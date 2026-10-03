<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicOrderRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_limit_is_independent_and_real_abuse_remains_blocked(): void
    {
        $service = $this->automaticService();
        $payload = ['service_id' => $service->id, 'pages' => 1, 'copies' => 1, 'submission_token' => (string) Str::uuid()];

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postJson('/pedido/calcular', $payload)->assertOk();
        }
        $this->postJson('/pedido/calcular', $payload)
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Se hicieron varios intentos en poco tiempo. Espera unos segundos y vuelve a intentarlo.')
            ->assertJsonStructure(['retry_after']);

        // Exhausting previews must not consume the creation counter.
        $this->postJson('/pedido', [])->assertUnprocessable();
    }

    public function test_creation_limit_returns_non_empty_spanish_json(): void
    {
        $submissionToken = (string) Str::uuid();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/pedido', ['submission_token' => $submissionToken])->assertUnprocessable();
        }

        $this->postJson('/pedido', ['submission_token' => $submissionToken])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Se hicieron varios intentos en poco tiempo. Espera unos segundos y vuelve a intentarlo.')
            ->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_school_work_can_be_requested_without_accepting_a_nonexistent_price(): void
    {
        $service = Service::create([
            'name' => 'Investigación escolar', 'slug' => 'investigacion-rate-test', 'description' => 'Prueba',
            'calculation_type' => 'quote', 'pricing_model' => 'manual', 'requires_topic' => true,
            'active' => true,
        ]);
        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'topic' => 'Historia de El Salvador',
            'latitude' => 13.483874, 'longitude' => -88.203702, 'delivery_confirmed' => 1,
        ])->assertOk()->json();
        $submissionToken = (string) Str::uuid();
        $payload = [
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'topic' => 'Historia de El Salvador', 'requested_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'cash', 'municipality' => 'San Miguel', 'latitude' => 13.483874,
            'longitude' => -88.203702, 'delivery_confirmed' => 1, 'confirmed' => 1,
            'quote_requested' => 1, 'quote_token' => $preview['quote_token'],
            'submission_token' => $submissionToken,
        ];

        $this->post('/pedido', $payload)->assertRedirect();
        $order = Order::sole();
        $this->assertSame('pending_quote', $order->status);
        $this->assertNull($order->total);
        $this->assertNotNull($order->delivery_fee);

        $this->post('/pedido', $payload)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_form_registers_one_submit_handler_and_handles_429_recovery(): void
    {
        $content = $this->get('/pedido')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, "form.addEventListener('submit'"));
        $this->assertStringContainsString('|| submitting', $content);
        $this->assertStringContainsString('response.status === 429', $content);
        $this->assertStringContainsString('finally', $content);
        $this->assertStringContainsString('Los archivos seleccionados se conservaron', $content);
        $this->assertStringContainsString('He revisado los datos y deseo enviar esta solicitud', $content);
        $this->assertStringNotContainsString("'topic', 'education_level'", $content);
    }

    private function automaticService(): Service
    {
        return Service::create([
            'name' => 'Impresión', 'slug' => 'automatic-rate-test', 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .10,
            'requires_pages' => true, 'requires_copies' => true, 'active' => true,
        ]);
    }
}
