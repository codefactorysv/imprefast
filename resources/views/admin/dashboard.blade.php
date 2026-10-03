@extends('layouts.admin', ['title' => 'Panel'])

@section('content')
    <main class="wrap section">
        <h1>Panel de pedidos</h1>
        <p style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach(['all'=>'Todos','new'=>'Nuevos','quote'=>'Pendientes de cotización','urgent'=>'Urgentes','preparing'=>'En preparación'] as $value=>$label)
                <a class="btn" href="{{ route('admin.dashboard',['filter'=>$value]) }}">{{ $label }}</a>
            @endforeach
        </p>
        <div class="grid">
            <div class="card"><b>{{ $today }}</b><p>Recibidos hoy</p></div>
            @foreach (\App\Services\OrderStatusService::LABELS as $status => $label)
                <div class="card">
                    <b>{{ $counts[$status] ?? 0 }}</b>
                    <p>{{ $label }}</p>
                </div>
            @endforeach
        </div>
        <h2>Pedidos recientes</h2>
        <div class="card" style="overflow:auto">
            <table>
                <thead><tr><th>Código</th><th>Cliente</th><th>Servicio</th><th>Estado</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr @if(!$order->reviewed_at) style="background:#fff8dc" @endif>
                            <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->code }}</a></td>
                            <td>{{ $order->customer_name }}<br><small>{{ $order->phone }}</small></td>
                            <td>{{ $order->service->name }} @if(!$order->reviewed_at)<span class="badge">Nuevo</span>@endif @if($order->requires_quote)<span class="badge">Requiere cotización</span>@endif @if($order->urgent)<span class="badge">Urgente</span>@endif</td><td>{{ $order->status_label }}</td>
                            <td>{{ $order->total ? '$'.$order->total : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </main>
@endsection
