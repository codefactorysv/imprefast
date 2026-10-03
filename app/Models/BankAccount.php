<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class BankAccount extends Model { protected $guarded=[]; protected $casts=['active'=>'boolean']; }
