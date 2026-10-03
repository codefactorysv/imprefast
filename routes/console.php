<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('files:purge-expired')->dailyAt('02:00');
Schedule::command('orders:send-unreviewed-reminders')->everyMinute()->withoutOverlapping();

Artisan::command('telegram:set-webhook', function () {
    $token=(string)config('services.telegram.bot_token');$secret=(string)config('services.telegram.webhook_secret');$url=route('telegram.webhook');
    if(!$token||!$secret){$this->error('Configura TELEGRAM_BOT_TOKEN y TELEGRAM_WEBHOOK_SECRET.');return 1;}
    if(!str_starts_with($url,'https://')){$this->error('APP_URL debe ser una URL HTTPS pública para usar webhooks de Telegram.');return 1;}
    $ok=Http::timeout(10)->post("https://api.telegram.org/bot{$token}/setWebhook",['url'=>$url,'secret_token'=>$secret,'allowed_updates'=>['message']])->json('ok');
    $ok?$this->info('Webhook configurado correctamente.'):$this->error('Telegram no pudo configurar el webhook.'); return $ok?0:1;
})->purpose('Configura el webhook de Telegram sin mostrar el token');

Artisan::command('telegram:webhook-info', function () {
    $token=(string)config('services.telegram.bot_token');if(!$token){$this->error('Falta TELEGRAM_BOT_TOKEN.');return 1;}
    $result=Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo")->json('result',[]);
    $configured=filled($result['url']??null);$pending=(int)($result['pending_update_count']??0);
    $this->line('Webhook configurado: '.($configured?'Sí':'No'));
    $this->line('URL: '.($result['url']??'No configurada'));
    $this->line('Actualizaciones pendientes: '.$pending);
    $this->line('Errores pendientes: '.(($result['last_error_message']??null) ?: 'Ninguno'));
    if(isset($result['last_error_date']))$this->line('Fecha del último error: '.date('c',(int)$result['last_error_date']));
    $this->line('Certificado personalizado: '.(!empty($result['has_custom_certificate'])?'Sí':'No'));
    if(isset($result['ip_address']))$this->line('Dirección IP: '.$result['ip_address']);
    return 0;
})->purpose('Muestra el estado del webhook sin mostrar el token');

Artisan::command('telegram:delete-webhook', function () {
    $token=(string)config('services.telegram.bot_token');if(!$token){$this->error('Falta TELEGRAM_BOT_TOKEN.');return 1;}
    $ok=Http::timeout(10)->post("https://api.telegram.org/bot{$token}/deleteWebhook")->json('ok');$ok?$this->info('Webhook eliminado.'):$this->error('No se pudo eliminar el webhook.');return $ok?0:1;
})->purpose('Elimina el webhook sin mostrar el token');
