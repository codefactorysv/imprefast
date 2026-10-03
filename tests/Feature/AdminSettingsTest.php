<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_can_open_and_persist_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Setting::create(['key' => 'business_name', 'value' => 'Nombre actual']);
        Setting::create(['key' => 'delivery_base_fee', 'value' => '1.00']);

        $this->actingAs($admin)->get('/admin/configuracion')
            ->assertOk()
            ->assertSee('Nombre actual')
            ->assertSee('delivery_base_fee');

        $this->actingAs($admin)->post('/admin/configuracion', [
            'settings' => [
                'business_name' => 'Nombre actualizado',
                'delivery_base_fee' => '1.25',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'business_name', 'value' => 'Nombre actualizado']);
        $this->assertDatabaseHas('settings', ['key' => 'delivery_base_fee', 'value' => '1.25']);

        $this->actingAs($admin)->get('/admin/configuracion')
            ->assertOk()
            ->assertSee('Nombre actualizado')
            ->assertSee('1.25');

        $this->assertSame(2, Setting::count());
    }
}
