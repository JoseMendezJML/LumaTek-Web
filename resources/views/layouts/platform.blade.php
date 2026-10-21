<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Plataforma | LumaTek')</title>
    <link rel="icon" href="{{ asset('images/lumatek-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7f5; color: #26372d; font-family: Arial, Helvetica, sans-serif; }
        .platform-header { background: white; border-bottom: 1px solid #dfe8e2; padding: 20px 32px; display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }
        .platform-logo { width: 150px; height: auto; }
        .platform-header nav { flex: 1; }
        .platform-header a { color: #176136; text-decoration: none; font-weight: 700; }
        .platform-button { border: 1px solid #176136; border-radius: 8px; background: white; color: #176136; padding: 10px 16px; cursor: pointer; }
        .platform-main { max-width: 1100px; margin: 40px auto; padding: 0 24px; }
        .platform-main h1 { color: #173d27; }
        .platform-intro { color: #617168; line-height: 1.6; }
        .platform-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 28px; }
        .platform-card { background: white; border: 1px solid #dfe8e2; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(20,60,40,.04); }
        .platform-card h2 { margin-top: 0; color: #176136; font-size: 18px; }
        .platform-card p { line-height: 1.6; }
        #platform-message { color: #9b2424; }
        @media(max-width: 600px) { .platform-header { padding: 16px; gap: 16px; } .platform-header nav { flex-basis: 100%; } }
    </style>
</head>
<body>
    <header class="platform-header">
        <a href="{{ route('platform.dashboard') }}" aria-label="Inicio de plataforma">
            <img class="platform-logo" src="{{ asset('images/lumatek-logo.png') }}" alt="LumaTek">
        </a>
        <nav aria-label="Plataforma">
            <a href="{{ route('platform.dashboard') }}" aria-current="page">Inicio de plataforma</a>
        </nav>
        <span>{{ auth()->user()->name }}</span>
        <form id="platform-logout" method="POST" action="{{ route('platform.logout') }}">
            @csrf
            <button class="platform-button" type="submit">Cerrar sesión</button>
        </form>
    </header>
    <main class="platform-main">
        <p id="platform-message" role="alert" hidden></p>
        @yield('content')
    </main>
    <script>
        document.getElementById('platform-logout').addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = document.getElementById('platform-message');
            try {
                const response = await fetch(event.currentTarget.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('Logout failed');
                sessionStorage.removeItem('lumatek_access_token');
                sessionStorage.removeItem('lumatek_user');
                window.location.href = @json(route('login'));
            } catch (error) {
                message.textContent = 'No fue posible cerrar la sesión. Intenta nuevamente.';
                message.hidden = false;
            }
        });
    </script>
</body>
</html>
