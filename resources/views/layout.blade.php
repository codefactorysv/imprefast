<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $title ?? 'Impresiones a domicilio' }}</title>
    <style>
        :root {
            --ink: #18332f;
            --brand: #0f766e;
            --accent: #f59e0b;
            --paper: #fffdf7;
            --soft: #e8f5f1
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font: 16px system-ui;
            color: var(--ink);
            background: var(--paper)
        }

        a {
            color: inherit
        }

        .wrap {
            max-width: 1100px;
            margin: auto;
            padding: 0 20px
        }

        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 0
        }

        .brand {
            font-weight: 900;
            color: var(--brand);
            text-decoration: none
        }

        .navlinks {
            display: flex;
            gap: 14px;
            align-items: center
        }

        .btn,
        button {
            display: inline-block;
            border: 0;
            border-radius: 12px;
            padding: 13px 18px;
            background: var(--brand);
            color: white;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer
        }

        .btn.alt {
            background: white;
            color: var(--brand);
            border: 2px solid var(--brand)
        }

        .hero {
            padding: 65px 0;
            background: linear-gradient(135deg, var(--soft), #fff3d6)
        }

        h1 {
            font-size: clamp(2.2rem, 6vw, 4.5rem);
            line-height: 1.02;
            margin: .2em 0;
            max-width: 850px
        }

        h2 {
            font-size: 1.8rem;
            margin-top: 2em
        }

        .lead {
            font-size: 1.2rem;
            max-width: 650px
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 18px
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 18px;
            border: 1px solid #dce8e4;
            box-shadow: 0 8px 25px #17342e0d
        }

        .icon {
            font-size: 2rem
        }

        .steps {
            counter-reset: s
        }

        .step:before {
            counter-increment: s;
            content: counter(s);
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent);
            font-weight: bold
        }

        .section {
            padding: 38px 0
        }

        .muted {
            color: #60736f
        }

        .flash {
            padding: 13px;
            background: #dcfce7;
            border-radius: 10px
        }

        .errors {
            background: #fee2e2;
            padding: 14px;
            border-radius: 10px
        }

        label {
            display: block;
            font-weight: 700;
            margin-top: 14px
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #aebdb9;
            border-radius: 9px;
            font: inherit;
            background: white
        }

        .checks label {
            font-weight: 500
        }

        .two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px
        }

        .progress {
            height: 8px;
            background: #dbe7e4;
            border-radius: 9px
        }

        .progress i {
            display: block;
            width: 100%;
            height: 100%;
            background: var(--brand);
            border-radius: 9px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            text-align: left;
            padding: 11px;
            border-bottom: 1px solid #ddd
        }

        .badge {
            padding: 5px 9px;
            border-radius: 20px;
            background: var(--soft);
            font-size: .85rem
        }

        .wa {
            position: fixed;
            right: 18px;
            bottom: 18px;
            background: #22c55e;
            border-radius: 50%;
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            color: white;
            text-decoration: none;
            font-size: 27px
        }

        footer {
            background: #153b35;
            color: white;
            padding: 35px 0;
            margin-top: 50px
        }

        @media(max-width:650px) {
            .two {
                grid-template-columns: 1fr
            }

            .navlinks {
                font-size: .85rem
            }

            .hero {
                padding: 35px 0
            }

            .card {
                padding: 17px
            }

            table {
                font-size: .85rem
            }

            .hide-mobile {
                display: none
            }
        }
    </style>
    @stack('head')
</head>

<body>
    @unless($adminLayout ?? false)
    <div class="wrap">
        <nav><a class="brand" href="/">ImpreFast <small>provisional</small></a>
            <div class="navlinks"><a href="{{ route('orders.lookup') }}">Consultar</a><a class="btn"
                    href="{{ route('orders.create') }}">Hacer pedido</a></div>
        </nav>
    </div>
    @endunless
    @yield('admin-navigation')
    @if (session('ok'))
        <div class="wrap flash">{{ session('ok') }}</div>
    @endif
    @yield('content')
    <footer>
        <div class="wrap"><b>Punto de preparación en San Miguel</b>
            <p>Atención de 6:00 a. m. a 9:00 p. m. · San Miguel centro y colonias cercanas.</p>
            <p><a href="#privacidad">Privacidad</a> · <a href="#terminos">Términos del servicio</a></p>
        </div>
    </footer><a class="wa" aria-label="WhatsApp"
        href="https://wa.me/{{ \App\Models\Setting::value('whatsapp_number', env('WHATSAPP_NUMBER', '50370000000')) }}?text={{ urlencode('Hola, deseo información sobre impresiones a domicilio.') }}">◉</a>
    <script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js')</script>
</body>

</html>
