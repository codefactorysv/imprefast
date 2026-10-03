<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServicesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_only_an_authenticated_admin_can_manage_services(): void
    {
        $this->get('/admin/servicios')->assertRedirect('/admin/login');
        $this->actingAs($this->admin())->get('/admin/servicios')->assertOk()->assertSee('Servicios y precios');
    }

    public function test_admin_can_create_update_and_deactivate_a_service(): void
    {
        $admin = $this->admin();
        $data = ['name'=>'Plastificado','slug'=>'plastificado','description'=>'Protección','pricing_model'=>'per_unit','price_per_page'=>'1.50','billing_unit'=>'unidad','sort_order'=>3,'requires_quantity'=>'1','active'=>'1','variants'=>[['name'=>'Carta','group_name'=>'Tamaño','price_effect'=>'surcharge','price'=>'0.25']],'addons'=>[['name'=>'Corte','description'=>'Corte final','price_type'=>'fixed','price'=>'0.50']]];
        $this->actingAs($admin)->post(route('admin.services.store'), $data)->assertRedirect();
        $service = Service::whereSlug('plastificado')->firstOrFail();
        $this->assertDatabaseHas('service_variants', ['service_id'=>$service->id,'name'=>'Carta']);
        $this->assertDatabaseHas('service_addons', ['service_id'=>$service->id,'name'=>'Corte']);
        $this->actingAs($admin)->put(route('admin.services.update',$service), array_merge($data, ['name'=>'Plastificado premium']))->assertRedirect();
        $this->assertSame('Plastificado premium', $service->fresh()->name);
        $this->actingAs($admin)->patch(route('admin.services.toggle',$service))->assertRedirect();
        $this->assertFalse($service->fresh()->active);
    }

    public function test_inactive_service_is_hidden_and_service_with_orders_is_not_deleted(): void
    {
        $service = Service::factory()->create(['active'=>false]);
        $this->get('/pedido')->assertDontSee($service->name);
        $service->update(['active'=>true]);
        Order::create(['service_id'=>$service->id,'customer_name'=>'Cliente','phone'=>'70000000','status'=>'received','payment_method'=>'cash','requested_at'=>now()->addDay(),'municipality'=>'San Miguel','public_token'=>hash('sha256','test-token')]);
        $this->actingAs($this->admin())->delete(route('admin.services.destroy',$service))->assertRedirect();
        $this->assertDatabaseHas('services',['id'=>$service->id,'active'=>false,'deleted_at'=>null]);
    }
}
