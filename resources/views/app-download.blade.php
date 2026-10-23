<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App móvil | LumaTek</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            box-sizing: border-box;
            background: #f4f7f5;
            color: #17201b;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .card {
            width: min(420px, 100%);
            padding: 32px 28px;
            border: 1px solid #dfe6e1;
            border-radius: 18px;
            background: #ffffff;
            text-align: center;
        }

        .card img {
            width: 180px;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 22px;
            color: #12562c;
        }

        p {
            margin: 0 0 22px;
            line-height: 1.55;
            color: #647067;
        }

        a {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 10px;
            background: #176b37;
            color: #ffffff;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="card">
        <img src="{{ asset('images/lumatek-logo.png') }}" alt="LumaTek">

        @if ($state === 'ios')
            <h1>Disponible para Android</h1>
            <p>
                Por ahora la app de LumaTek solo está disponible para Android.
                Mientras tanto, puedes usar LumaTek desde el navegador de tu iPhone.
            </p>
        @else
            <h1>Próximamente</h1>
            <p>
                La app de LumaTek para Android estará disponible muy pronto.
                Mientras tanto, puedes usar LumaTek desde el navegador.
            </p>
        @endif

        <a href="{{ route('login') }}">Entrar a LumaTek</a>
    </main>
</body>
</html>
