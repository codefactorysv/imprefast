<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model { protected $guarded=[]; protected $casts=['snapshot'=>'array','unit_price'=>'decimal:2','subtotal'=>'decimal:2']; public function options(){return $this->hasMany(OrderItemOption::class);} }
