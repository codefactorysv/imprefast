@extends('layouts.admin', ['title' => $service->exists ? 'Editar servicio' : 'Agregar servicio'])

@section('content')
<main class="wrap section">
    <p><a href="{{ route('admin.services.index') }}">← Servicios</a></p>
    <h1>{{ $service->exists ? 'Editar servicio' : 'Agregar servicio' }}</h1>
    @if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ $service->exists ? route('admin.services.update',$service) : route('admin.services.store') }}">@csrf @if($service->exists) @method('PUT') @endif
        <div class="card"><h2>Información general</h2><div class="two">
            <label>Nombre<input name="name" value="{{ old('name',$service->name) }}" required></label>
            <label>Slug<input name="slug" value="{{ old('slug',$service->slug) }}" required></label>
            <label>Descripción corta<input name="short_description" value="{{ old('short_description',$service->short_description) }}"></label>
            <label>Ícono o emoji<input name="icon" value="{{ old('icon',$service->icon) }}"></label>
        </div><label>Descripción completa<textarea name="description" required>{{ old('description',$service->description) }}</textarea></label><label>Instrucciones para el cliente<textarea name="instructions">{{ old('instructions',$service->instructions) }}</textarea></label></div>
        <div class="card"><h2>Precio y comportamiento</h2><div class="two">
            <label>Tipo de cálculo<select name="pricing_model">@foreach(['per_page'=>'Precio por página','per_unit'=>'Precio por unidad','fixed'=>'Precio fijo','tiered'=>'Paquetes o escalas','manual'=>'Cotización manual'] as $value=>$label)<option value="{{ $value }}" @selected(old('pricing_model',$service->pricing_model)===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Precio base<input type="number" step="0.01" min="0" name="price_per_page" value="{{ old('price_per_page',$service->price_per_page) }}"></label>
            <label>Unidad de cobro<input name="billing_unit" value="{{ old('billing_unit',$service->billing_unit ?: 'unidad') }}" required></label>
            <label>Orden<input type="number" min="0" name="sort_order" value="{{ old('sort_order',$service->sort_order ?? 0) }}" required></label>
        </div><div class="grid checks">@foreach(['requires_file'=>'Requiere archivo','requires_pages'=>'Requiere páginas','requires_copies'=>'Requiere copias o juegos','requires_quantity'=>'Requiere cantidad o paquete','requires_topic'=>'Requiere tema','requires_education_level'=>'Requiere nivel educativo','allows_cover'=>'Permite portada','allows_images'=>'Permite imágenes','allows_stapling'=>'Permite engrapado','requires_output_format'=>'Requiere formato de entrega','requires_pickup_instructions'=>'Requiere recogida de documentos','requires_deposit'=>'Requiere anticipo','allows_letter'=>'Permite carta','allows_legal'=>'Permite oficio','allows_duplex'=>'Permite doble cara','pickup_return'=>'Recogida y devolución','allows_urgent'=>'Permite urgente','active'=>'Servicio activo'] as $field=>$label)<label><input style="width:auto" type="checkbox" name="{{ $field }}" value="1" @checked(old($field,$service->$field ?? false))> {{ $label }}</label>@endforeach</div><p class="muted">Selecciona solo las capacidades que el cliente realmente debe completar. “Cotización manual” se controla exclusivamente con el tipo de cálculo.</p></div>
        @foreach(['variants'=>['Variantes','Nombre','Grupo','Efecto','Precio'],'tiers'=>['Paquetes o escalas','Etiqueta','Mínimo','Máximo','Precio'],'addons'=>['Complementos','Nombre','Descripción','Tipo','Precio']] as $key=>$meta)
        <div class="card"><h2>{{ $meta[0] }}</h2><div id="{{ $key }}-rows">
            @foreach(old($key,$service->exists ? ($key==='variants'?$service->variants:($key==='tiers'?$service->priceTiers:$service->addons))->toArray() : []) as $i=>$row)
                @include('admin.services.row',['kind'=>$key,'i'=>$i,'row'=>$row])
            @endforeach
        </div><button type="button" onclick="addRow('{{ $key }}')">Agregar</button></div>
        @endforeach
        <p><button type="submit">Guardar servicio y precios</button></p>
    </form>
</main>
<template id="variants-template">@include('admin.services.row',['kind'=>'variants','i'=>'__INDEX__','row'=>[]])</template>
<template id="tiers-template">@include('admin.services.row',['kind'=>'tiers','i'=>'__INDEX__','row'=>[]])</template>
<template id="addons-template">@include('admin.services.row',['kind'=>'addons','i'=>'__INDEX__','row'=>[]])</template>
<script>function addRow(kind){const box=document.getElementById(kind+'-rows'),i=box.children.length,t=document.getElementById(kind+'-template');box.insertAdjacentHTML('beforeend',t.innerHTML.replaceAll('__INDEX__',i))}function removeRow(button){button.closest('.option-row').remove()}</script>
@endsection
