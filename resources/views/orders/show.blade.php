@extends('layouts.public', ['title' => $order->code])

@section('content')
    <main class="wrap section">
        @if (in_array($order->status, ['pending_quote','received','preparing']))
            <p><a class="btn alt" href="{{ route('orders.location.edit',[$order->code,$order->public_token]) }}">{{ in_array($order->status,['received','preparing']) ? 'Solicitar cambio de ubicación' : 'Corregir ubicación de entrega' }}</a></p>
        @elseif ($order->status === 'on_the_way')
            <div class="errors">El pedido ya está en camino. Contacta al negocio para solicitar cualquier cambio.</div>
        @endif
        <div class="flash">✓ Recibimos tu solicitud</div>
        <h1>{{ $order->code }}</h1>
        <p class="lead">Guarda este código para consultar tu pedido.</p>
        <div class="grid">
            <div class="card">
                <h3>Servicio</h3>
                <p>{{ $order->service->name }}</p>
                <p>Estado: <span class="badge">{{ $order->status_label }}</span></p>
                @if ($latestQuote = $order->quotes->sortByDesc('version')->first())<p>Cotización: <span class="badge">{{ $latestQuote->status_label }}</span></p>@endif
                <p>Pago: <span class="badge">{{ $order->payment_status_label }}</span></p>
                <p>Fecha: {{ $order->requested_at->format('d/m/Y g:i a') }}</p>
            </div>
            <div class="card">
                <h3>Resumen</h3>
                <p>Trabajo: {{ $order->work_subtotal !== null ? '$'.number_format($order->work_subtotal, 2) : 'Por cotizar' }}</p>
                <p>Domicilio: {{ $order->delivery_fee !== null ? '$'.number_format($order->delivery_fee, 2) : 'Por confirmar' }}</p>
                <p><b>Total: {{ $order->total !== null ? '$'.number_format($order->total, 2) : 'Pendiente de cotización' }}</b></p>
            </div>
        </div>
        <p>
            <button onclick="print()">Imprimir comprobante</button>
            <a class="btn alt" href="https://wa.me/{{ \App\Models\Setting::value('whatsapp_number', '50370000000') }}?text={{ urlencode('Hola, realicé el pedido '.$order->code.'. Este es el resumen: '.url()->current()) }}">Enviar por WhatsApp</a>
        </p>
    </main>
@endsection
