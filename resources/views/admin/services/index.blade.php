@extends('layouts.admin', ['title' => 'Servicios y precios'])

@section('content')
<main class="wrap section">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div><h1>Servicios y precios</h1><p class="muted">Administra qué vendes y cómo se calcula cada precio.</p></div>
        <a class="btn" href="{{ route('admin.services.create') }}">Agregar servicio</a>
    </div>
    <form id="reorder-services" method="post" action="{{ route('admin.services.reorder') }}">@csrf</form>
        <div class="card" style="overflow:auto">
            <table><thead><tr><th>Nombre</th><th>Tipo</th><th>Cálculo</th><th>Precio inicial</th><th>Archivo</th><th>Recogida</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr></thead>
            <tbody>
            @forelse($services as $service)
                <tr>
                    <td><b>{{ $service->name }}</b><br><small>{{ $service->short_description }}</small></td>
                    <td>{{ $service->billing_unit }}</td><td>{{ ['per_page'=>'Por página','per_unit'=>'Por unidad','fixed'=>'Fijo','tiered'=>'Paquetes','manual'=>'Cotización manual'][$service->pricing_model] ?? $service->pricing_model }}</td>
                    <td>{{ $service->price_per_page !== null ? '$'.number_format($service->price_per_page,2) : 'Pendiente' }}</td>
                    <td>{{ $service->requires_file ? 'Sí' : 'No' }}</td><td>{{ $service->pickup_return ? 'Sí' : 'No' }}</td>
                    <td><span class="badge">{{ $service->active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td><input form="reorder-services" style="width:75px" type="number" min="0" name="orders[{{ $service->id }}]" value="{{ $service->sort_order }}"></td>
                    <td style="min-width:240px"><a href="{{ route('admin.services.edit',$service) }}">Editar / precios</a>
                        <form style="display:inline" method="post" action="{{ route('admin.services.toggle',$service) }}">@csrf @method('PATCH')<button style="padding:7px">{{ $service->active?'Desactivar':'Activar' }}</button></form>
                        <form style="display:inline" method="post" action="{{ route('admin.services.duplicate',$service) }}">@csrf<button style="padding:7px">Duplicar</button></form>
                        <form style="display:inline" method="post" action="{{ route('admin.services.destroy',$service) }}" onsubmit="return confirm('¿Eliminar este servicio?')">@csrf @method('DELETE')<button style="padding:7px;background:#991b1b">Eliminar</button></form>
                    </td>
                </tr>
            @empty<tr><td colspan="9">No hay servicios.</td></tr>@endforelse
            </tbody></table>
        </div><p><button form="reorder-services">Guardar orden</button></p>
</main>
@endsection
