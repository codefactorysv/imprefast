<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_maps_links_and_audit_location_recalculation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::create(['name' => 'Impresión', 'slug' => 'admin-location', 'description' => 'Prueba', 'calculation_type' => 'automatic', 'price_per_page' => .10]);
        $order = Order::create([
            'code' => 'PED-000321', 'service_id' => $service->id, 'customer_name' => 'Cliente',
            'phone' => '70000000', 'status' => 'received', 'payment_method' => 'cash',
            'pages' => 2, 'copies' => 1, 'work_subtotal' => .20, 'delivery_fee' => 1.50,
            'total' => 1.70, 'requested_at' => now()->addDay(), 'municipality' => 'San Miguel',
            'latitude' => 13.493874, 'longitude' => -88.203702,
            'public_token' => hash('sha256', Str::random(64)),
        ]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Ubicación de entrega')
            ->assertSee('Abrir ubicación')
            ->assertSee('Iniciar ruta')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=13.493874,-88.203702', false);

        $this->actingAs($admin)->post(route('admin.orders.location', $order), [
            'latitude' => 13.503874, 'longitude' => -88.203702,
            'department' => 'San Miguel', 'municipality' => 'San Miguel',
            'neighborhood' => 'Centro', 'street' => 'Calle Principal',
            'house_number' => '12', 'address_reference' => 'Portón azul',
            'reason' => 'El cliente corrigió el marcador.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertSame('1.70', $order->total);
        $this->assertEqualsWithDelta(13.503874, (float) $order->latitude, 0.0000001);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $order->id, 'action' => 'order_location_recalculated']);
    }

    public function test_public_lookup_does_not_expose_location(): void
    {
        $service = Service::create(['name' => 'Impresión', 'slug' => 'private-location', 'description' => 'Prueba', 'calculation_type' => 'automatic', 'price_per_page' => .10]);
        Order::create(['code' => 'PED-000654', 'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000', 'status' => 'received', 'payment_method' => 'cash', 'requested_at' => now()->addDay(), 'municipality' => 'San Miguel', 'street' => 'CALLE-SECRETA-987', 'latitude' => 13.493874, 'longitude' => -88.203702, 'public_token' => hash('sha256', Str::random(64))]);

        $this->post('/consultar', ['code' => 'PED-000654', 'phone' => '70000000'])
            ->assertOk()->assertDontSee('CALLE-SECRETA-987')->assertDontSee('13.493874');
    }
}
