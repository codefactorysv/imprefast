<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderImageComposition extends Model
{
    protected $guarded = [];

    protected $casts = [
        'order_file_ids' => 'array',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
