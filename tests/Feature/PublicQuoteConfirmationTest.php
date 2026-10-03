<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Services\OrderPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PublicQuoteConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_preview_can_be_confirmed_without_changes_and_reuse_is_idempotent(): void
    {
        $service = $this->service();
        $preview = $this->preview($service, ['pages' => 5, 'copies' => 1]);
        $payload = $this->orderData($service, $preview['quote_token'], ['pages' => 5, 'copies' => 1]);

        $first = $this->post('/pedido', $payload);
        $order = Order::sole();
        $first->assertRedirect(route('orders.show', [$order->code, $order->public_token]));

        $this->post('/pedido', $payload)
            ->assertRedirect(route('orders.show', [$order->code, $order->public_token]));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_money_is_signed_in_cents_instead_of_float_representation(): void
    {
        $pricing = app(OrderPreviewService::class);
        $preview = $this->preview($this->service(['price_per_page' => .10]), ['pages' => 5, 'copies' => 1]);
        unset($preview['quote_token']);
        $same = $preview;
        $same['work_subtotal'] = 0.499999999;
        $same['total'] = (float) $preview['total'] + 0.000000001;

        $this->assertSame(
            $pricing->quoteSnapshot($preview)['price_fingerprint'],
            $pricing->quoteSnapshot($same)['price_fingerprint'],
        );
        $this->assertSame(50, $pricing->quoteSnapshot($same)['prices']['service_subtotal_cents']);
    }

    public function test_real_rate_change_requires_new_price_acceptance_with_old_and_new_totals(): void
    {
        $service = $this->service(['price_per_page' => .10]);
        $preview = $this->preview($service, ['pages' => 5, 'copies' => 1]);
        $service->update(['price_per_page' => .20]);

        $this->post('/pedido', $this->orderData($service, $preview['quote_token'], ['pages' => 5, 'copies' => 1]))
            ->assertSessionHasErrors(['price_accepted' => 'El precio fue actualizado de $2.00 a $2.50. Revisa el nuevo total antes de confirmar.']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_configuration_change_has_its_own_message(): void
    {
        $service = $this->service();
        $preview = $this->preview($service, ['pages' => 5, 'copies' => 1]);

        $this->post('/pedido', $this->orderData($service, $preview['quote_token'], ['pages' => 6, 'copies' => 1]))
            ->assertSessionHasErrors(['price_accepted' => 'La configuración del pedido cambió. Revisa el resumen actualizado antes de confirmar.']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_quote_has_specific_message(): void
    {
        $service = $this->service();
        $preview = $this->preview($service, ['pages' => 5, 'copies' => 1]);
        $key = 'order_preview.'.$preview['quote_token'];
        $quote = session()->get($key);
        $quote['expires_at'] = now()->subSecond()->timestamp;
        session()->put($key, $quote);

        $this->post('/pedido', $this->orderData($service, $preview['quote_token'], ['pages' => 5, 'copies' => 1]))
            ->assertSessionHasErrors(['price_accepted' => 'El tiempo para confirmar este precio terminó. Actualizamos la cotización para que puedas revisarla nuevamente.']);
    }

    public function test_validation_error_does_not_consume_quote_and_valid_retry_succeeds(): void
    {
        $service = $this->service();
        $preview = $this->preview($service, ['pages' => 5, 'copies' => 1]);
        $payload = $this->orderData($service, $preview['quote_token'], ['pages' => 5, 'copies' => 1]);

        $this->post('/pedido', array_merge($payload, ['customer_name' => '']))
            ->assertSessionHasErrors('customer_name');
        $this->assertNull(session('order_preview.'.$preview['quote_token'].'.used_order'));

        $this->post('/pedido', $payload)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertNotNull(session('order_preview.'.$preview['quote_token'].'.used_order'));
    }

    public function test_four_images_with_three_combined_confirm_without_false_configuration_change(): void
    {
        $service = $this->service(['requires_file' => true, 'requires_pages' => false, 'requires_copies' => false, 'price_per_page' => .50]);
        $quotedDetails = collect(range(1, 4))->map(fn ($number) => [
            'client_id' => "image-$number", 'name' => "foto$number.png", 'document_type' => 'image',
            'source_pages' => '1', 'copies' => '1', 'paper_size' => 'carta',
        ])->all();
        $confirmedDetails = $quotedDetails;
        foreach ([0, 1, 2] as $index) unset($confirmedDetails[$index]['copies']);
        $composition = [
            'combine_images' => '1', 'combined_file_ids' => ['image-1', 'image-2', 'image-3'],
            'images_per_page' => '3', 'combined_copies' => '1',
        ];
        $preview = $this->preview($service, ['file_details' => $quotedDetails] + $composition);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $response = $this->post('/pedido', $this->orderData($service, $preview['quote_token'], [
            'file_details' => $confirmedDetails,
            'files' => collect(range(1, 4))->map(fn ($number) => UploadedFile::fake()->createWithContent("foto$number.png", $png))->all(),
        ] + $composition));

        $response->assertRedirect();
        $order = Order::with(['files', 'imageComposition'])->sole();
        $this->assertCount(4, $order->files);
        $this->assertCount(3, $order->imageComposition->order_file_ids);
        $this->assertSame(100, (int) round((float) $order->work_subtotal * 100));
    }

    public function test_real_combined_copy_change_is_rejected_with_machine_readable_reason(): void
    {
        $service = $this->service(['requires_file' => true, 'requires_pages' => false, 'requires_copies' => false]);
        $details = [
            ['client_id' => 'a', 'name' => 'a.png', 'document_type' => 'image', 'source_pages' => 1],
            ['client_id' => 'b', 'name' => 'b.png', 'document_type' => 'image', 'source_pages' => 1],
        ];
        $configuration = ['file_details' => $details, 'combine_images' => 1, 'combined_file_ids' => ['a', 'b'], 'images_per_page' => 2, 'combined_copies' => 1];
        $preview = $this->preview($service, $configuration);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->postJson('/pedido', $this->orderData($service, $preview['quote_token'], array_merge($configuration, [
            'combined_copies' => 2,
            'files' => [
                UploadedFile::fake()->createWithContent('a.png', $png),
                UploadedFile::fake()->createWithContent('b.png', $png),
            ],
        ])))
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'configuration_changed')
            ->assertJsonPath('requires_new_acceptance', true)
            ->assertJsonFragment(['image_composition.combined_copies']);
    }

    private function service(array $overrides = []): Service
    {
        return Service::create(array_merge([
            'name' => 'Impresión segura', 'slug' => 'firma-'.uniqid(), 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .10,
            'requires_pages' => true, 'requires_copies' => true, 'allows_letter' => true, 'active' => true,
        ], $overrides));
    }

    private function preview(Service $service, array $configuration): array
    {
        return $this->postJson('/pedido/calcular', array_merge([
            'service_id' => $service->id,
            'latitude' => 13.483874, 'longitude' => -88.203702, 'delivery_confirmed' => 1,
        ], $configuration))->assertOk()->json();
    }

    private function orderData(Service $service, string $token, array $configuration): array
    {
        return array_merge([
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'requested_at' => now()->addDay()->format('Y-m-d H:i:s'), 'payment_method' => 'cash',
            'municipality' => 'San Miguel', 'latitude' => 13.483874, 'longitude' => -88.203702,
            'delivery_confirmed' => 1, 'confirmed' => 1, 'price_accepted' => 1, 'quote_token' => $token,
        ], $configuration);
    }
}
