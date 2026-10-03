<?php

namespace App\Http\Controllers;

use App\Models\{Order,OrderLocationChange};
use App\Services\{OrderPreviewService,WhatsAppNotifierInterface};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerLocationController extends Controller
{
    private const DIRECT = ['pending_quote'];
    private const REVIEW = ['received','preparing'];

    public function edit(string $code,string $token)
    {
        $order=$this->order($code,$token);
        return view('orders.location-edit',['order'=>$order,'canDirect'=>in_array($order->status,self::DIRECT,true),'needsReview'=>in_array($order->status,self::REVIEW,true)]);
    }

    public function preview(Request $request,string $code,string $token,OrderPreviewService $pricing)
    {
        $order=$this->order($code,$token); abort_unless(in_array($order->status,array_merge(self::DIRECT,self::REVIEW),true),422);
        $data=$this->validated($request,false);
        $preview=$pricing->calculate(['service_id'=>$order->service_id,'pages'=>$order->pages,'copies'=>$order->copies,'quantity'=>$order->items()->first()?->quantity,'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'urgent'=>$order->urgent]);
        return response()->json(['distance_km'=>$preview['distance_km'],'duration_minutes'=>$preview['duration_minutes'],'delivery_fee'=>$preview['delivery']['total']??null,'needs_review'=>in_array($order->status,self::REVIEW,true)||$preview['delivery']===null,'reason'=>$preview['reason']]);
    }

    public function update(Request $request,string $code,string $token,OrderPreviewService $pricing,WhatsAppNotifierInterface $notifier)
    {
        $order=$this->order($code,$token); abort_unless(in_array($order->status,array_merge(self::DIRECT,self::REVIEW),true),422);
        $data=$this->validated($request,true);
        $preview=$pricing->calculate(['service_id'=>$order->service_id,'pages'=>$order->pages,'copies'=>$order->copies,'quantity'=>$order->items()->first()?->quantity,'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'urgent'=>$order->urgent]);
        $location=collect($data)->only(['latitude','longitude','department','municipality','neighborhood','street','house_number','address_reference'])->all();
        $old=$order->only(['latitude','longitude','department','municipality','neighborhood','street','house_number','address_reference']);
        $direct=in_array($order->status,self::DIRECT,true)&&$preview['delivery']!==null;
        $change=DB::transaction(function()use($request,$data,$order,$preview,$location,$old,$direct,$notifier){
            $delivery=$preview['delivery'];
            $change=$order->locationChanges()->create(['status'=>$direct?'applied':'pending','direct'=>$direct,'old_location'=>$old,'new_location'=>$location,'old_distance_km'=>$order->distance_km,'new_distance_km'=>$preview['distance_km'],'new_duration_minutes'=>$preview['duration_minutes'],'old_delivery_fee'=>$order->delivery_fee,'new_delivery_fee'=>$delivery['total']??null,'rate_snapshot'=>$delivery,'customer_message'=>$data['customer_message']??null,'order_status_at_request'=>$order->status,'reviewed_at'=>$direct?now():null]);
            if($direct){
                $oldDelivery=(float)$order->delivery_fee; $newDelivery=(float)$delivery['total'];
                $order->update($location+['distance_km'=>$preview['distance_km'],'duration_minutes'=>$preview['duration_minutes'],'delivery_fee'=>$newDelivery,'route_provider'=>$preview['route_provider'],'route_calculated_at'=>now(),'base_fee_snapshot'=>$delivery['base'],'price_per_km_snapshot'=>$delivery['per_km'],'minimum_fee_snapshot'=>$delivery['minimum'],'pickup_factor_snapshot'=>$delivery['factor'],'urgent_surcharge_snapshot'=>$delivery['urgent'],'total'=>$order->work_subtotal!==null?round((float)$order->work_subtotal+$newDelivery,2):null]);
                $latest=$order->quotes()->where('status','sent')->latest('version')->first();
                if($latest&&abs($oldDelivery-$newDelivery)>.001){$latest->update(['status'=>'expired']);$copy=$latest->replicate(['token','status','responded_at']);$copy->version=((int)$order->quotes()->max('version'))+1;$copy->delivery_price=$newDelivery;$copy->total=round((float)$copy->work_price+$newDelivery+(float)$copy->addons_total,2);$copy->token=hash('sha256',\Illuminate\Support\Str::random(64));$copy->status='sent';$copy->save();$change->update(['quote_id'=>$copy->id]);$order->update(['total'=>$copy->total]);$notifier->prepareQuoteReady($order->load('service'),$copy);}
            }
            return $change;
        });
        $business=preg_replace('/\D/','',\App\Models\Setting::value('whatsapp_number','50370000000'));
        $message="Hola, corregí la ubicación de mi pedido {$order->code}.\n\nNueva referencia:\n".($data['address_reference']??'Sin referencia')."\n\nMensaje:\n".($data['customer_message']??'Sin mensaje')."\n\nLa nueva ubicación ya fue registrada en el sistema.";
        return back()->with('ok',$direct?'Tu nueva ubicación fue guardada y el negocio recibió una notificación.':'Esta ubicación necesita revisión. El negocio confirmará el nuevo costo antes de continuar.')->with('location_whatsapp','https://wa.me/'.$business.'?text='.rawurlencode($message));
    }

    private function order(string $code,string $token):Order{return Order::with(['service','items'])->where('code',$code)->where('public_token',$token)->firstOrFail();}
    private function validated(Request $r,bool $confirm):array{return $r->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','department'=>'required|string|max:120','municipality'=>'required|string|max:120','neighborhood'=>'nullable|string|max:120','street'=>'nullable|string|max:180','house_number'=>'nullable|string|max:40','address_reference'=>'nullable|string|max:500','customer_message'=>'nullable|string|max:1000']+($confirm?['location_confirmed'=>'accepted']:[]));}
}
