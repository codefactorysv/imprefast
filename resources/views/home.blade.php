@extends('layouts.public')

@section('content')
    <section class="hero">
        <div class="wrap">
            <p><b>IMPRESIONES A DOMICILIO · SAN MIGUEL</b></p>
            <h1>Envíanos tu archivo, lo preparamos y te lo llevamos</h1>
            <p class="lead">Impresiones, copias, escaneos y trabajos escolares sin salir de casa.</p>
            <p><a class="btn" href="{{ route('orders.create') }}">Hacer un pedido</a> <a class="btn alt" href="{{ route('orders.lookup') }}">Consultar pedido</a></p>
        </div>
    </section>
    <main class="wrap">
        <section class="section">
            <h2>¿Qué necesitas?</h2>
            <div class="grid">
                @foreach ($services as $service)
                    <article class="card">
                        <div class="icon">{{ $service->icon }}</div>
                        <h3>{{ $service->name }}</h3><p>{{ $service->description }}</p>
                        <small>{{ $service->calculation_type === 'quote' ? 'Cotización personalizada' : 'Precio calculado al solicitar' }}</small>
                    </article>
                @endforeach
            </div>
        </section>
        <section class="section">
            <h2>Así de fácil</h2>
            <div class="grid">
                <div class="card step"><h3>Envía</h3><p>Selecciona el servicio y sube tus archivos de forma segura.</p></div>
                <div class="card step"><h3>Confirma</h3><p>Marca tu ubicación y revisa precio, copias y horario.</p></div>
                <div class="card step"><h3>Recibe</h3><p>Preparamos tu pedido y lo llevamos a tu domicilio.</p></div>
            </div>
        </section>
        <section class="section">
            <h2>Preguntas frecuentes</h2>
            <details class="card"><summary>¿Puedo pedir una sola página?</summary><p>Sí. No existe pedido mínimo; se suma el costo de domicilio.</p></details>
            <details class="card"><summary>¿Mis archivos son privados?</summary><p>Sí. Se guardan fuera del área pública, se usan solo para tu pedido y se eliminan al vencer su período de conservación.</p></details>
            <details class="card"><summary>¿Aceptan transferencias?</summary><p>Sí. El comprobante debe ser revisado antes de marcar el pago como confirmado.</p></details>
        </section>
        <section id="privacidad" class="section"><h2>Privacidad</h2><p>Usamos tus archivos únicamente para realizar el pedido. No los compartimos y los eliminamos después del período configurado. Evita enviar información innecesaria.</p></section>
        <section id="terminos" class="section"><h2>Términos básicos</h2><p>Todo archivo se revisa antes de imprimir. La urgencia está sujeta a disponibilidad. Reimpresiones ocasionadas por archivos o instrucciones incorrectas pueden generar un costo adicional.</p></section>
    </main>
@endsection
