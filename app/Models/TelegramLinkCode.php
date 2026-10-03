<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramLinkCode extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
}
