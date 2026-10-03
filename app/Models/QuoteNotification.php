<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuoteNotification extends Model { protected $guarded=[]; protected $casts=['sent_at'=>'datetime']; public function quote(){return $this->belongsTo(Quote::class);} }
