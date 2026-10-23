<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">

    <title>Iniciando sesión | LumaTek</title>

    @include('partials.favicon')
</head>
<body>

    <p>Iniciando sesión...</p>

    <script>

        /*
         * Igual que en el login normal: el panel usa
         * el JWT guardado en sessionStorage.
         */

        sessionStorage.setItem(
            'lumatek_access_token',
            @json($accessToken)
        );

        sessionStorage.setItem(
            'lumatek_user',
            JSON.stringify(@json($user))
        );

        window.location.replace(@json($redirectTo));

    </script>

</body>
</html>
