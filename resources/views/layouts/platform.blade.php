<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Plataforma | LumaTek')</title>

    @include('partials.favicon')
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
        .platform-table-wrap { overflow-x: auto; }
        .platform-table { width: 100%; border-collapse: collapse; }
        .platform-table th, .platform-table td { text-align: left; padding: 12px; border-bottom: 1px solid #dfe8e2; vertical-align: top; }
        .platform-filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; margin-bottom: 24px; }
        .platform-filters label { display: grid; gap: 6px; }
        .platform-filters input, .platform-filters select { padding: 10px; border: 1px solid #dfe8e2; border-radius: 8px; max-width: 100%; }
        .platform-header nav a { margin-right: 18px; }
        .platform-pagination { display: flex; justify-content: space-between; margin-top: 24px; gap: 12px; }
        .platform-plan-form { display: grid; gap: 14px; }
        .platform-plan-form label { display: grid; gap: 6px; }
        .platform-plan-form input:not([type=checkbox]), .platform-plan-form select { width: 100%; padding: 10px; border: 1px solid #dfe8e2; border-radius: 8px; }
        .platform-plans { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 24px; }
        .platform-plans .platform-plan-card { min-width: 0; padding: 28px; }
        .platform-plans h2 { font-size: 24px; margin-bottom: 10px; }
        .platform-plans .platform-plan-description { color: #617168; min-height: 3.2em; margin: 0 0 22px; }
        .platform-plan-details { margin: 0; }
        .platform-plan-row { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); align-items: baseline; gap: 16px; padding: 13px 0; border-bottom: 1px solid #e5ece7; }
        .platform-plan-row dt { color: #52635a; font-size: 14px; line-height: 1.5; }
        .platform-plan-row dd { margin: 0; text-align: right; font-weight: 600; line-height: 1.5; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
        .platform-plan-price { padding: 14px; background: #eff7f1; border: 0; border-radius: 10px; margin-bottom: 8px; }
        .platform-plan-price dt { color: #176136; }
        .platform-plan-price dd { color: #176136; font-size: 18px; }
        .platform-plan-edit-title { margin: 28px 0 18px; padding-top: 24px; border-top: 1px solid #dfe8e2; font-size: 18px; color: #173d27; }
        .platform-plans .platform-plan-form { gap: 20px; }
        .platform-plan-prices { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 16px; }
        .platform-plans .platform-plan-form label { font-size: 14px; line-height: 1.5; gap: 8px; }
        .platform-plans .platform-plan-form input:not([type=checkbox]), .platform-plans .platform-plan-form select { min-width: 0; min-height: 44px; font: inherit; color: #26372d; background: #fff; }
        .platform-plans .platform-plan-form :is(input, select, button):focus-visible { outline: 2px solid #176136; outline-offset: 3px; }
        .platform-plans #annual-price-help { margin: 8px 0 0; font-size: 13px; color: #617168; }
        .platform-plans .platform-plan-confirmation { display: flex; align-items: flex-start; gap: 10px; padding: 14px; background: #f4f7f5; border-radius: 8px; }
        .platform-plan-confirmation input { flex: 0 0 auto; width: 18px; height: 18px; margin: 2px 0 0; accent-color: #176136; }
        .platform-plans .platform-plan-form button { justify-self: start; background: #176136; color: #fff; min-height: 44px; font: inherit; font-weight: 600; }
        .platform-plans .platform-plan-form button:hover { background: #124d2b; }
        .platform-plans .platform-plan-readonly { margin: 24px 0 0; padding: 16px; border-radius: 10px; background: #f4f7f5; color: #52635a; font-size: 14px; }
        @media(max-width: 760px) { .platform-plans { grid-template-columns: minmax(0, 1fr); } .platform-plans .platform-plan-description { min-height: 0; } }
        @media(max-width: 420px) { .platform-plans .platform-plan-card { padding: 20px; } .platform-plan-prices { grid-template-columns: minmax(0, 1fr); } .platform-plan-row { gap: 12px; } .platform-plan-price dd { font-size: 16px; } .platform-plans .platform-plan-form button { width: 100%; } }
        #platform-message { color: #9b2424; }
        @media(max-width: 600px) { .platform-header { padding: 16px; gap: 16px; } .platform-header nav { flex-basis: 100%; } }
    </style>
    @stack('styles')
</head>
<body>
    <header class="platform-header">
        <a href="{{ route('platform.dashboard') }}" aria-label="Inicio de plataforma">
            <img class="platform-logo" src="{{ asset('images/lumatek-logo.png') }}" alt="LumaTek">
        </a>
        <nav aria-label="Plataforma">
            <a href="{{ route('platform.dashboard') }}" @if(request()->routeIs('platform.dashboard')) aria-current="page" @endif>Inicio de plataforma</a>
            <a href="{{ route('platform.companies.index') }}" @if(request()->routeIs('platform.companies.*')) aria-current="page" @endif>Empresas</a>
            <a href="{{ route('platform.plans.index') }}" @if(request()->routeIs('platform.plans.*')) aria-current="page" @endif>Planes</a>
            <a href="{{ route('platform.subscriptions.index') }}" @if(request()->routeIs('platform.subscriptions.*')) aria-current="page" @endif>Solicitudes Pro</a>
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
