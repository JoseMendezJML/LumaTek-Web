<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'LumaTek')</title>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('images/lumatek-icon.png') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/app.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/auth.css') }}"
    >

    @stack('styles')
</head>

<body>

    <main class="auth-page">

        <section class="auth-container">

            <div class="auth-form-section">

                <div class="auth-logo">
                    <img
                        src="{{ asset('images/lumatek-logo.png') }}"
                        alt="LumaTek"
                    >
                </div>

                @yield('content')

            </div>

            <div class="auth-image-section">

                <img
                    src="{{ asset('images/login-greenhouse.jpg') }}"
                    alt="Invernadero LumaTek"
                >

                <div class="auth-image-overlay">

                    <div>
                        <h2>
                            Monitoreo inteligente
                        </h2>

                        <p>
                            Controla las condiciones de tus
                            invernaderos desde un solo lugar.
                        </p>
                    </div>

                </div>

            </div>

        </section>

    </main>

    @stack('scripts')

</body>
</html>