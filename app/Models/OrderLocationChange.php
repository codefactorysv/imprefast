<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLocationChange extends Model
{
    protected $guarded = [];
    protected $casts = ['direct'=>'boolean','old_location'=>'array','new_location'=>'array','rate_snapshot'=>'array','reviewed_at'=>'datetime','admin_seen_at'=>'datetime'];
    public function order(){return $this->belongsTo(Order::class);}
    public function quote(){return $this->belongsTo(Quote::class);}
}
