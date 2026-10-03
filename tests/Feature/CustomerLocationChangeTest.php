<?php

namespace Tests\Feature;

use App\Models\{Order,Service};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLocationChangeTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status='pending_quote'): Order
    {
        $service=Service::create(['name'=>'Impresión','slug'=>'location-change-'.$status,'description'=>'Prueba','calculation_type'=>'automatic','pricing_model'=>'fixed','price_per_page'=>5,'active'=>true]);
        return Order::create(['code'=>'PED-K7M4Q9','service_id'=>$service->id,'customer_name'=>'Cliente','phone'=>'61234567','status'=>$status,'payment_method'=>'cash','requested_at'=>now()->addDay(),'department'=>'San Miguel','municipality'=>'San Miguel','street'=>'Calle anterior','latitude'=>13.483874,'longitude'=>-88.203702,'distance_km'=>1,'delivery_fee'=>1.50,'work_subtotal'=>5,'total'=>6.50,'public_token'=>hash('sha256','private-location-link')]);
    }

    private function payload(): array
    {
        return ['latitude'=>13.493874,'longitude'=>-88.203702,'department'=>'San Miguel','municipality'=>'San Miguel','neighborhood'=>'Centro','street'=>'Calle nueva','house_number'=>'10','address_reference'=>'Frente a tienda','customer_message'=>'Moví el punto','location_confirmed'=>'1'];
    }

    public function test_private_token_is_required_and_confirmation_is_mandatory(): void
    {
        $order=$this->order();
        $this->get("/pedido/{$order->code}/token-incorrecto/ubicacion")->assertNotFound();
        $this->post(route('orders.location.update',[$order->code,$order->public_token]),array_diff_key($this->payload(),['location_confirmed'=>true]))->assertSessionHasErrors('location_confirmed');
        $this->assertDatabaseCount('order_location_changes',0);
    }

    public function test_customer_direct_change_is_recalculated_and_notifies_admin(): void
    {
        $order=$this->order();
        $this->get(route('orders.location.edit',[$order->code,$order->public_token]))->assertOk()->assertSee('location-map')->assertSee('Guardar nueva ubicación');
        $this->post(route('orders.location.update',[$order->code,$order->public_token]),$this->payload())->assertRedirect();
        $order->refresh();
        $this->assertSame('Calle nueva',$order->street);
        $this->assertNotNull($order->route_calculated_at);
        $this->assertDatabaseHas('order_location_changes',['order_id'=>$order->id,'status'=>'applied','direct'=>true,'customer_message'=>'Moví el punto']);
    }

    public function test_received_order_creates_pending_request_without_overwriting_location(): void
    {
        $order=$this->order('received');
        $this->post(route('orders.location.update',[$order->code,$order->public_token]),$this->payload())->assertRedirect();
        $this->assertSame('Calle anterior',$order->fresh()->street);
        $this->assertDatabaseHas('order_location_changes',['order_id'=>$order->id,'status'=>'pending','direct'=>false]);
    }

    public function test_on_the_way_order_is_blocked(): void
    {
        $order=$this->order('on_the_way');
        $this->get(route('orders.location.edit',[$order->code,$order->public_token]))->assertOk()->assertSee('El pedido ya está en camino')->assertSee('Contactar por WhatsApp');
        $this->post(route('orders.location.update',[$order->code,$order->public_token]),$this->payload())->assertStatus(422);
    }
}
