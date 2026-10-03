<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePricingModelsTest extends TestCase
{
    use RefreshDatabase;

    private function preview(string $model, float $price, array $extra = []): array
    {
        $service = Service::factory()->create(['pricing_model'=>$model,'calculation_type'=>$model==='manual'?'quote':'automatic','price_per_page'=>$price,'active'=>true]);
        return $this->postJson('/pedido/calcular', ['service_id'=>$service->id,'pages'=>2,'copies'=>3,'quantity'=>4] + $extra)->assertOk()->json();
    }

    public function test_page_unit_fixed_and_manual_models(): void
    {
        $this->assertEquals(3.0, $this->preview('per_page', .5)['work_subtotal']);
        $this->assertEquals(8.0, $this->preview('per_unit', 2)['work_subtotal']);
        $this->assertEquals(7.0, $this->preview('fixed', 7)['work_subtotal']);
        $manual = $this->preview('manual', 0);
        $this->assertTrue($manual['manual_quote']);
        $this->assertNull($manual['total']);
    }

    public function test_tier_variant_and_addon_prices_are_calculated_on_server(): void
    {
        $service = Service::factory()->create(['pricing_model'=>'tiered','active'=>true]);
        $service->priceTiers()->create(['minimum_quantity'=>50,'maximum_quantity'=>99,'price'=>8,'label'=>'50 tarjetas','active'=>true]);
        $variant = $service->variants()->create(['name'=>'Brillante','group_name'=>'Acabado','price_effect'=>'surcharge','price'=>.10,'active'=>true]);
        $addon = $service->addons()->create(['name'=>'Diseño','price_type'=>'fixed','price'=>5,'active'=>true]);
        $preview = $this->postJson('/pedido/calcular',['service_id'=>$service->id,'quantity'=>50,'variant_id'=>$variant->id,'addon_ids'=>[$addon->id]])->assertOk()->json();
        $this->assertEquals(18.0, $preview['work_subtotal']);
        $this->assertSame('Brillante', $preview['variant']['name']);
        $this->assertSame('Diseño', $preview['addons'][0]['name']);
    }
}
