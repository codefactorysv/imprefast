<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; use Illuminate\Database\Eloquent\Factories\HasFactory;
class Service extends Model {
 use SoftDeletes, HasFactory; protected $guarded=[];
 protected $casts=['active'=>'boolean','pickup_return'=>'boolean','requires_file'=>'boolean','requires_pages'=>'boolean','requires_quantity'=>'boolean','requires_copies'=>'boolean','requires_topic'=>'boolean','requires_education_level'=>'boolean','allows_cover'=>'boolean','allows_images'=>'boolean','allows_stapling'=>'boolean','requires_output_format'=>'boolean','requires_pickup_instructions'=>'boolean','requires_deposit'=>'boolean','allows_letter'=>'boolean','allows_legal'=>'boolean','allows_duplex'=>'boolean','allows_urgent'=>'boolean','price_per_page'=>'decimal:2'];
 public function variants(){return $this->hasMany(ServiceVariant::class)->orderBy('sort_order');}
 public function priceTiers(){return $this->hasMany(ServicePriceTier::class)->orderBy('sort_order');}
 public function addons(){return $this->hasMany(ServiceAddon::class)->orderBy('sort_order');}
 public function orders(){return $this->hasMany(Order::class);}
}
