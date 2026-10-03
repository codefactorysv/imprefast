@extends('layouts.public', ['title' => 'Corregir ubicación'])

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>#location-map{height:420px;border-radius:16px;margin:14px 0}.comparison{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media(max-width:650px){.comparison{grid-template-columns:1fr}}</style>
@endpush

@section('content')
<main class="wrap section">
    <h1>{{ $needsReview ? 'Solicitar cambio de ubicación' : 'Corregir ubicación de entrega' }}</h1>
    @if (! $canDirect && ! $needsReview)
        <div class="errors">{{ $order->status === 'on_the_way' ? 'El pedido ya está en camino. Contacta al negocio para solicitar cualquier cambio.' : 'La ubicación ya no puede modificarse.' }}</div>
        <a class="btn" href="https://wa.me/{{ \App\Models\Setting::value('whatsapp_number','50370000000') }}">Contactar por WhatsApp</a>
    @else
        @if ($needsReview)<div class="flash">Tu pedido ya está confirmado. Enviaremos la solicitud al negocio para que revise si el cambio modifica el domicilio o el tiempo de entrega.</div>@endif
        <form id="location-form" method="post" action="{{ route('orders.location.update',[$order->code,$order->public_token]) }}">@csrf
            <button type="button" id="locate">Usar mi ubicación actual</button>
            <label>Buscar dirección o colonia<input id="address-search" placeholder="Escribe una colonia o dirección"></label>
            <div id="location-map"></div>
            <input type="hidden" name="latitude" id="latitude" value="{{ $order->latitude }}"><input type="hidden" name="longitude" id="longitude" value="{{ $order->longitude }}">
            <div class="two"><label>Departamento<input name="department" value="{{ $order->department }}" required></label><label>Municipio o distrito<input name="municipality" value="{{ $order->municipality }}" required></label><label>Colonia<input name="neighborhood" value="{{ $order->neighborhood }}"></label><label>Calle<input name="street" value="{{ $order->street }}"></label><label>Número de casa<input name="house_number" value="{{ $order->house_number }}"></label></div>
            <label>Referencias<textarea name="address_reference">{{ $order->address_reference }}</textarea></label>
            <label>Mensaje para el negocio<textarea name="customer_message" placeholder="Ejemplo: Me equivoqué al colocar el punto. La casa está una calle más abajo, frente a la tienda"></textarea></label>
            <div class="comparison"><div class="card"><h3>Ubicación anterior</h3><p>{{ $order->neighborhood }} {{ $order->street }} {{ $order->house_number }}, {{ $order->municipality }}</p><p>Domicilio: {{ $order->delivery_fee!==null?'$'.number_format($order->delivery_fee,2):'Pendiente' }}</p></div><div class="card"><h3>Nueva ubicación</h3><p id="new-address">Mueve el marcador y completa la dirección.</p><p id="new-price">Calculando…</p></div></div>
            <label><input style="width:auto" type="checkbox" name="location_confirmed" value="1" required> He revisado el mapa y confirmo que este es el nuevo punto de entrega</label>
            <button>Guardar nueva ubicación</button>
        </form>
    @endif
    @if(session('location_whatsapp'))<p><a class="btn alt" href="{{ session('location_whatsapp') }}">Avisar también por WhatsApp</a></p>@endif
</main>
@if ($canDirect || $needsReview)
<script>
const form=document.getElementById('location-form'),lat=document.getElementById('latitude'),lng=document.getElementById('longitude');
const map=L.map('location-map').setView([Number(lat.value||13.483874),Number(lng.value||-88.203702)],15);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);const marker=L.marker(map.getCenter(),{draggable:true}).addTo(map);
async function changed(point){marker.setLatLng(point);lat.value=point.lat;lng.value=point.lng;const data=new FormData(form);const response=await fetch('{{ route('orders.location.preview',[$order->code,$order->public_token]) }}',{method:'POST',headers:{Accept:'application/json'},body:data});const result=await response.json();document.getElementById('new-address').textContent=[form.neighborhood.value,form.street.value,form.house_number.value,form.municipality.value].filter(Boolean).join(', ');document.getElementById('new-price').textContent=result.delivery_fee===null?'Domicilio pendiente de revisión':`Distancia: ${result.distance_km} km · Nuevo domicilio: $${Number(result.delivery_fee).toFixed(2)}`;}
map.on('click',e=>changed(e.latlng));marker.on('dragend',e=>changed(e.target.getLatLng()));document.getElementById('locate').onclick=()=>navigator.geolocation.getCurrentPosition(p=>{const point={lat:p.coords.latitude,lng:p.coords.longitude};map.setView(point,17);changed(point)});
document.getElementById('address-search').addEventListener('change',async e=>{if(!e.target.value)return;const response=await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(e.target.value+', El Salvador'));const rows=await response.json();if(rows[0]){const point={lat:Number(rows[0].lat),lng:Number(rows[0].lon)};map.setView(point,17);changed(point)}});
</script>
@endif
@endsection
