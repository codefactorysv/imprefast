<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $guarded = [];
    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'read_at' => 'datetime'];
    public function order() { return $this->belongsTo(Order::class); }
}
