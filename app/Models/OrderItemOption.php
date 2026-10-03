<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class OrderItemOption extends Model { protected $guarded=[]; protected $casts=['snapshot'=>'array','price'=>'decimal:2']; }
