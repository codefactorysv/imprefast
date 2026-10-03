<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Services\RouteCalculatorInterface;
use App\Services\RouteResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function automaticService(bool $pickup = false): Service
    {
        return Service::create([
            'name' => 'Impresión', 'slug' => 'preview-'.uniqid(), 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'price_per_page' => .10,
            'pickup_return' => $pickup, 'active' => true,
        ]);
    }

    public function test_server_returns_complete_automatic_price_breakdown(): void
    {
        $service = $this->automaticService();
        $json = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'pages' => 10, 'copies' => 2,
            'latitude' => 13.493874, 'longitude' => -88.203702, 'urgent' => true,
        ])->assertOk()->json();

        $this->assertEquals(2.0, $json['work_subtotal']);
        $this->assertEquals(1.0, $json['delivery']['urgent']);
        $this->assertFalse($json['manual_quote']);
        $this->assertEquals(round($json['work_subtotal'] + $json['delivery']['total'], 2), $json['total']);
    }

    public function test_pickup_factor_and_outside_radius_are_reported(): void
    {
        $pickup = $this->automaticService(true);
        $near = $this->postJson('/pedido/calcular', ['service_id' => $pickup->id, 'pages' => 1, 'copies' => 1, 'latitude' => 13.493874, 'longitude' => -88.203702])->assertOk()->json();
        $this->assertEquals(2.0, $near['delivery']['factor']);

        $far = $this->postJson('/pedido/calcular', ['service_id' => $pickup->id, 'pages' => 1, 'copies' => 1, 'latitude' => 13.683874, 'longitude' => -88.203702])->assertOk()->json();
        $this->assertTrue($far['manual_quote']);
        $this->assertSame('outside_radius', $far['reason']);
        $this->assertSame('outside_coverage', $far['calculation_status']);
        $this->assertFalse($far['service_requires_quote']);
        $this->assertTrue($far['delivery_requires_review']);
        $this->assertNull($far['total']);
    }

    public function test_custom_service_never_invents_work_price(): void
    {
        $service = Service::create(['name' => 'Digitación', 'slug' => 'quote-preview', 'description' => 'Prueba', 'calculation_type' => 'quote']);
        $json = $this->postJson('/pedido/calcular', ['service_id' => $service->id, 'latitude' => 13.493874, 'longitude' => -88.203702])->assertOk()->json();
        $this->assertTrue($json['manual_quote']);
        $this->assertNull($json['work_subtotal']);
        $this->assertNull($json['total']);
        $this->assertNotNull($json['delivery']['total']);
    }

    public function test_changed_data_requires_new_price_acceptance(): void
    {
        Storage::fake('local');
        $service = $this->automaticService();
        $token = $this->postJson('/pedido/calcular', ['service_id' => $service->id, 'pages' => 1, 'copies' => 1, 'latitude' => 13.493874, 'longitude' => -88.203702])->json('quote_token');

        $this->post('/pedido', [
            'service_id' => $service->id, 'pages' => 99, 'copies' => 1,
            'latitude' => 13.493874, 'longitude' => -88.203702,
            'customer_name' => 'Manipulación', 'phone' => '70000000',
            'requested_at' => now()->addDay(), 'payment_method' => 'cash',
            'municipality' => 'San Miguel', 'confirmed' => 1, 'price_accepted' => 1,
            'quote_token' => $token,
            'files' => [UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('price_accepted');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_route_provider_failure_falls_back_to_manual_quote(): void
    {
        $this->app->bind(RouteCalculatorInterface::class, fn () => new class implements RouteCalculatorInterface {
            public function calculate(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): RouteResult
            {
                throw new \RuntimeException('Proveedor fuera de servicio');
            }
        });

        $service = $this->automaticService();
        $json = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'pages' => 1, 'copies' => 1,
            'latitude' => 13.493874, 'longitude' => -88.203702,
        ])->assertOk()->json();

        $this->assertTrue($json['manual_quote']);
        $this->assertSame('route_error', $json['reason']);
        $this->assertSame('route_provider_error', $json['calculation_status']);
        $this->assertFalse($json['service_requires_quote']);
        $this->assertTrue($json['delivery_requires_review']);
        $this->assertEquals(.10, $json['work_subtotal']);
        $this->assertNull($json['total']);
    }

    public function test_missing_coordinates_have_a_specific_status(): void
    {
        $service = $this->automaticService();
        $json = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'pages' => 1, 'copies' => 1,
            'delivery_confirmed' => false,
        ])->assertOk()->json();

        $this->assertSame('missing_coordinates', $json['calculation_status']);
        $this->assertFalse($json['service_requires_quote']);
        $this->assertTrue($json['delivery_requires_review']);
    }

    public function test_application_uses_el_salvador_timezone(): void
    {
        $this->assertSame('America/El_Salvador', config('app.timezone'));
    }
}
