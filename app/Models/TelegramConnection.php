<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramConnection extends Model
{
    protected $guarded = [];
    protected $casts = ['connected_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
