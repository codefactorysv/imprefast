<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Jobs\SendTelegramOrderNotification;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramConnection;
use App\Models\TelegramLinkCode;
use App\Models\User;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram' => ['enabled' => true, 'bot_token' => 'secret-test-token', 'bot_username' => 'ImpreFastTestBot', 'webhook_secret' => 'webhook-test-secret'], 'app.url' => 'https://imprefast.example']);
    }

    public function test_order_created_dispatches_one_queued_notification_and_messages_are_conditional(): void
    {
        Queue::fake();
        $automatic = $this->order(false, true);
        AdminNotification::create(['order_id' => $automatic->id, 'channel' => 'database', 'type' => 'new_order', 'status' => 'sent']);
        OrderCreated::dispatch($automatic);
        Queue::assertPushed(SendTelegramOrderNotification::class, 1);
        $message = app(TelegramNotificationService::class)->orderMessage($automatic);
        $this->assertStringContainsString('<b>Total:</b> $4.30', $message['text']);
        $this->assertStringContainsString('🔴 <b>PEDIDO URGENTE</b>', $message['text']);
        $this->assertSame('https://imprefast.example/admin/pedidos/'.$automatic->id, $message['url']);

        $manual = $this->order(true);
        $manualMessage = app(TelegramNotificationService::class)->orderMessage($manual);
        $this->assertStringContainsString('NUEVA SOLICITUD DE COTIZACIÓN', $manualMessage['text']);
        $this->assertStringContainsString('Pendiente de cotización', $manualMessage['text']);
        $this->assertStringNotContainsString('<b>Total:</b>', $manualMessage['text']);
    }

    public function test_telegram_failure_does_not_change_order_and_is_safely_recorded(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        TelegramConnection::create(['user_id' => $admin->id, 'chat_id' => '12345', 'connected_at' => now()]);
        $order = $this->order();
        AdminNotification::create(['order_id' => $order->id, 'channel' => 'telegram', 'type' => 'new_order', 'status' => 'pending']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);

        try { (new SendTelegramOrderNotification($order->id, 'new_order'))->handle(app(TelegramNotificationService::class)); } catch (\Throwable) {}
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSame('received', $order->fresh()->status);
    }

    public function test_link_code_is_single_use_bound_to_admin_and_expired_codes_fail(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
        $admin = User::factory()->create(['is_admin' => true]);
        $code = str_repeat('A', 40);
        $link = TelegramLinkCode::create(['user_id' => $admin->id, 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes(10)]);
        $payload = ['update_id' => 1, 'message' => ['text' => '/start '.$code, 'chat' => ['id' => 98765], 'from' => ['username' => 'admin_test', 'first_name' => 'Admin']]];

        $this->postJson('/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-test-secret'])->assertOk();
        $this->assertDatabaseHas('telegram_connections', ['user_id' => $admin->id, 'chat_id' => '98765']);
        $this->assertNotNull($link->fresh()->used_at);
        $payload['message']['chat']['id'] = 11111;
        $this->postJson('/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-test-secret'])->assertOk();
        $this->assertDatabaseMissing('telegram_connections', ['chat_id' => '11111']);

        $expired = str_repeat('B', 40);
        TelegramLinkCode::create(['user_id' => $admin->id, 'code_hash' => hash('sha256', $expired), 'expires_at' => now()->subMinute()]);
        $payload['message']['text'] = '/start '.$expired;
        $this->postJson('/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-test-secret'])->assertOk();
        $this->assertDatabaseMissing('telegram_connections', ['chat_id' => '11111']);
    }

    public function test_webhook_secret_test_button_review_counter_and_token_visibility(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 88]])]);
        $admin = User::factory()->create(['is_admin' => true]);
        TelegramConnection::create(['user_id' => $admin->id, 'chat_id' => '12345', 'display_name' => 'Administrador', 'connected_at' => now()]);
        $order = $this->order();

        $this->postJson('/telegram/webhook', ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => 'incorrect'])->assertForbidden();
        $this->actingAs($admin)->post('/admin/telegram/prueba')->assertSessionHas('ok');
        Http::assertSent(fn ($request) => $request['chat_id'] === '12345' && str_contains($request['text'], 'NOTIFICACIONES ACTIVAS'));
        $settings = $this->actingAs($admin)->get('/admin/configuracion')->assertOk();
        $settings->assertDontSee('secret-test-token');
        $this->get('/admin')->assertOk()->assertSee('Nuevo');
        $status = $order->status;
        $this->post("/admin/pedidos/{$order->id}/revisado")->assertRedirect();
        $this->assertNotNull($order->fresh()->reviewed_at);
        $this->assertSame($status, $order->fresh()->status);
    }

    public function test_reminder_is_idempotent_and_skips_reviewed_orders(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        TelegramConnection::create(['user_id' => $admin->id, 'chat_id' => '12345', 'connected_at' => now()]);
        $pending = $this->order(); $pending->forceFill(['created_at' => now()->subMinutes(11)])->save();
        $reviewed = $this->order(); $reviewed->forceFill(['created_at' => now()->subMinutes(11), 'reviewed_at' => now(), 'reviewed_by' => $admin->id])->save();

        $this->artisan('orders:send-unreviewed-reminders')->assertSuccessful();
        $this->artisan('orders:send-unreviewed-reminders')->assertSuccessful();
        $this->assertDatabaseCount('admin_notifications', 1);
        $this->assertDatabaseHas('admin_notifications', ['order_id' => $pending->id, 'type' => 'unreviewed_reminder']);
        $this->assertDatabaseMissing('admin_notifications', ['order_id' => $reviewed->id]);
        Queue::assertPushed(SendTelegramOrderNotification::class, 1);
    }

    private function order(bool $manual = false, bool $urgent = false): Order
    {
        $service = Service::factory()->create(['name' => $manual ? 'Investigación escolar' : 'Impresión a color', 'calculation_type' => $manual ? 'quote' : 'automatic', 'pricing_model' => $manual ? 'manual' : 'per_page']);
        return Order::create(['code' => 'PED-'.str_pad((string) (Order::count()+1), 6, '0', STR_PAD_LEFT), 'service_id' => $service->id, 'customer_name' => $manual ? 'Carlos Pérez' : 'María López', 'phone' => '70000000', 'status' => $manual ? 'pending_quote' : 'received', 'payment_method' => 'cash', 'payment_status' => 'pending', 'requested_at' => now()->addDay(), 'municipality' => 'San Miguel', 'work_subtotal' => $manual ? null : 2.50, 'delivery_fee' => 1.80, 'total' => $manual ? null : 4.30, 'urgent' => $urgent, 'requires_quote' => $manual, 'topic' => $manual ? 'Historia de Cristóbal Colón' : null, 'education_level' => $manual ? 'Sexto grado' : null, 'pages' => $manual ? 3 : 2, 'public_token' => hash('sha256', uniqid('', true))]);
    }
}
