@extends('layouts.admin', ['title' => 'Administración'])

@section('content')
    <main class="wrap section" style="max-width:520px">
        <h1>Administración</h1>

        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        <form class="card" method="post">
            @csrf
            <label>Correo<input type="email" name="email" required></label>
            <label>Contraseña<input type="password" name="password" required></label>
            <p><button>Iniciar sesión</button></p>
        </form>
    </main>
@endsection
