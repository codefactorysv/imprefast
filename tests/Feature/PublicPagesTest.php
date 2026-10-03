<?php
namespace Tests\Feature; use App\Models\Service; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class PublicPagesTest extends TestCase { use RefreshDatabase; public function test_home_and_order_form_work():void{Service::create(['name'=>'Impresión','slug'=>'impresion','description'=>'Prueba','calculation_type'=>'automatic','price_per_page'=>.10]);$this->get('/')->assertOk()->assertSee('Envíanos tu archivo');$this->get('/pedido')->assertOk()->assertSee('Impresión');} }
