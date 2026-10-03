<?php
namespace App\Services;
use Illuminate\Support\Facades\Http; use RuntimeException;
class OsrmRouteCalculator implements RouteCalculatorInterface { public function calculate(float $a,float $b,float $c,float $d): RouteResult { $url=rtrim(config('services.routes.url'),'/')."/route/v1/driving/$b,$a;$d,$c"; $r=Http::timeout((int)config('services.routes.timeout',10))->get($url,['overview'=>'false']); if(!$r->successful()||!$r->json('routes.0.distance')) throw new RuntimeException('Route provider unavailable'); return new RouteResult(round($r->json('routes.0.distance')/1000,2),(int)ceil($r->json('routes.0.duration')/60),'osrm'); } }
