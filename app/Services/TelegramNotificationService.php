<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramNotificationService
{
    public function send(string $chatId, string $text, ?string $buttonText = null, ?string $buttonUrl = null): string
    {
        $token = (string) config('services.telegram.bot_token');
        if (! config('services.telegram.enabled') || $token === '') throw new RuntimeException('Telegram no está configurado.');
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($buttonText && $buttonUrl) $payload['reply_markup'] = ['inline_keyboard' => [[['text' => $buttonText, 'url' => $buttonUrl]]]];
        $response = Http::asJson()->timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        if (! $response->successful() || ! $response->json('ok')) throw new RuntimeException('Telegram rechazó el mensaje.');

        return (string) $response->json('result.message_id');
    }

    public function orderMessage(Order $order, bool $reminder = false): array
    {
        $order->loadMissing('service');
        $manual = $order->requires_quote || $order->status === 'pending_quote';
        $urgent = $order->urgent ? "🔴 <b>PEDIDO URGENTE</b>\n\n" : '';
        if ($reminder) {
            $title = $manual ? '⚠️ <b>SOLICITUD PENDIENTE</b>' : '⚠️ <b>PEDIDO SIN REVISAR</b>';
            $text = "{$title}\n\nEl pedido <b>".$this->e($order->code)."</b> todavía no ha sido revisado.\n\nServicio: ".$this->e($order->service->name)."\nCliente: ".$this->e($order->customer_name);
        } else {
            $title = $manual ? '📝 <b>NUEVA SOLICITUD DE COTIZACIÓN</b>' : '🛠️ <b>NUEVO PEDIDO</b>';
            $lines = [$urgent.$title, '', '<b>Código:</b> '.$this->e($order->code), '<b>Cliente:</b> '.$this->e($order->customer_name), '<b>Servicio:</b> '.$this->e($order->service->name)];
            if ($manual) {
                if ($order->topic) $lines[] = '<b>Tema:</b> '.$this->e($order->topic);
                if ($order->education_level) $lines[] = '<b>Nivel:</b> '.$this->e($order->education_level);
                if ($order->pages) $lines[] = '<b>Extensión solicitada:</b> '.(int) $order->pages.' páginas';
                $lines[] = '<b>Domicilio calculado:</b> '.$this->money($order->delivery_fee);
                $lines[] = '<b>Precio del trabajo:</b> Pendiente de cotización';
            } else {
                $lines[] = '<b>Subtotal:</b> '.$this->money($order->work_subtotal);
                $lines[] = '<b>Domicilio:</b> '.$this->money($order->delivery_fee);
                $lines[] = '<b>Total:</b> '.$this->money($order->total);
            }
            $lines[] = '<b>Entrega solicitada:</b> '.$order->requested_at?->format('d/m/Y, g:i a');
            if ($order->urgent) $lines[] = '<b>Urgente:</b> Sí';
            if ($order->service->pickup_return) $lines[] = '📍 Requiere recoger documentos físicos';
            $lines[] = '';
            $lines[] = $manual ? 'Debes revisar la solicitud y agregar el precio del trabajo.' : 'Revisa el pedido desde el botón inferior.';
            $text = implode("\n", array_filter($lines, fn ($line) => $line !== null));
        }

        return ['text' => $text, 'button' => $manual && ! $reminder ? 'Cotizar pedido' : 'Ver pedido', 'url' => rtrim((string) config('app.url'), '/').'/admin/pedidos/'.$order->id];
    }

    private function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private function money(mixed $value): string { return $value === null ? 'Pendiente' : '$'.number_format((float) $value, 2); }
}
