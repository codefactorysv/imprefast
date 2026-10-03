<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Models\TelegramConnection;
use App\Models\TelegramLinkCode;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class TelegramController extends Controller
{
    public function connect()
    {
        abort_unless(config('services.telegram.enabled') && config('services.telegram.bot_username'), 422, 'Configura primero el bot de Telegram en el archivo .env.');
        TelegramLinkCode::where('user_id', Auth::id())->whereNull('used_at')->delete();
        $code = Str::random(40);
        TelegramLinkCode::create(['user_id' => Auth::id(), 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes(10)]);

        return redirect()->away('https://t.me/'.rawurlencode(config('services.telegram.bot_username')).'?start='.rawurlencode($code));
    }

    public function webhook(Request $request, TelegramNotificationService $telegram)
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) return response()->json(['ok' => false], 403);
        $data = $request->validate(['update_id' => 'required|integer', 'message.text' => 'nullable|string|max:200', 'message.chat.id' => 'required_with:message|integer', 'message.from.username' => 'nullable|string|max:100', 'message.from.first_name' => 'nullable|string|max:100', 'message.from.last_name' => 'nullable|string|max:100']);
        $text = trim((string) data_get($data, 'message.text', ''));
        $chatId = (string) data_get($data, 'message.chat.id', '');
        if ($text === '/start') {
            $telegram->send($chatId, 'Para conectar tu cuenta, abre Administración → Configuración → Telegram y presiona “Conectar Telegram”.');
        } elseif (preg_match('/^\/start\s+([A-Za-z0-9]{40})$/', $text, $match)) {
            $hash = hash('sha256', $match[1]);
            $link = TelegramLinkCode::where('code_hash', $hash)->whereNull('used_at')->where('expires_at', '>', now())->first();
            if ($link) {
                TelegramConnection::updateOrCreate(['user_id' => $link->user_id], ['chat_id' => $chatId, 'username' => data_get($data, 'message.from.username'), 'display_name' => trim(data_get($data, 'message.from.first_name', '').' '.data_get($data, 'message.from.last_name', '')), 'connected_at' => now()]);
                $link->update(['used_at' => now()]);
                $telegram->send($chatId, "✅ <b>Telegram conectado correctamente.</b>\n\nA partir de ahora recibirás avisos de pedidos nuevos.");
            } else {
                $telegram->send($chatId, 'Este enlace de conexión venció. Regresa al panel y genera uno nuevo.');
            }
        } elseif ($text === '/status' && TelegramConnection::where('chat_id', $chatId)->exists()) {
            $telegram->send($chatId, '✅ Las notificaciones de pedidos están activas.');
        }

        return response()->json(['ok' => true]);
    }

    public function test(TelegramNotificationService $telegram)
    {
        $connection = TelegramConnection::where('user_id', Auth::id())->first();
        if (! $connection) return back()->withErrors(['telegram' => 'Telegram todavía no está conectado.']);
        try {
            $telegram->send($connection->chat_id, '✅ <b>NOTIFICACIONES ACTIVAS</b>\n\nLas notificaciones administrativas de pedidos están configuradas correctamente.');
            return back()->with('ok', 'Mensaje de prueba enviado correctamente.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['telegram' => 'No se pudo enviar el mensaje de prueba. Revisa la configuración del bot.']);
        }
    }

    public function disconnect()
    {
        TelegramConnection::where('user_id', Auth::id())->delete();
        return back()->with('ok', 'Telegram fue desconectado.');
    }

    public function review(Order $order)
    {
        if (! $order->reviewed_at) $order->update(['reviewed_at' => now(), 'reviewed_by' => Auth::id()]);
        $order->adminNotifications()->where('channel', 'database')->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('ok', 'Pedido marcado como revisado.');
    }

    public function poll(Request $request)
    {
        $after = max(0, (int) $request->query('after', 0));
        $orders = Order::with('service')->whereNull('reviewed_at')->where('id', '>', $after)->oldest('id')->limit(20)->get()->map(fn ($order) => ['id' => $order->id, 'code' => $order->code, 'service' => $order->service->name, 'total' => $order->total, 'requires_quote' => $order->requires_quote, 'url' => route('admin.orders.show', $order)]);
        return response()->json(['count' => Order::whereNull('reviewed_at')->count(), 'orders' => $orders, 'sound_enabled' => filter_var(Setting::value('admin_sound_enabled', '1'), FILTER_VALIDATE_BOOL)]);
    }
}
