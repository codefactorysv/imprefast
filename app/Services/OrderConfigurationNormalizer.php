<?php

namespace App\Services;

class OrderConfigurationNormalizer
{
    public function normalizeOrderConfiguration(array $preview): array
    {
        $composition = $preview['image_composition'] ?? null;
        $combinedIds = $this->uniqueStrings($composition['file_ids'] ?? []);
        $files = [];

        foreach ($preview['file_details'] ?? [] as $detail) {
            $clientId = trim((string) ($detail['client_id'] ?? ''));
            $combined = in_array($clientId, $combinedIds, true)
                || filter_var($detail['included_in_composition'] ?? false, FILTER_VALIDATE_BOOL);
            $files[$clientId] = [
                'file_id' => $clientId,
                'mode' => $combined ? 'combined' : 'individual',
                'document_type' => $this->nullableText($detail['document_type'] ?? null),
                'source_pages' => (int) ($detail['source_pages'] ?? 1),
                'copies' => $combined ? null : $this->nullableInteger($detail['copies'] ?? null),
                'paper_size' => $this->nullableText($detail['paper_size'] ?? null),
                'double_sided' => $this->boolean($detail['double_sided'] ?? false),
                'stapled' => $this->boolean($detail['stapled'] ?? false),
                'print_units' => $combined ? 0 : (int) ($detail['print_units'] ?? 0),
            ];
        }
        ksort($files);

        return $this->canonicalize([
            'service_id' => (int) ($preview['service_id'] ?? 0),
            'pages' => (int) ($preview['pages'] ?? 0),
            'copies' => (int) ($preview['copies'] ?? 0),
            'file_details' => $files,
            'combine_images' => $composition !== null,
            'image_composition' => $composition ? [
                // This order is commercially meaningful and must be preserved.
                'file_ids' => $combinedIds,
                'images_per_page' => (int) ($composition['images_per_page'] ?? 0),
                'resulting_pages' => (int) ($composition['resulting_pages'] ?? 0),
                'combined_copies' => (int) ($composition['copies'] ?? 0),
                'billable_pages' => (int) ($composition['billable_pages'] ?? 0),
                'instructions' => $this->nullableText($composition['instructions'] ?? null),
            ] : null,
            'print_units' => (int) ($preview['print_units'] ?? 0),
            'urgent' => $this->boolean($preview['urgent'] ?? false),
            'manual_quote' => $this->boolean($preview['manual_quote'] ?? false),
            'calculation_status' => $this->nullableText($preview['calculation_status'] ?? null),
            'service_requires_quote' => $this->boolean($preview['service_requires_quote'] ?? false),
            'delivery_requires_review' => $this->boolean($preview['delivery_requires_review'] ?? false),
            'quantity' => $this->nullableInteger($preview['quantity'] ?? null),
            'variant' => isset($preview['variant']) ? [
                'type' => $this->nullableText($preview['variant']['type'] ?? null),
                'id' => (int) ($preview['variant']['id'] ?? 0),
                'effect' => $this->nullableText($preview['variant']['effect'] ?? null),
            ] : null,
            'addon_ids' => array_values(array_unique(array_map(
                fn ($addon) => (int) $addon['id'],
                $preview['addons'] ?? [],
            ))),
        ]);
    }

    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique(array_map(fn ($value) => trim((string) $value), $values)));
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) return $value;
        if (! array_is_list($value)) ksort($value);

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }
}
