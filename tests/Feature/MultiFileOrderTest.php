<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiFileOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_images_use_only_per_file_copies_and_ignore_units(): void
    {
        Storage::fake('local');
        $service = Service::create([
            'name' => 'Impresión', 'slug' => 'cuatro-imagenes', 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .10,
            'requires_file' => true, 'requires_copies' => true, 'requires_quantity' => true,
            'allows_letter' => true, 'active' => true,
        ]);
        $oneCopy = collect(range(1, 4))->map(fn ($number) => [
            'client_id' => "file-{$number}", 'name' => "foto{$number}.png", 'document_type' => 'image', 'source_pages' => 1,
            'copies' => 1, 'paper_size' => 'carta',
        ])->all();

        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $oneCopy, 'quantity' => 999,
            'latitude' => 13.483874, 'longitude' => -88.203702,
        ])->assertOk()->json();
        $this->assertSame(4, $preview['pages']);
        $this->assertSame(4, $preview['print_units']);
        $this->assertEquals(.40, $preview['work_subtotal']);
        $this->assertNull($preview['quantity']);

        $threeCopies = collect($oneCopy)->map(fn ($detail) => array_merge($detail, ['copies' => 3]))->all();
        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $threeCopies, 'quantity' => 1,
            'latitude' => 13.483874, 'longitude' => -88.203702,
        ])->assertOk()->json();
        $this->assertSame(12, $preview['print_units']);
        $this->assertEquals(1.20, $preview['work_subtotal']);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $response = $this->post('/pedido', [
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'requested_at' => now()->addDay(), 'payment_method' => 'cash', 'municipality' => 'San Miguel',
            'latitude' => 13.483874, 'longitude' => -88.203702, 'confirmed' => 1, 'price_accepted' => 1,
            'quote_token' => $preview['quote_token'], 'file_details' => $threeCopies,
            'files' => collect(range(1, 4))->map(fn ($i) => UploadedFile::fake()->createWithContent("foto{$i}.png", $png))->all(),
        ]);

        $response->assertRedirect();
        $this->assertCount(4, Order::with('files')->sole()->files);

        $this->get('/pedido')->assertOk()->assertDontSee('¿Cuántas unidades necesitas?');
    }

    public function test_image_and_four_page_pdf_are_priced_and_stored_per_file(): void
    {
        Storage::fake('local');
        $service = Service::create([
            'name' => 'Impresión múltiple', 'slug' => 'impresion-multiple', 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .10,
            'requires_file' => true, 'requires_pages' => true, 'requires_copies' => true,
            'allows_letter' => true, 'allows_legal' => true, 'allows_duplex' => true, 'allows_stapling' => true,
            'active' => true,
        ]);
        $details = [
            ['client_id' => 'image-1', 'name' => 'foto.png', 'document_type' => 'image', 'source_pages' => 1, 'copies' => 2, 'paper_size' => 'carta'],
            ['client_id' => 'pdf-1', 'name' => 'documento.pdf', 'document_type' => 'pdf', 'source_pages' => 4, 'copies' => 1, 'paper_size' => 'carta'],
        ];
        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $details,
            'latitude' => 13.483874, 'longitude' => -88.203702,
        ])->assertOk()->json();

        $this->assertSame(5, $preview['pages']);
        $this->assertSame(6, $preview['print_units']);
        $this->assertEquals(.60, $preview['work_subtotal']);

        $response = $this->post('/pedido', [
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'requested_at' => now()->addDay()->format('Y-m-d H:i:s'), 'payment_method' => 'cash',
            'municipality' => 'San Miguel', 'latitude' => 13.483874, 'longitude' => -88.203702,
            'confirmed' => 1, 'price_accepted' => 1, 'quote_token' => $preview['quote_token'],
            'file_details' => $details,
            'files' => [
                UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
                UploadedFile::fake()->createWithContent('documento.pdf', "%PDF-1.4\n/Type /Page\n/Type /Page\n/Type /Page\n/Type /Page\n%%EOF"),
            ],
        ]);

        $order = Order::with('files')->sole();
        $response->assertRedirect();
        $this->assertCount(2, $order->files);
        $this->assertSame(5, $order->files->sum('source_pages'));
        $this->assertSame(6, $order->files->sum('print_units'));
        $this->assertEqualsWithDelta(.60, $order->files->sum('subtotal'), .001);
        $this->assertDatabaseHas('order_files', ['original_name' => 'foto.png', 'source_pages' => 1, 'copies' => 2, 'print_units' => 2]);
        $this->assertDatabaseHas('order_files', ['original_name' => 'documento.pdf', 'source_pages' => 4, 'copies' => 1, 'print_units' => 4]);
    }

    public function test_server_rejects_manipulated_pdf_page_count(): void
    {
        $service = Service::create(['name' => 'Impresión', 'slug' => 'segura', 'description' => 'Prueba', 'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .10, 'requires_file' => true, 'allows_letter' => true, 'active' => true]);
        $details = [['client_id' => 'pdf-secure', 'name' => 'documento.pdf', 'document_type' => 'pdf', 'source_pages' => 1, 'copies' => 1, 'paper_size' => 'carta']];
        $token = $this->postJson('/pedido/calcular', ['service_id' => $service->id, 'file_details' => $details])->json('quote_token');

        $this->post('/pedido', [
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'requested_at' => now()->addDay(), 'payment_method' => 'cash', 'municipality' => 'San Miguel',
            'confirmed' => 1, 'price_accepted' => 1, 'quote_token' => $token, 'file_details' => $details,
            'files' => [UploadedFile::fake()->createWithContent('documento.pdf', "%PDF-1.4\n/Type /Page\n/Type /Page\n%%EOF")],
        ])->assertSessionHasErrors('price_accepted');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_upload_limits_and_types_have_spanish_messages_without_creating_an_order(): void
    {
        $service = $this->uploadService();
        $base = $this->minimumOrderData($service);

        $this->post('/pedido', $base + [
            'files' => collect(range(1, 11))->map(fn ($i) => UploadedFile::fake()->create("foto{$i}.png", 1, 'image/png'))->all(),
        ])->assertSessionHasErrors(['files' => 'Puedes subir un máximo de 10 archivos por pedido.']);

        $this->post('/pedido', $base + [
            'files' => [UploadedFile::fake()->create('grande.png', 10241, 'image/png')],
        ])->assertSessionHasErrors('files.0');

        $this->post('/pedido', $base + [
            'files' => [UploadedFile::fake()->create('programa.exe', 1, 'application/octet-stream')],
        ])->assertSessionHasErrors('files.0');

        $this->post('/pedido', $base + [
            'files' => collect(range(1, 6))->map(fn ($i) => UploadedFile::fake()->create("archivo{$i}.pdf", 9 * 1024, 'application/pdf'))->all(),
        ])->assertSessionHasErrors(['files' => 'El conjunto de archivos supera el máximo permitido de 50 MB.']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_php_upload_error_is_translated_without_exposing_the_technical_index(): void
    {
        $service = $this->uploadService();
        $path = tempnam(sys_get_temp_dir(), 'failed-upload');
        $failed = new UploadedFile($path, 'grande.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

        try {
            $this->post('/pedido', $this->minimumOrderData($service) + ['files' => [$failed]])
                ->assertSessionHasErrors(['files' => 'El primer archivo supera el tamaño máximo permitido de 10 MB.']);
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_combined_images_are_billed_by_resulting_pages_without_double_charge(): void
    {
        $service = Service::create([
            'name' => 'Impresión color', 'slug' => 'composicion-color', 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .50,
            'requires_file' => true, 'allows_letter' => true, 'active' => true,
        ]);
        $details = collect(range(1, 5))->map(fn ($i) => [
            'client_id' => "image-{$i}", 'name' => "foto{$i}.png", 'document_type' => 'image', 'source_pages' => 1,
            'copies' => 1, 'paper_size' => 'carta',
        ])->all();

        $two = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => array_slice($details, 0, 2),
            'combine_images' => 1, 'combined_file_ids' => ['image-1', 'image-2'], 'images_per_page' => 2, 'combined_copies' => 3,
        ])->assertOk()->json();
        $this->assertSame(1, $two['image_composition']['resulting_pages']);
        $this->assertSame(3, $two['image_composition']['billable_pages']);
        $this->assertSame(3, $two['print_units']);
        $this->assertEquals(1.50, $two['work_subtotal']);
        $this->assertSame(0, $two['file_details'][0]['print_units']);

        $three = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => array_slice($details, 0, 3),
            'combine_images' => 1, 'combined_file_ids' => ['image-1', 'image-2', 'image-3'], 'images_per_page' => 3, 'combined_copies' => 1,
        ])->assertOk()->json();
        $this->assertSame(1, $three['image_composition']['resulting_pages']);
        $this->assertSame(1, $three['print_units']);
        $this->assertEquals(.50, $three['work_subtotal']);

        $five = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $details,
            'combine_images' => 1, 'combined_file_ids' => ['image-1', 'image-2', 'image-3', 'image-4', 'image-5'], 'images_per_page' => 3, 'combined_copies' => 1,
        ])->assertOk()->json();
        $this->assertSame(2, $five['image_composition']['resulting_pages']);
        $this->assertSame(2, $five['print_units']);
        $this->assertSame([['foto1.png', 'foto2.png', 'foto3.png'], ['foto4.png', 'foto5.png']], $five['image_composition']['page_groups']);

        $mixedDetails = array_slice($details, 0, 4);
        $mixedDetails[3]['copies'] = 2;
        $mixed = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $mixedDetails,
            'combine_images' => 1, 'combined_file_ids' => ['image-1', 'image-2', 'image-3'], 'images_per_page' => 2, 'combined_copies' => 1,
        ])->assertOk()->json();
        $this->assertSame(4, $mixed['print_units']);
        $this->assertEquals(2.0, $mixed['work_subtotal']);
        $this->assertSame(2, $mixed['file_details'][3]['print_units']);

        $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => array_slice($details, 0, 3),
            'combine_images' => 1, 'combined_file_ids' => ['image-1', 'image-2', 'image-3'], 'images_per_page' => 4, 'combined_copies' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('images_per_page');
    }

    public function test_pdf_cannot_be_selected_for_image_composition(): void
    {
        $service = $this->uploadService();
        $this->postJson('/pedido/calcular', [
            'service_id' => $service->id,
            'file_details' => [
                ['client_id' => 'image-pdf-test', 'name' => 'foto.png', 'document_type' => 'image', 'source_pages' => 1, 'copies' => 1],
                ['client_id' => 'pdf-image-test', 'name' => 'documento.pdf', 'document_type' => 'pdf', 'source_pages' => 1, 'copies' => 1],
            ],
            'combine_images' => 1, 'combined_file_ids' => ['image-pdf-test', 'pdf-image-test'], 'images_per_page' => 2, 'combined_copies' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('combined_file_ids');
    }

    public function test_copies_are_required_only_for_files_printed_individually(): void
    {
        $service = $this->uploadService();
        $individual = [[
            'client_id' => 'individual-1', 'name' => 'individual.png', 'document_type' => 'image',
            'source_pages' => 1, 'paper_size' => 'carta',
        ]];
        $this->postJson('/pedido/calcular', ['service_id' => $service->id, 'file_details' => $individual])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file_details')
            ->assertJsonFragment(['Indica cuántas copias deseas del archivo «individual.png». ']);

        $combined = [
            ['client_id' => 'combined-a', 'name' => 'a.png', 'document_type' => 'image', 'source_pages' => 1],
            ['client_id' => 'combined-b', 'name' => 'b.png', 'document_type' => 'image', 'source_pages' => 1],
            ['client_id' => 'outside', 'name' => 'fuera.png', 'document_type' => 'image', 'source_pages' => 1, 'copies' => 1],
        ];
        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $combined, 'combine_images' => 1,
            'combined_file_ids' => ['combined-b', 'combined-a'], 'images_per_page' => 2, 'combined_copies' => 1,
        ])->assertOk()->json();

        $this->assertNull($preview['file_details'][0]['copies']);
        $this->assertNull($preview['file_details'][1]['copies']);
        $this->assertSame(0, $preview['file_details'][0]['print_units']);
        $this->assertSame(2, $preview['print_units']);
        $this->assertSame(['combined-b', 'combined-a'], $preview['image_composition']['file_ids']);
    }

    public function test_combined_configuration_is_recalculated_and_persisted_with_real_order_file_ids(): void
    {
        Storage::fake('local');
        $service = Service::create([
            'name' => 'Composición persistida', 'slug' => 'composicion-persistida', 'description' => 'Prueba',
            'calculation_type' => 'automatic', 'pricing_model' => 'per_page', 'price_per_page' => .50,
            'requires_file' => true, 'allows_letter' => true, 'active' => true,
        ]);
        $details = collect(range(1, 2))->map(fn ($i) => [
            'client_id' => "persisted-{$i}", 'name' => "foto{$i}.png", 'document_type' => 'image', 'source_pages' => 1,
            'paper_size' => 'carta',
        ])->all();
        $composition = [
            'combine_images' => 1, 'combined_file_ids' => ['persisted-2', 'persisted-1'], 'images_per_page' => 2,
            'combined_copies' => 3, 'combined_instructions' => 'Colocarlas lado a lado.',
        ];
        $preview = $this->postJson('/pedido/calcular', [
            'service_id' => $service->id, 'file_details' => $details,
            'latitude' => 13.483874, 'longitude' => -88.203702, 'delivery_confirmed' => 1,
        ] + $composition)->assertOk()->json();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->post('/pedido', [
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'requested_at' => now()->addDay(), 'payment_method' => 'cash', 'municipality' => 'San Miguel',
            'latitude' => 13.483874, 'longitude' => -88.203702, 'delivery_confirmed' => 1,
            'confirmed' => 1, 'price_accepted' => 1, 'quote_token' => $preview['quote_token'],
            'file_details' => $details, 'subtotal' => 99999,
            'files' => [
                UploadedFile::fake()->createWithContent('foto1.png', $png),
                UploadedFile::fake()->createWithContent('foto2.png', $png),
            ],
        ] + $composition)->assertRedirect();

        $order = Order::with(['files', 'imageComposition'])->sole();
        $this->assertSame(3, $order->imageComposition->billable_pages);
        $this->assertEquals(1.50, $order->imageComposition->subtotal);
        $this->assertEquals(1.50, $order->work_subtotal);
        $this->assertSame($order->files->pluck('id')->reverse()->values()->all(), $order->imageComposition->order_file_ids);
    }

    private function uploadService(): Service
    {
        return Service::create([
            'name' => 'Carga segura', 'slug' => 'carga-segura-'.uniqid(), 'description' => 'Prueba',
            'calculation_type' => 'manual_quote', 'pricing_model' => 'fixed', 'price_per_page' => 1,
            'requires_file' => true, 'allows_letter' => true, 'active' => true,
        ]);
    }

    private function minimumOrderData(Service $service): array
    {
        return [
            'service_id' => $service->id, 'customer_name' => 'Cliente', 'phone' => '70000000',
            'requested_at' => now()->addDay(), 'payment_method' => 'cash', 'municipality' => 'San Miguel',
            'confirmed' => 1, 'price_accepted' => 1, 'quote_token' => str_repeat('a', 64),
        ];
    }
}
