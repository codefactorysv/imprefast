<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TelegramConnection;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendTelegramOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 15;
    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId, public string $type) { $this->onQueue('notifications'); }

    public function handle(TelegramNotificationService $telegram): void
    {
        $order = Order::with('service')->findOrFail($this->orderId);
        $notification = AdminNotification::firstOrCreate(
            ['order_id' => $order->id, 'channel' => 'telegram', 'type' => $this->type],
            ['status' => 'pending', 'scheduled_at' => now()],
        );
        if ($notification->status === 'sent') return;
        $connection = TelegramConnection::first();
        $enabled = filter_var(Setting::value('telegram_admin_enabled', '1'), FILTER_VALIDATE_BOOL);
        if (! config('services.telegram.enabled') || ! config('services.telegram.bot_token') || ! $connection || ! $enabled) {
            $notification->update(['status' => 'skipped', 'last_error' => 'Telegram no está conectado o está desactivado.']);
            return;
        }
        $message = $telegram->orderMessage($order, $this->type === 'unreviewed_reminder');
        $messageId = $telegram->send($connection->chat_id, $message['text'], $message['button'], $message['url']);
        $notification->update(['status' => 'sent', 'telegram_message_id' => $messageId, 'attempts' => $this->attempts(), 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        AdminNotification::where(['order_id' => $this->orderId, 'channel' => 'telegram', 'type' => $this->type])
            ->update(['status' => 'failed', 'attempts' => $this->tries, 'last_error' => mb_substr($exception?->getMessage() ?: 'Error de Telegram', 0, 500)]);
        report($exception);
    }
}
