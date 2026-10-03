<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'received', bool $requiresQuote = false): Order
    {
        $service = Service::factory()->create(['calculation_type' => $requiresQuote ? 'quote' : 'automatic']);

        return Order::create([
            'code' => 'PED-'.Str::upper(Str::random(6)), 'service_id' => $service->id,
            'customer_name' => 'Cliente', 'phone' => '70000000', 'status' => $status,
            'requires_quote' => $requiresQuote, 'payment_method' => 'cash', 'payment_status' => 'pending',
            'requested_at' => now()->addDay(), 'municipality' => 'San Miguel', 'total' => 10,
            'public_token' => hash('sha256', Str::random(64)),
        ]);
    }

    public function test_normal_flow_is_sequential_and_idempotent(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);
        $statuses = app(OrderStatusService::class);

        foreach (['preparing', 'ready', 'on_the_way', 'delivered'] as $status) {
            $statuses->transition($order->refresh(), $status, $admin);
            $this->assertSame($status, $order->refresh()->status);
        }
        $this->assertCount(4, $order->histories);
        $statuses->transition($order->refresh(), 'delivered', $admin);
        $this->assertCount(4, $order->histories()->get());

        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'ready'])
            ->assertSessionHasErrors('status');
        $this->assertSame('delivered', $order->refresh()->status);
        $this->assertCount(4, $order->histories()->get());
    }

    public function test_invalid_jump_and_cancellation_without_reason_are_rejected(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'delivered'])
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('received', $order->refresh()->status);
        $this->assertDatabaseCount('status_histories', 0);
    }

    public function test_accepted_quote_moves_pending_order_to_received(): void
    {
        $order = $this->order('pending_quote', true);
        $admin = User::factory()->create(['is_admin' => true]);
        $order->quotes()->create([
            'version' => 1, 'work_price' => 8, 'delivery_price' => 2, 'total' => 10,
            'expires_at' => now()->addDay(), 'status' => 'accepted', 'token' => hash('sha256', Str::random(64)),
        ]);

        app(OrderStatusService::class)->transition($order, 'received', $admin, null, 'quotation');
        $this->assertSame('received', $order->refresh()->status);
        $this->assertDatabaseHas('status_histories', ['order_id' => $order->id, 'from_status' => 'pending_quote', 'to_status' => 'received', 'origin' => 'quotation']);
    }

    public function test_payment_does_not_change_operational_status_and_admin_has_only_valid_action(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.orders.payment', $order), ['amount' => 5, 'method' => 'cash', 'status' => 'verified'])
            ->assertRedirect();
        $this->assertSame('received', $order->refresh()->status);
        $this->assertSame('partial', $order->payment_status);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Iniciar preparación')->assertDontSee('Nuevo estado')
            ->assertDontSee('Esperando confirmación')->assertDontSee('Esperando pago')->assertDontSee('Confirmado');
    }
}
