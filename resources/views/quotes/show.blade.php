@extends('layouts.public', ['title' => 'Cotización'])

@section('content')
    <main class="wrap section">
        <h1>Cotización {{ $quote->order->code }}</h1>
        <div class="card">
            <p>Trabajo personalizado: ${{ number_format($quote->work_price, 2) }}<br>Domicilio: ${{ number_format($quote->delivery_price, 2) }}<br>Complementos: ${{ number_format($quote->addons_total, 2) }}</p>
            <h2>Total ${{ number_format($quote->total, 2) }}</h2>
            <p>{{ $quote->notes }}</p>
            <p>Válida hasta {{ $quote->expires_at->format('d/m/Y g:i a') }}</p>
            @if ($quote->status === 'sent' && ! $quote->expires_at->isPast())
                <form method="post">
                    @csrf
                    <button name="decision" value="accepted">Aceptar</button>
                    <button name="decision" value="rejected" style="background:#991b1b">Rechazar</button>
                </form>
            @else
                <p class="badge">{{ $quote->expires_at->isPast() ? 'Cotización vencida' : $quote->status_label }}</p>
            @endif
        </div>
    </main>
@endsection
