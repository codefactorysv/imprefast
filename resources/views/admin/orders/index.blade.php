@extends('layouts.admin', ['title' => 'Pedidos'])

@section('content')
<main class="wrap section"><h1>Pedidos</h1><div class="card" style="overflow:auto"><table>
<thead><tr><th>Código</th><th>Cliente</th><th>Servicio</th><th>Estado</th><th>Total</th><th></th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td>{{ $order->code }}</td><td>{{ $order->customer_name }}</td><td>{{ $order->service->name }}</td><td>{{ $order->status_label }}</td><td>{{ $order->total ? '$'.$order->total : '—' }}</td><td><a href="{{ route('admin.orders.show', $order) }}">Ver</a></td></tr>
@empty<tr><td colspan="6">No hay pedidos.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}</main>
@endsection
