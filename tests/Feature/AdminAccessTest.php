<?php
namespace Tests\Feature; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class AdminAccessTest extends TestCase { use RefreshDatabase; public function test_guest_cannot_open_admin():void{$this->get('/admin')->assertRedirect('/admin/login');} public function test_admin_can_open_dashboard():void{$u=User::factory()->create(['is_admin'=>true]);$this->actingAs($u)->get('/admin')->assertOk();} }
