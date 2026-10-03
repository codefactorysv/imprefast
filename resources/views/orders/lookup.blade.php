@extends('layouts.public', ['title' => 'Consultar pedido'])

@section('content')
<main class="wrap section">
    <h1>Consulta el estado de tu pedido</h1>
    <p class="lead">Ingresa el código que recibiste al realizar tu pedido.</p>
    <form class="card" method="post">
        @csrf
        <label>Código del pedido
            <input name="code" value="{{ old('code') }}" placeholder="Ejemplo: PED-K7M4Q9" autocomplete="off" required>
        </label>
        @error('code')<div class="errors">{{ $message }}</div>@enderror
        <p><button>Consultar pedido</button></p>
    </form>

    @if (request()->isMethod('post'))
        @if ($order)
            <section class="card">
                <h2>{{ $order->code }}</h2>
                <p>{{ $order->service->name }} · <span class="badge">{{ $order->status_label }}</span></p>
                @if ($latestQuote = $order->quotes->sortByDesc('version')->first())<p><b>Cotización:</b> {{ $latestQuote->status_label }}</p>@endif
                <p><b>Pago:</b> {{ $order->payment_status_label }}</p>
                <p><b>Fecha solicitada:</b> {{ $order->requested_at->format('d/m/Y g:i a') }}</p>
                <p><b>Precio del trabajo:</b> {{ $order->work_subtotal !== null ? '$'.number_format($order->work_subtotal,2) : 'Precio del trabajo pendiente de cotización' }}</p>
                <p><b>Domicilio:</b> {{ $order->delivery_fee !== null ? '$'.number_format($order->delivery_fee,2) : 'Pendiente de revisión' }}</p>
                <p><b>Total:</b> {{ $order->total !== null ? '$'.number_format($order->total,2) : 'Pendiente' }}</p>
                <p><b>Pagado:</b> ${{ number_format($order->verified_paid,2) }} · <b>Saldo:</b> ${{ $order->balance }}</p>
                <h3>Historial</h3>
                @foreach ($order->histories as $history)
                    <p>{{ $history->created_at->format('d/m/Y g:i a') }} — {{ (new \App\Models\Order(['status'=>$history->to_status]))->status_label }}</p>
                @endforeach
                <a class="btn" href="https://wa.me/{{ \App\Models\Setting::value('whatsapp_number','50370000000') }}">Contactar al negocio</a>
            </section>
        @else
            <div class="errors">No encontramos un pedido con ese código.</div>
        @endif
    @endif
</main>
@endsection
