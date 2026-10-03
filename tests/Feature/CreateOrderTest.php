<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_order_with_review_confirmation(): void
    {
        Storage::fake('local');

        $service = Service::create([
            'name' => 'Impresión en blanco y negro',
            'slug' => 'impresion-create-test',
            'description' => 'Impresión de prueba',
            'calculation_type' => 'automatic',
            'price_per_page' => 0.10,
            'active' => true,
        ]);

        $payload = [
            'service_id' => $service->id,
            'customer_name' => 'María López',
            'phone' => '70000000',
            'email' => 'maria@example.test',
            'pages' => 2,
            'copies' => 3,
            'paper_size' => 'carta',
            'orientation' => 'vertical',
            'requested_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'cash',
            'department' => 'San Miguel',
            'municipality' => 'San Miguel',
            'neighborhood' => 'Centro',
            'latitude' => 13.483874,
            'longitude' => -88.203702,
            'confirmed' => '1',
            'files' => [
                UploadedFile::fake()->create('tarea.pdf', 20, 'application/pdf'),
            ],
        ];

        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id,
            'pages' => 2,
            'copies' => 3,
            'paper_size' => 'carta',
            'orientation' => 'vertical',
            'latitude' => 13.483874,
            'longitude' => -88.203702,
        ])->assertOk()->json();

        $response = $this->post('/pedido', $payload + [
            'quote_token' => $preview['quote_token'],
            'price_accepted' => '1',
        ]);

        $order = Order::with(['files', 'items'])->sole();

        $response->assertRedirect(route('orders.show', [$order->code, $order->public_token]));
        $this->get(route('orders.show', [$order->code, $order->public_token]))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Recibimos tu solicitud');
        $this->assertMatchesRegularExpression('/^PED-[A-HJ-KM-NP-Z2-9]{6}$/', $order->code);
        $this->assertSame('received', $order->status);
        $this->assertSame('70000000', $order->phone);
        $this->assertCount(1, $order->files);
        $this->assertCount(1, $order->items);
        $historicalSubtotal = $order->items->first()->subtotal;
        $service->update(['price_per_page' => 99]);
        $this->assertSame($historicalSubtotal, $order->items()->first()->subtotal);
        Storage::disk('local')->assertExists($order->files->first()->path);

        $this->post('/consultar', [
            'code' => $order->code,
            'phone' => '70000000',
        ])->assertOk()->assertSee($order->code);
    }
}
