<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramOrderNotification;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TelegramConnection;
use Illuminate\Console\Command;

class SendUnreviewedOrderReminders extends Command
{
    protected $signature = 'orders:send-unreviewed-reminders';
    protected $description = 'Programa un aviso único para pedidos que todavía no han sido revisados';

    public function handle(): int
    {
        if (! filter_var(Setting::value('telegram_reminders_enabled', '1'), FILTER_VALIDATE_BOOL) || ! TelegramConnection::exists()) return self::SUCCESS;
        $minutes = max(1, min(1440, (int) Setting::value('telegram_reminder_minutes', 10)));
        Order::whereNull('reviewed_at')->where('status', '!=', 'cancelled')->where('created_at', '<=', now()->subMinutes($minutes))->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                $notification = AdminNotification::firstOrCreate(['order_id' => $order->id, 'channel' => 'telegram', 'type' => 'unreviewed_reminder'], ['status' => 'pending', 'scheduled_at' => now()]);
                if ($notification->wasRecentlyCreated) SendTelegramOrderNotification::dispatch($order->id, 'unreviewed_reminder')->onQueue('notifications');
            }
        });
        return self::SUCCESS;
    }
}
