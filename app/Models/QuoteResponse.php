<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuoteResponse extends Model { protected $guarded=[]; protected $casts=['responded_at'=>'datetime']; }
