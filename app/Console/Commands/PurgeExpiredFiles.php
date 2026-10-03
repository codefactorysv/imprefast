<?php
namespace App\Console\Commands; use App\Models\OrderFile; use Illuminate\Console\Command; use Illuminate\Support\Facades\Storage;
class PurgeExpiredFiles extends Command { protected $signature='files:purge-expired'; protected $description='Elimina archivos privados vencidos'; public function handle(): int { OrderFile::where('expires_at','<',now())->chunkById(100,function($files){foreach($files as $f){Storage::disk($f->disk)->delete($f->path);$f->delete();}}); return self::SUCCESS; } }
