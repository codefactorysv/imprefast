@extends('layouts.public', ['title' => 'Archivos demasiado grandes'])

@section('content')
    <main class="wrap section">
        <div class="errors">
            <h1>Los archivos son demasiado grandes</h1>
            <p>Los archivos seleccionados son demasiado grandes para enviarlos juntos. El máximo total es 50 MB.</p>
            <p>Por seguridad, selecciona nuevamente los archivos que no pudieron cargarse.</p>
        </div>
        <p><a class="btn" href="{{ route('orders.create') }}">Volver al pedido</a></p>
    </main>
@endsection
