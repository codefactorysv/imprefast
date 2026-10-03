<?php

namespace Tests\Feature;

use App\Models\{Order,Service,User};
use App\Services\OrderCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecureQuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $service=Service::create(['name'=>'Diseño','slug'=>'diseno-test','description'=>'Manual','calculation_type'=>'quote','pricing_model'=>'manual','active'=>true]);
        return Order::create(['code'=>'PED-K7M4Q9','service_id'=>$service->id,'customer_name'=>'Nombre privado','phone'=>'61234567','email'=>'privado@example.test','status'=>'pending_quote','requires_quote'=>true,'payment_method'=>'cash','requested_at'=>now()->addDay(),'municipality'=>'San Miguel','street'=>'Dirección privada','delivery_fee'=>2.50,'distance_km'=>3.2,'public_token'=>hash('sha256','secure')]);
    }

    public function test_codes_are_random_and_exclude_confusing_characters(): void
    {
        $code=app(OrderCodeGenerator::class)->generate();
        $this->assertMatchesRegularExpression('/^PED-[A-HJ-KM-NP-Z2-9]{6}$/',$code);
    }

    public function test_lookup_accepts_lowercase_and_does_not_expose_private_data(): void
    {
        $this->order();
        $this->post('/consultar',['code'=>' ped-k7m4q9 '])->assertOk()->assertSee('PED-K7M4Q9')->assertDontSee('Nombre privado')->assertDontSee('61234567')->assertDontSee('privado@example.test')->assertDontSee('Dirección privada');
    }

    public function test_admin_quote_uses_read_only_delivery_and_creates_version_notification(): void
    {
        $order=$this->order(); $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->get(route('admin.orders.show',$order))->assertOk()->assertDontSee('name="delivery_price"',false)->assertSee('Domicilio calculado');
        $this->post(route('admin.orders.quote',$order),['work_price'=>10,'addons_total'=>99,'deposit_required'=>0,'notes'=>'Trabajo personalizado'])->assertRedirect();
        $order->refresh(); $quote=$order->quotes()->first();
        $this->assertSame('pending_quote',$order->status);
        $this->assertSame('sent',$quote->status);
        $this->assertSame('12.50',$quote->total);
        $this->assertDatabaseHas('quote_notifications',['quote_id'=>$quote->id,'status'=>'pending']);
        $this->assertStringContainsString('https://wa.me/50361234567',$quote->notification->url);
    }

    public function test_automatic_order_cannot_be_quoted_by_admin(): void
    {
        $order = $this->order();
        $order->update(['requires_quote' => false, 'status' => 'received']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.orders.quote', $order), ['work_price' => 10])
            ->assertNotFound();

        $this->assertDatabaseCount('quotes', 0);
    }
}
