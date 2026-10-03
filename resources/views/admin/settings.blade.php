@extends('layouts.admin', ['title' => 'Configuración'])

@section('content')
@php
    $values = $settings->keyBy('key');
    $groups = [
        'Datos del negocio' => [
            'business_name' => ['Nombre del negocio', 'text', 'Nombre visible para tus clientes.'],
            'whatsapp_number' => ['Número de WhatsApp', 'tel', 'Número que recibirá las consultas.'],
            'business_latitude' => ['Latitud del punto de preparación', 'number', 'Coordenada usada para calcular recorridos.', 'any'],
            'business_longitude' => ['Longitud del punto de preparación', 'number', 'Coordenada usada para calcular recorridos.', 'any'],
        ],
        'Horarios' => [
            'opening_time' => ['Hora de apertura', 'time', 'Inicio de la jornada de atención.'],
            'closing_time' => ['Hora de cierre', 'time', 'Fin de la jornada de atención.'],
        ],
        'Tarifas de domicilio' => [
            'delivery_base_fee' => ['Tarifa base', 'number', 'Importe inicial de cada domicilio.', '0.01'],
            'delivery_price_per_km' => ['Precio por kilómetro', 'number', 'Importe adicional según la distancia.', '0.01'],
            'delivery_minimum_fee' => ['Tarifa mínima', 'number', 'Mínimo que se cobrará por domicilio.', '0.01'],
            'delivery_max_radius_km' => ['Distancia máxima', 'number', 'Radio máximo de cobertura en kilómetros.', '0.1'],
            'delivery_pickup_return_factor' => ['Factor de recogida y devolución', 'number', 'Multiplicador para recorridos de ida y vuelta.', '0.01'],
            'urgent_delivery_surcharge' => ['Recargo urgente', 'number', 'Importe adicional por atención urgente.', '0.01'],
        ],
        'Archivos' => [
            'upload_max_mb' => ['Tamaño máximo por archivo', 'number', 'Límite permitido en megabytes.', '1'],
            'file_retention_days' => ['Días de conservación', 'number', 'Tiempo que se guardarán los archivos.', '1'],
        ],
    ];
@endphp
<main class="wrap section">
    <h1>Configuración</h1>
    <p class="muted">Administra los datos generales sin exponer credenciales ni claves privadas.</p>
    <section class="card" style="margin-bottom:16px">
        <h2>Notificaciones por Telegram</h2>
        @if (!config('services.telegram.enabled') || !config('services.telegram.bot_token'))
            <div class="errors">Telegram no está habilitado en el archivo .env. Los pedidos seguirán apareciendo en el panel, pero no recibirás avisos en el teléfono.</div>
        @endif
        <p><b>Estado:</b> {{ $telegramConnection ? 'Conectado' : 'No conectado' }}</p>
        @if($telegramConnection)
            <p><b>Cuenta:</b> {{ $telegramConnection->display_name ?: ($telegramConnection->username ? '@'.$telegramConnection->username : 'Cuenta de Telegram') }}</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" action="{{ route('admin.telegram.test') }}">@csrf<button>Enviar mensaje de prueba</button></form>
                <form method="post" action="{{ route('admin.telegram.disconnect') }}" onsubmit="return confirm('¿Desconectar Telegram?')">@csrf @method('delete')<button style="background:#991b1b">Desconectar Telegram</button></form>
            </div>
        @else
            <form method="post" action="{{ route('admin.telegram.connect') }}">@csrf<button>Conectar Telegram</button></form>
        @endif
        <div class="grid" style="margin-top:14px">
            <label><input style="width:auto" type="checkbox" name="settings[telegram_admin_enabled]" value="1" form="settings-form" @checked(old('settings.telegram_admin_enabled', $values->get('telegram_admin_enabled')?->value ?? '1') === '1')> Activar avisos por Telegram</label>
            <label><input style="width:auto" type="checkbox" name="settings[telegram_reminders_enabled]" value="1" form="settings-form" @checked(old('settings.telegram_reminders_enabled', $values->get('telegram_reminders_enabled')?->value ?? '1') === '1')> Recordar pedidos no revisados</label>
            <label>Minutos antes del recordatorio<input type="number" min="1" max="1440" name="settings[telegram_reminder_minutes]" value="{{ old('settings.telegram_reminder_minutes', $values->get('telegram_reminder_minutes')?->value ?? 10) }}" form="settings-form"></label>
            <label><input style="width:auto" type="checkbox" name="settings[admin_sound_enabled]" value="1" form="settings-form" @checked(old('settings.admin_sound_enabled', $values->get('admin_sound_enabled')?->value ?? '1') === '1')> Activar sonido en el panel</label>
        </div>
    </section>
        <form id="settings-form" method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        @foreach ($groups as $group => $fields)
            <section class="card" style="margin-bottom:16px">
                <h2>{{ $group }}</h2>
                <div class="grid">
                    @foreach ($fields as $key => $field)
                        @if ($values->has($key))
                            <label>{{ $field[0] }}
                                <input type="{{ $field[1] }}" step="{{ $field[3] ?? '' }}" name="settings[{{ $key }}]" value="{{ old('settings.'.$key, $values[$key]->value) }}">
                                <small class="muted">{{ $field[2] }}</small>
                            </label>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
        @if ($values->has('route_provider'))
            <section class="card"><h2>Rutas y mapas</h2><label>Proveedor de rutas
                <select name="settings[route_provider]">
                    @foreach (['osrm' => 'OSRM', 'openrouteservice' => 'OpenRouteService', 'mapbox' => 'Mapbox', 'google' => 'Google Maps'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('settings.route_provider', $values['route_provider']->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select><small class="muted">Servicio usado para calcular distancia y duración.</small>
            </label></section>
        @endif
        <p><button type="submit">Guardar cambios</button></p>
    </form>
</main>
@endsection
