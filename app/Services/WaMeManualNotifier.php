<?php
namespace App\Services;
use App\Models\{Order,Quote,QuoteNotification};
class WaMeManualNotifier implements WhatsAppNotifierInterface {
 public function prepareQuoteReady(Order $order,Quote $quote):QuoteNotification {
  $url=route('quotes.show',$quote->token); $message="Hola, tu cotización está lista.\n\nPedido: {$order->code}\nServicio: {$order->service->name}\n\nPrecio del trabajo: $".number_format($quote->work_price,2)."\nDomicilio: $".number_format($quote->delivery_price,2)."\nTotal: $".number_format($quote->total,2)."\n\nVálida hasta: ".$quote->expires_at->format('d/m/Y g:i a')."\n\nPuedes revisar el estado aquí:\n{$url}\n\nResponde: Sí, confirmo el pedido / No deseo continuar";
  $phone=preg_replace('/\D/','',$order->phone); if(!str_starts_with($phone,'503'))$phone='503'.$phone;
  return QuoteNotification::firstOrCreate(['quote_id'=>$quote->id,'driver'=>'manual'],['order_id'=>$order->id,'status'=>'pending','message'=>$message,'url'=>'https://wa.me/'.$phone.'?text='.rawurlencode($message)]);
 }
}
