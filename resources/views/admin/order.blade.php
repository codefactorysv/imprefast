@extends('layouts.admin', ['title' => $order->code])

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <main class="wrap section">
        <p><a href="{{ route('admin.dashboard') }}">← Pedidos</a></p>
        @if(!$order->reviewed_at)
            <form method="post" action="{{ route('admin.orders.review', $order) }}">@csrf<button>Marcar como revisado</button></form>
        @else
            <p class="muted">Revisado {{ $order->reviewed_at->format('d/m/Y g:i a') }}</p>
        @endif
        <h1>{{ $order->code }}</h1>

        <div class="grid">
            <div class="card">
                <h3>Cliente</h3>
                <p>{{ $order->customer_name }} · {{ $order->phone }}</p>
                <a class="btn alt" href="https://wa.me/503{{ $order->phone }}">Contactar</a>
            </div>
            <div class="card">
                <h3>Pedido</h3>
                <p>{{ $order->service->name }} · {{ $order->pages }} páginas × {{ $order->copies }} copias</p>
                <p>Estado del pedido: <span class="badge">{{ $order->status_label }}</span></p>
                <p>Estado del pago: <span class="badge">{{ $order->payment_status_label }}</span></p>
                <p>{{ $order->instructions }}</p>
                <p>Total: {{ $order->total ? '$'.number_format($order->total, 2) : 'Por cotizar' }} · Saldo ${{ $order->balance }}</p>
            </div>
        </div>

        <h2>Ubicación de entrega</h2>
        <section class="card">
            <div class="grid">
                <div>
                    <p><b>Dirección completa</b><br>{{ $order->department }}, {{ $order->municipality }}<br>{{ $order->neighborhood }} {{ $order->street }} {{ $order->house_number }}</p>
                    <p><b>Referencias</b><br>{{ $order->address_reference ?: 'Sin referencias' }}</p>
                    <p><b>Ruta</b><br>{{ $order->distance_km !== null ? number_format($order->distance_km, 2).' km' : 'Pendiente' }} · {{ $order->duration_minutes ? $order->duration_minutes.' minutos aproximadamente' : 'Duración pendiente' }}</p>
                    <p><b>Coordenadas</b><br>{{ $order->latitude ?? '—' }}, {{ $order->longitude ?? '—' }}</p>
                </div>
                <div>
                    @php
                        $facade = $order->files->firstWhere('type', 'facade');
                    @endphp
                    @if ($facade)
                        <p><b>Fotografía de fachada</b></p>
                        <p><a class="btn alt" href="{{ route('admin.files.download', [$order, $facade]) }}">Ver fotografía privada</a></p>
                    @endif
                </div>
            </div>

            @if ($order->latitude !== null && $order->longitude !== null)
                @php
                    $businessLatitude = (float) \App\Models\Setting::value('business_latitude', env('BUSINESS_LATITUDE', 13.483874));
                    $businessLongitude = (float) \App\Models\Setting::value('business_longitude', env('BUSINESS_LONGITUDE', -88.203702));
                    $customerLatitude = (float) $order->latitude;
                    $customerLongitude = (float) $order->longitude;
                    $locationUrl = 'https://www.google.com/maps/search/?api=1&query='.$customerLatitude.','.$customerLongitude;
                    $routeUrl = 'https://www.google.com/maps/dir/?api=1&origin='.$businessLatitude.','.$businessLongitude.'&destination='.$customerLatitude.','.$customerLongitude.'&travelmode=driving';
                @endphp
                <div id="delivery-map" style="height:360px;border-radius:14px;margin:18px 0"></div>
                <p style="display:flex;gap:12px;flex-wrap:wrap">
                    <a class="btn" target="_blank" rel="noopener" href="{{ $locationUrl }}">Abrir ubicación</a>
                    <a class="btn" target="_blank" rel="noopener" href="{{ $routeUrl }}">Iniciar ruta</a>
                </p>
            @else
                <div class="errors">El cliente no confirmó coordenadas. La ubicación requiere corrección manual.</div>
            @endif
        </section>

        @if (false)
        <h2>Corregir ubicación y recalcular</h2>
        <form class="card" method="post" action="{{ route('admin.orders.location', $order) }}">
            @csrf
            <div class="two">
                <label>Latitud<input type="number" step="0.0000001" name="latitude" value="{{ $order->latitude }}" required></label>
                <label>Longitud<input type="number" step="0.0000001" name="longitude" value="{{ $order->longitude }}" required></label>
                <label>Departamento<input name="department" value="{{ $order->department }}" required></label>
                <label>Municipio o distrito<input name="municipality" value="{{ $order->municipality }}" required></label>
                <label>Colonia<input name="neighborhood" value="{{ $order->neighborhood }}"></label>
                <label>Calle<input name="street" value="{{ $order->street }}"></label>
                <label>Número de casa<input name="house_number" value="{{ $order->house_number }}"></label>
            </div>
            <label>Referencias<textarea name="address_reference">{{ $order->address_reference }}</textarea></label>
            <label>Motivo obligatorio del cambio<textarea name="reason" required minlength="5"></textarea></label>
            <p class="muted">Si el pedido ya fue recibido, el estado operativo se conserva y el nuevo cálculo queda registrado en auditoría.</p>
            <button>Guardar ubicación y recalcular domicilio</button>
        </form>

        @endif

        <h2>Cambios de ubicación</h2>
        @forelse($order->locationChanges->sortByDesc('created_at') as $change)
            <section class="card" style="margin-bottom:14px">
                <p><b>Solicitado por el cliente</b> · {{ $change->created_at->format('d/m/Y g:i a') }} · <span class="badge">{{ ['applied'=>'Aplicado','pending'=>'Pendiente','rejected'=>'Rechazado'][$change->status] }}</span></p>
                <p>Dirección anterior: {{ collect($change->old_location)->only(['neighborhood','street','house_number','municipality'])->filter()->implode(', ') }}</p>
                <p>Dirección nueva: {{ collect($change->new_location)->only(['neighborhood','street','house_number','municipality'])->filter()->implode(', ') }}</p>
                <p>Distancia: {{ $change->old_distance_km ?? '—' }} → {{ $change->new_distance_km ?? 'Pendiente' }} km · Domicilio: ${{ number_format($change->old_delivery_fee ?? 0,2) }} → {{ $change->new_delivery_fee!==null?'$'.number_format($change->new_delivery_fee,2):'Pendiente' }}</p>
                <p>Mensaje: {{ $change->customer_message ?: 'Sin mensaje' }}</p>
                @if($change->status==='pending')
                    @if($change->new_delivery_fee!==null)<form method="post" action="{{ route('admin.location-changes.approve',[$order,$change]) }}">@csrf<button>Aprobar</button></form>@endif
                    <form method="post" action="{{ route('admin.location-changes.reject',[$order,$change]) }}">@csrf<label>Motivo del rechazo<textarea name="reason" required minlength="5"></textarea></label><button style="background:#991b1b">Rechazar</button></form>
                @elseif(!$change->admin_seen_at)<form method="post" action="{{ route('admin.location-changes.reviewed',[$order,$change]) }}">@csrf<button>Marcar como revisado</button></form>@endif
            </section>
        @empty<p>No hay cambios de ubicación.</p>@endforelse

        <h2>Archivos privados</h2>
        @if ($order->imageComposition)
            @php
                $compositionFiles = collect($order->imageComposition->order_file_ids)
                    ->map(fn ($id) => $order->files->firstWhere('id', $id))
                    ->filter();
            @endphp
            <section class="card">
                <h3>Diseño combinado</h3>
                <p><b>Imágenes incluidas:</b> {{ $compositionFiles->pluck('original_name')->join(', ') }}</p>
                <p><b>Distribución:</b> {{ $order->imageComposition->images_per_page }} imágenes por página</p>
                <p><b>Páginas resultantes:</b> {{ $order->imageComposition->resulting_pages }}</p>
                <p><b>Copias:</b> {{ $order->imageComposition->copies }}</p>
                <p><b>Páginas facturadas:</b> {{ $order->imageComposition->billable_pages }}</p>
                <p><b>Instrucciones:</b> {{ $order->imageComposition->instructions ?: 'Sin instrucciones adicionales' }}</p>
                <p><b>Tarifa:</b> ${{ number_format($order->imageComposition->unit_price, 2) }} por página · <b>Subtotal:</b> ${{ number_format($order->imageComposition->subtotal, 2) }}</p>
            </section>
        @endif
        @forelse ($order->files as $file)
            <section class="card">
                <h3>{{ $loop->iteration }}. {{ $file->original_name }}</h3>
                <p>Tipo: {{ ucfirst($file->document_type ?? $file->type) }} · {{ round($file->size / 1024) }} KB</p>
                @if ($order->imageComposition && in_array($file->id, $order->imageComposition->order_file_ids, true))
                    <p><b>Incluida en la página combinada</b></p>
                @elseif ($file->source_pages)
                    <p>Páginas: {{ $file->source_pages }} ({{ $file->page_detection === 'automatic' ? 'detectadas automáticamente' : 'estimadas por el cliente' }})<br>
                    Copias: {{ $file->copies }}<br>
                    Total: <b>{{ $file->print_units }} impresiones</b><br>
                    Papel: {{ ucfirst($file->paper_size) }} · {{ $file->double_sided ? 'Doble cara' : 'Una cara' }}{{ $file->stapled ? ' · Engrapado' : '' }}<br>
                    Subtotal: ${{ number_format($file->subtotal, 2) }}</p>
                @endif
                <a class="btn alt" href="{{ route('admin.files.download', [$order, $file]) }}">Descargar archivo</a>
            </section>
        @empty
            <p>No hay archivos.</p>
        @endforelse

        <h2>Estado operativo</h2>
        <section class="card">
            <p>Estado actual: <span class="badge">{{ $order->status_label }}</span></p>
            @php
                $nextActions = [
                    'received' => ['preparing' => 'Iniciar preparación'],
                    'preparing' => ['ready' => 'Marcar como listo'],
                    'ready' => ['on_the_way' => 'Marcar en camino'],
                    'on_the_way' => ['delivered' => 'Marcar como entregado'],
                ][$order->status] ?? [];
            @endphp
            @forelse ($nextActions as $newStatus => $label)
                <form method="post" action="{{ route('admin.orders.status', $order) }}" @if($newStatus === 'delivered') onsubmit="return confirm('¿Confirmas que el pedido fue entregado?')" @endif>
                    @csrf
                    <input type="hidden" name="status" value="{{ $newStatus }}">
                    <button>{{ $label }}</button>
                </form>
            @empty
                @if (in_array($order->status, ['delivered', 'cancelled']))
                    <p class="muted">Este es un estado terminal; no hay más acciones operativas.</p>
                @elseif ($order->status === 'pending_quote')
                    <p class="muted">El pedido avanzará a Recibido cuando la cotización sea aceptada.</p>
                @endif
            @endforelse

            @if (in_array($order->status, ['received', 'pending_quote', 'preparing', 'ready']))
                <form method="post" action="{{ route('admin.orders.status', $order) }}" onsubmit="return confirm('¿Confirmas que deseas cancelar este pedido?')">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <label>Motivo de cancelación<textarea name="reason" required minlength="5"></textarea></label>
                    <button style="background:#991b1b">Cancelar pedido</button>
                </form>
            @endif
        </section>

        @if ($order->requires_quote && $order->status === 'pending_quote')
        <h2>{{ $order->quotes->isEmpty() ? 'Cotizar trabajo' : 'Revisar cotización' }}</h2>
        <form class="card" method="post" action="{{ route('admin.orders.quote', $order) }}">
            @csrf
            @if ($order->delivery_fee !== null)
                <div class="flash"><b>Domicilio calculado: ${{ number_format($order->delivery_fee,2) }}</b><br>Distancia: {{ number_format($order->distance_km,2) }} km · Recargo urgente incluido: ${{ number_format($order->urgent_surcharge_snapshot ?? 0,2) }}</div>
            @else
                <div class="errors">Domicilio pendiente de revisión.</div>
            @endif
            <div class="two">
                <label>Trabajo<input type="number" step=".01" min="0" name="work_price" required></label>
                @if ($order->requires_deposit)
                    <label>Anticipo<input type="number" step=".01" min="0" name="deposit_required" value="0"></label>
                @endif
                <label>Vence (opcional)<input type="datetime-local" name="expires_at"></label>
            </div>
            @php
                $selectedAddons = $order->items->flatMap->options->where('type', 'addon');
            @endphp
            @if ($selectedAddons->isNotEmpty())
                <p><b>Opciones seleccionadas:</b> {{ $selectedAddons->pluck('name')->join(', ') }} — ${{ number_format($selectedAddons->sum('price'), 2) }}</p>
            @endif
            <label>Notas<textarea name="notes"></textarea></label>
            <p class="muted">Total = trabajo + domicilio calculado + complementos. El domicilio incluye base, kilómetros, mínimo, recogida y recargo urgente.</p>
            <button @disabled($order->delivery_fee === null)>{{ $order->quotes->isEmpty() ? 'Guardar y enviar cotización' : 'Crear nueva versión de cotización' }}</button>
        </form>

        @if ($latestQuote = $order->quotes->sortByDesc('version')->first())
            <section class="card">
                <h3>Cotización versión {{ $latestQuote->version }}</h3>
                <p>Trabajo ${{ number_format($latestQuote->work_price,2) }} · Domicilio ${{ number_format($latestQuote->delivery_price,2) }} · Complementos ${{ number_format($latestQuote->addons_total,2) }}</p>
                <h2>Total ${{ number_format($latestQuote->total,2) }}</h2>
                <p>Mensaje: {{ $latestQuote->notification?->status === 'sent' ? 'Enviado manualmente' : 'Pendiente de envío' }}</p>
                <p>Estado de la cotización: <span class="badge">{{ $latestQuote->status_label }}</span></p>
                @if ($latestQuote->notification && $latestQuote->status === 'sent' && ! $latestQuote->expires_at->isPast())
                    <a class="btn" target="_blank" rel="noopener" href="{{ $latestQuote->notification->url }}">Enviar cotización por WhatsApp</a>
                    <form method="post" action="{{ route('admin.quotes.sent',[$order,$latestQuote]) }}">@csrf<button>Marcar mensaje enviado</button></form>
                    <form method="post" action="{{ route('admin.quotes.response',[$order,$latestQuote]) }}">@csrf<label>Nota opcional<textarea name="note"></textarea></label><button name="decision" value="accepted">Registrar aceptación</button><button name="decision" value="rejected" style="background:#991b1b" onclick="return confirm('¿Confirmas el rechazo de la cotización?')">Registrar rechazo</button></form>
                @elseif ($latestQuote->expires_at->isPast())<div class="errors">Cotización vencida</div>@endif
            </section>
        @endif

        @if ($order->delivery_fee === null)
            <h2>Revisar costo de domicilio</h2>
            <form class="card" method="post" action="{{ route('admin.orders.delivery-review',$order) }}">@csrf<label>Nuevo costo<input type="number" name="delivery_fee" min="0" step=".01" required></label><label>Motivo<textarea name="reason" required minlength="5"></textarea></label><button>Guardar revisión</button></form>
        @endif

        @endif

        @unless($order->requires_quote)
            <h2>Resumen del precio</h2>
            <section class="card"><p>Servicio <b>${{ number_format($order->work_subtotal ?? 0,2) }}</b></p><p>Domicilio <b>${{ number_format($order->delivery_fee ?? 0,2) }}</b></p><h3>Total ${{ number_format($order->total ?? 0,2) }}</h3></section>
        @endunless

        <h2>Registrar pago</h2>
        <form class="card" method="post" action="{{ route('admin.orders.payment', $order) }}">
            @csrf
            <div class="two">
                <label>Monto<input type="number" step=".01" min=".01" name="amount" required></label>
                <label>Método<select name="method"><option value="cash">Efectivo</option><option value="transfer">Transferencia</option></select></label>
                <label>Estado<select name="status"><option value="pending_verification">Pendiente de verificación</option><option value="verified">Verificado</option><option value="rejected">Rechazado</option></select></label>
            </div>
            <button>Registrar</button>
        </form>
    </main>

    @if ($order->latitude !== null && $order->longitude !== null)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const preparation = [{{ $businessLatitude }}, {{ $businessLongitude }}];
            const customer = [{{ $customerLatitude }}, {{ $customerLongitude }}];
            const map = L.map('delivery-map');
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
            L.marker(preparation).addTo(map).bindPopup('Punto de preparación');
            L.marker(customer).addTo(map).bindPopup('Ubicación del cliente').openPopup();
            map.fitBounds([preparation, customer], {padding: [35, 35]});
        </script>
    @endif
@endsection
