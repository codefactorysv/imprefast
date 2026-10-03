<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendTelegramOrderNotification;

class SendNewOrderTelegramNotification
{
    public function handle(OrderCreated $event): void
    {
        SendTelegramOrderNotification::dispatch($event->order->id, 'new_order')->onQueue('notifications');
    }
}
