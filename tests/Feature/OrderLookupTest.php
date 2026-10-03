<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_page_responds_without_server_error(): void
    {
        $this->get('/consultar')
            ->assertOk()
            ->assertSee('Consultar pedido');
    }

    public function test_lookup_validates_only_code(): void
    {
        $this->post('/consultar', [])
            ->assertSessionHasErrors(['code'])->assertSessionDoesntHaveErrors(['phone']);
    }

    public function test_customer_can_find_an_order_with_code_and_phone(): void
    {
        $service = Service::create([
            'name' => 'Impresión',
            'slug' => 'impresion-lookup',
            'description' => 'Impresión de prueba',
            'calculation_type' => 'automatic',
            'price_per_page' => 0.10,
        ]);

        Order::create([
            'code' => 'PED-000123',
            'service_id' => $service->id,
            'customer_name' => 'Cliente de prueba',
            'phone' => '70000000',
            'status' => 'received',
            'payment_method' => 'cash',
            'requested_at' => now()->addHour(),
            'municipality' => 'San Miguel',
            'total' => 2.50,
            'public_token' => hash('sha256', Str::random(64)),
        ]);

        $this->post('/consultar', [
            'code' => 'ped-000123',
            'phone' => '70000000',
        ])->assertOk()
            ->assertSee('PED-000123')
            ->assertSee('Impresión')
            ->assertSee('$2.50');
    }

    public function test_lookup_shows_message_when_order_does_not_exist(): void
    {
        $this->post('/consultar', [
            'code' => 'PED-999999',
            'phone' => '70000000',
        ])->assertOk()
            ->assertSee('No encontramos un pedido con ese código.');
    }
}
