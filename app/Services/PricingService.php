<?php
namespace App\Services;
use App\Models\Setting;
class PricingService {
 public function delivery(float $km,bool $pickup=false,bool $urgent=false): array { $base=(float)Setting::value('delivery_base_fee',env('DELIVERY_BASE_FEE',1)); $per=(float)Setting::value('delivery_price_per_km',env('DELIVERY_PRICE_PER_KM',.30)); $min=(float)Setting::value('delivery_minimum_fee',env('DELIVERY_MINIMUM_FEE',1.50)); $factor=$pickup?(float)Setting::value('delivery_pickup_return_factor',env('DELIVERY_PICKUP_RETURN_FACTOR',2)):1; $urgentFee=$urgent?(float)Setting::value('urgent_delivery_surcharge',env('URGENT_DELIVERY_SURCHARGE',1)):0; $subtotal=round(max($min,$base+$km*$per)*$factor,2); return ['base'=>$base,'per_km'=>$per,'minimum'=>$min,'factor'=>$factor,'urgent'=>$urgentFee,'subtotal'=>$subtotal,'total'=>round($subtotal+$urgentFee,2)]; }
 public function printing(int $pages,int $copies,float $price): float { return round($pages*$copies*$price,2); }
}
