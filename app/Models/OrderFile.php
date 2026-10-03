<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFile extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'double_sided' => 'boolean', 'stapled' => 'boolean', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
