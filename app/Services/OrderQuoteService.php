<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderQuoteService
{
    public function issue(Request $request, array $preview, OrderPreviewService $pricing): string
    {
        $token = Str::random(64);
        $snapshot = $pricing->quoteSnapshot($preview);
        $request->session()->put("order_preview.$token", [
            'quote_id' => hash('sha256', $token),
            'configuration_fingerprint' => $snapshot['configuration_fingerprint'],
            'configuration' => $snapshot['configuration'],
            'price_fingerprint' => $snapshot['price_fingerprint'],
            'prices' => $snapshot['prices'],
            'rate_version' => $snapshot['rate_version'],
            'created_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(20)->timestamp,
            'used_order' => null,
        ]);

        return $token;
    }

    public function verify(Request $request, string $token, array $preview, OrderPreviewService $pricing): array
    {
        $quote = $request->session()->get("order_preview.$token");
        if (! $quote) return ['status' => 'missing'];
        if (! empty($quote['used_order'])) return ['status' => 'used', 'order' => $quote['used_order']];
        if (($quote['expires_at'] ?? 0) < now()->timestamp) {
            $this->logRejection($quote, 'expired');
            return ['status' => 'expired'];
        }

        $current = $pricing->quoteSnapshot($preview);
        if (! hash_equals((string) $quote['configuration_fingerprint'], $current['configuration_fingerprint'])) {
            $differences = $this->differences($quote['configuration'] ?? [], $current['configuration']);
            $this->logRejection($quote, 'configuration_changed', $current, $differences);
            return ['status' => 'configuration_changed', 'changed_fields' => array_column($differences, 'field')];
        }
        if (! hash_equals((string) $quote['price_fingerprint'], $current['price_fingerprint'])) {
            $this->logRejection($quote, 'price_changed', $current);
            return ['status' => 'price_changed', 'old_prices' => $quote['prices'], 'new_prices' => $current['prices']];
        }

        return ['status' => 'valid'];
    }

    public function markUsed(Request $request, string $token, array $order): void
    {
        $key = "order_preview.$token";
        $quote = $request->session()->get($key);
        if ($quote) {
            $quote['used_order'] = $order;
            $quote['used_at'] = now()->timestamp;
            $request->session()->put($key, $quote);
        }
    }

    private function logRejection(array $quote, string $reason, ?array $current = null, array $differences = []): void
    {
        Log::warning('Cotización pública rechazada', [
            'quote_id' => $quote['quote_id'] ?? null,
            'reason' => $reason,
            'old_prices_cents' => $quote['prices'] ?? null,
            'new_prices_cents' => $current['prices'] ?? null,
            'old_rate_version' => $quote['rate_version'] ?? null,
            'new_rate_version' => $current['rate_version'] ?? null,
            'created_at' => $quote['created_at'] ?? null,
            'expires_at' => $quote['expires_at'] ?? null,
            'changed_fields' => array_column($differences, 'field'),
        ]);

        if (app()->environment('local') && $differences !== []) {
            Log::debug('Comparación de cotización', ['differences' => $differences]);
        }
    }

    private function differences(mixed $quoted, mixed $current, string $path = ''): array
    {
        if (! is_array($quoted) || ! is_array($current)) {
            return $quoted === $current ? [] : [[
                'field' => $path,
                'quote_value' => $quoted,
                'confirmation_value' => $current,
                'quote_type' => get_debug_type($quoted),
                'confirmation_type' => get_debug_type($current),
            ]];
        }

        $differences = [];
        foreach (array_unique(array_merge(array_keys($quoted), array_keys($current))) as $key) {
            $childPath = $path === '' ? (string) $key : $path.'.'.$key;
            $differences = array_merge($differences, $this->differences($quoted[$key] ?? null, $current[$key] ?? null, $childPath));
        }

        return $differences;
    }
}
