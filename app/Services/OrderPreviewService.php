<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OrderPreviewService
{
    public function __construct(
        private readonly RouteCalculatorInterface $router,
        private readonly PricingService $pricing,
        private readonly OrderConfigurationNormalizer $configurationNormalizer,
    ) {}

    public function calculate(array $data): array
    {
        $service = Service::with(['variants', 'priceTiers', 'addons'])->where('active', true)->findOrFail($data['service_id']);
        $urgent = filter_var($data['urgent'] ?? false, FILTER_VALIDATE_BOOL);
        $manual = $service->calculation_type === 'quote';
        $reason = $manual ? 'service_quote' : null;
        $route = null;
        $delivery = null;

        if (! isset($data['latitude'], $data['longitude']) || (array_key_exists('delivery_confirmed', $data) && ! filter_var($data['delivery_confirmed'], FILTER_VALIDATE_BOOL))) {
            $manual = true;
            $reason = 'missing_location';
        } else {
            try {
                $originLatitude = (float) Setting::value('business_latitude', env('BUSINESS_LATITUDE', 13.483874));
                $originLongitude = (float) Setting::value('business_longitude', env('BUSINESS_LONGITUDE', -88.203702));
                $calculateRoute = fn () => $this->router->calculate($originLatitude, $originLongitude, (float) $data['latitude'], (float) $data['longitude']);
                $route = app()->environment('testing')
                    ? $calculateRoute()
                    : Cache::remember('route:'.sha1(implode('|', [$originLatitude, $originLongitude, $data['latitude'], $data['longitude']])), now()->addMinutes(10), $calculateRoute);

                $maxRadius = (float) Setting::value('delivery_max_radius_km', env('DELIVERY_MAX_RADIUS_KM', 10));
                if ($route->distanceKm > $maxRadius) {
                    $manual = true;
                    $reason = 'outside_radius';
                } else {
                    $delivery = $this->pricing->delivery($route->distanceKm, $service->pickup_return, $urgent);
                }
            } catch (Throwable $exception) {
                report($exception);
                $manual = true;
                $reason = 'route_error';
            }
        }

        $fileDetails = collect($data['file_details'] ?? [])->values()->map(fn ($detail, $index) => [
            'index' => $index,
            'client_id' => (string) ($detail['client_id'] ?? ''),
            'name' => (string) ($detail['name'] ?? ''),
            'document_type' => (string) ($detail['document_type'] ?? 'document'),
            'source_pages' => max(1, (int) ($detail['source_pages'] ?? 1)),
            'page_detection' => (string) ($detail['page_detection'] ?? 'estimated'),
            'copies' => isset($detail['copies']) ? (int) $detail['copies'] : null,
            'paper_size' => $detail['paper_size'] ?? ($data['paper_size'] ?? 'carta'),
            'double_sided' => filter_var($detail['double_sided'] ?? ($data['double_sided'] ?? false), FILTER_VALIDATE_BOOL),
            'stapled' => filter_var($detail['stapled'] ?? ($data['stapled'] ?? false), FILTER_VALIDATE_BOOL),
        ])->values();
        $composition = null;
        $combinedIds = collect($data['combined_file_ids'] ?? [])->map(fn ($id) => (string) $id)->unique()->values();
        $combinedIndexes = $combinedIds->map(fn ($id) => $fileDetails->search(fn ($detail) => $detail['client_id'] === $id))->values();
        if (filter_var($data['combine_images'] ?? false, FILTER_VALIDATE_BOOL) && $combinedIndexes->count() >= 2) {
            $imagesPerPage = (int) ($data['images_per_page'] ?? 2);
            $combinedCopies = max(1, (int) ($data['combined_copies'] ?? 1));
            $resultingPages = (int) ceil($combinedIndexes->count() / $imagesPerPage);
            $composition = [
                'file_ids' => $combinedIds->all(),
                'file_indexes' => $combinedIndexes->all(),
                'file_names' => $combinedIndexes->map(fn ($index) => $fileDetails->get($index)['name'])->all(),
                'page_groups' => $combinedIndexes->chunk($imagesPerPage)->values()->map(
                    fn ($indexes) => $indexes->map(fn ($index) => $fileDetails->get($index)['name'])->values()->all()
                )->all(),
                'image_count' => $combinedIndexes->count(),
                'images_per_page' => $imagesPerPage,
                'resulting_pages' => $resultingPages,
                'copies' => $combinedCopies,
                'billable_pages' => $resultingPages * $combinedCopies,
                'instructions' => $data['combined_instructions'] ?? null,
            ];
        }
        $individualDetails = $fileDetails->map(function ($detail) use ($combinedIndexes, $composition) {
            $included = $composition !== null && $combinedIndexes->contains($detail['index']);

            return $detail + ['included_in_composition' => $included];
        });
        $sourcePages = $individualDetails->where('included_in_composition', false)->sum('source_pages') + ($composition['resulting_pages'] ?? 0);
        $individualPrintUnits = $individualDetails->where('included_in_composition', false)->sum(fn ($detail) => $detail['source_pages'] * (int) $detail['copies']);
        $printUnits = $individualPrintUnits + ($composition['billable_pages'] ?? 0);
        $pricingModel = $service->calculation_type === 'quote' ? 'manual' : $service->pricing_model;
        $quantity = (int) ($data['quantity'] ?? $data['copies'] ?? 1);
        $unitPrice = $service->price_per_page !== null ? (float) $service->price_per_page : null;
        $workSubtotal = null;
        $selectedVariant = null;
        $selectedAddons = [];
        if ($pricingModel === 'per_page') {
            $workSubtotal = $fileDetails->isNotEmpty() ? round($printUnits * (float) $unitPrice, 2) : $this->pricing->printing((int) ($data['pages'] ?? 0), (int) ($data['copies'] ?? 1), (float) $unitPrice);
        } elseif ($service->pricing_model === 'per_unit') {
            $workSubtotal = round($quantity * (float) $unitPrice, 2);
        } elseif ($service->pricing_model === 'fixed') {
            $workSubtotal = $unitPrice;
        } elseif ($service->pricing_model === 'tiered') {
            $tier = $service->priceTiers->first(fn ($t) => $t->active && $quantity >= $t->minimum_quantity && ($t->maximum_quantity === null || $quantity <= $t->maximum_quantity));
            if ($tier) {
                $workSubtotal = (float) $tier->price;
                $unitPrice = (float) $tier->price;
                $selectedVariant = ['type' => 'tier', 'id' => $tier->id, 'name' => $tier->label, 'price' => (float) $tier->price];
            } else {
                $manual = true;
                $reason = 'tier_not_found';
            }
        }
        if (! empty($data['variant_id'])) {
            $variant = $service->variants->firstWhere('id', (int) $data['variant_id']);
            if ($variant && $variant->active) {
                $selectedVariant = ['type' => 'variant', 'id' => $variant->id, 'name' => $variant->name, 'price' => (float) $variant->price, 'effect' => $variant->price_effect];
                if ($workSubtotal !== null) {
                    $workSubtotal = $variant->price_effect === 'replace' ? round((float) $variant->price * max(1, $quantity), 2) : round($workSubtotal + (float) $variant->price * max(1, $quantity), 2);
                }
            }
        }
        foreach ($service->addons->where('active', true) as $addon) {
            if (in_array($addon->id, array_map('intval', $data['addon_ids'] ?? []), true)) {
                $amount = $addon->price_type === 'per_unit' ? (float) $addon->price * max(1, $quantity) : (float) $addon->price;
                $selectedAddons[] = ['id' => $addon->id, 'name' => $addon->name, 'price' => (float) $amount, 'price_type' => $addon->price_type];
                if ($workSubtotal !== null) {
                    $workSubtotal = round($workSubtotal + $amount, 2);
                }
            }
        }

        if ($pricingModel !== 'manual' && $unitPrice === null && in_array($service->pricing_model, ['per_page', 'per_unit', 'fixed'], true)) {
            $manual = true;
            $reason = 'invalid_service_price';
            $workSubtotal = null;
        }

        $serviceRequiresQuote = $service->calculation_type === 'quote'
            || in_array($reason, ['service_quote', 'image_combination', 'tier_not_found', 'invalid_service_price'], true);
        $calculationStatus = match ($reason) {
            'outside_radius' => 'outside_coverage',
            'missing_location' => 'missing_coordinates',
            'route_error' => 'route_provider_error',
            'invalid_service_price', 'tier_not_found' => 'invalid_service_price',
            default => 'success',
        };
        $deliveryRequiresReview = in_array($calculationStatus, ['outside_coverage', 'missing_coordinates', 'route_provider_error'], true);

        $total = ! $manual && $delivery
            ? round($workSubtotal + $delivery['total'], 2)
            : null;

        return [
            'service_id' => $service->id,
            'service_name' => $service->name,
            'calculation_type' => $service->calculation_type,
            'pickup_return' => $service->pickup_return,
            'price_per_page' => $service->price_per_page !== null ? (float) $service->price_per_page : null,
            'pricing_model' => $pricingModel, 'billing_unit' => $service->billing_unit, 'quantity' => $pricingModel === 'per_page' && $fileDetails->isNotEmpty() ? null : $quantity, 'unit_price' => $unitPrice, 'variant' => $selectedVariant, 'addons' => $selectedAddons,
            'pages' => $fileDetails->isNotEmpty() ? $sourcePages : (int) ($data['pages'] ?? 0),
            'copies' => (int) ($data['copies'] ?? 0),
            'file_details' => $individualDetails->map(fn ($detail) => $detail + [
                'print_units' => $detail['included_in_composition'] ? 0 : $detail['source_pages'] * $detail['copies'],
                'unit_price' => $unitPrice,
                'subtotal' => $detail['included_in_composition'] ? 0 : round($detail['source_pages'] * $detail['copies'] * (float) $unitPrice, 2),
            ])->all(),
            'image_composition' => $composition ? $composition + [
                'unit_price' => $unitPrice,
                'subtotal' => round($composition['billable_pages'] * (float) $unitPrice, 2),
            ] : null,
            'print_units' => $printUnits,
            'work_subtotal' => $workSubtotal,
            'distance_km' => $route?->distanceKm,
            'duration_minutes' => $route?->durationMinutes,
            'route_provider' => $route?->provider,
            'delivery' => $delivery,
            'urgent' => $urgent,
            'manual_quote' => $manual,
            'reason' => $reason,
            'calculation_status' => $calculationStatus,
            'service_requires_quote' => $serviceRequiresQuote,
            'delivery_requires_review' => $deliveryRequiresReview,
            'total' => $total,
            'configuration' => ['requires_file' => $service->requires_file, 'requires_pages' => $service->requires_pages, 'requires_quantity' => $service->requires_quantity, 'requires_copies' => $service->requires_copies, 'requires_topic' => $service->requires_topic, 'requires_education_level' => $service->requires_education_level, 'allows_cover' => $service->allows_cover, 'allows_images' => $service->allows_images, 'allows_stapling' => $service->allows_stapling, 'requires_output_format' => $service->requires_output_format, 'requires_pickup_instructions' => $service->requires_pickup_instructions, 'requires_deposit' => $service->requires_deposit, 'allows_letter' => $service->allows_letter, 'allows_legal' => $service->allows_legal, 'allows_duplex' => $service->allows_duplex, 'allows_urgent' => $service->allows_urgent, 'variants' => $service->variants->where('active', true)->values(), 'tiers' => $service->priceTiers->where('active', true)->values(), 'addons' => $service->addons->where('active', true)->values()],
        ];
    }

    public function fingerprint(array $preview): string
    {
        $snapshot = $this->quoteSnapshot($preview);

        return hash('sha256', $snapshot['configuration_fingerprint'].'|'.$snapshot['price_fingerprint']);
    }

    public function quoteSnapshot(array $preview): array
    {
        $configuration = $this->configurationNormalizer->normalizeOrderConfiguration($preview);
        $prices = [
            'unit_price_cents' => $this->cents($preview['unit_price']),
            'service_subtotal_cents' => $this->cents($preview['work_subtotal']),
            'delivery_fee_cents' => $this->cents($preview['delivery']['total'] ?? null),
            'total_cents' => $this->cents($preview['total']),
        ];
        $rateVersion = hash('sha256', json_encode([
            'price_per_page_cents' => $this->cents($preview['price_per_page']),
            'delivery_base_cents' => $this->cents($preview['delivery']['base'] ?? null),
            'delivery_per_km_cents' => $this->cents($preview['delivery']['per_km'] ?? null),
            'delivery_minimum_cents' => $this->cents($preview['delivery']['minimum'] ?? null),
            'delivery_factor_milli' => isset($preview['delivery']['factor']) ? (int) round((float) $preview['delivery']['factor'] * 1000) : null,
            'urgent_cents' => $this->cents($preview['delivery']['urgent'] ?? null),
        ]));

        return [
            'configuration_fingerprint' => hash('sha256', json_encode($this->canonicalize($configuration), JSON_UNESCAPED_UNICODE)),
            'configuration' => $configuration,
            'price_fingerprint' => hash('sha256', json_encode($prices)),
            'prices' => $prices,
            'rate_version' => $rateVersion,
        ];
    }

    private function cents(mixed $value): ?int
    {
        return $value === null ? null : (int) round((float) $value * 100);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) return $value;
        if (! array_is_list($value)) ksort($value);

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }
}
