@extends('layout', ['adminLayout' => true])

@section('admin-navigation')
<header class="wrap">
    <nav style="flex-wrap:wrap;gap:16px">
        <a class="brand" href="{{ route('admin.dashboard') }}">Administración ImpreFast</a>
        <div class="navlinks" style="flex-wrap:wrap">
            <a href="{{ route('admin.dashboard') }}">Panel</a>
            @php($newOrders=\App\Models\Order::whereNull('reviewed_at')->count())
            <a href="{{ route('admin.dashboard', ['filter'=>'new']) }}">Pedidos @if($newOrders)<span id="new-order-count" class="badge">{{ $newOrders }}</span>@endif</a>
            <a href="{{ route('admin.services.index') }}">Servicios</a>
            <a href="{{ route('admin.settings.edit') }}">Configuración</a>
            <form method="post" action="{{ route('admin.logout') }}">@csrf<button style="padding:8px 12px">Cerrar sesión</button></form>
        </div>
    </nav>
</header>
<div id="admin-notification-toast" class="flash" style="display:none;position:fixed;right:20px;bottom:20px;z-index:1000;max-width:360px"></div>
<script>
(() => {
    let lastOrderId = Number(sessionStorage.getItem('lastAdminOrderNotification') || 0);
    const poll = async () => {
        try {
            const response = await fetch(`{{ route('admin.notifications.poll') }}?after=${lastOrderId}`, {headers:{Accept:'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            const badge = document.getElementById('new-order-count');
            if (badge) badge.textContent = data.count;
            const newest = data.orders.at(-1);
            if (newest) {
                lastOrderId = newest.id;
                sessionStorage.setItem('lastAdminOrderNotification', lastOrderId);
                const toast = document.getElementById('admin-notification-toast');
                toast.innerHTML = `<b>${newest.requires_quote?'Nueva solicitud':'Nuevo pedido'} ${newest.code}</b><br>${newest.service}${newest.requires_quote?' — requiere cotización':newest.total?' — $'+Number(newest.total).toFixed(2):''}`;
                toast.style.display = 'block'; setTimeout(() => toast.style.display='none', 8000);
                if (data.sound_enabled) { const audio = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA='); audio.play().catch(()=>{}); }
            }
        } catch (_) {}
    };
    poll(); setInterval(poll, 25000);
})();
</script>
@endsection
