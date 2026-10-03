<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Setting extends Model { protected $guarded=[]; public static function value(string $key, mixed $default=null): mixed { return cache()->remember("setting.$key",60,fn()=>static::where('key',$key)->value('value') ?? $default); } }
