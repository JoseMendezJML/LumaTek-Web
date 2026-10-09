<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Acceso no permitido | LumaTek</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 24px;

            background: #f4f7f5;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #26372d;
        }

        .error-card {
            width: 100%;
            max-width: 520px;

            padding: 42px;

            border: 1px solid #dfe8e2;
            border-radius: 16px;

            background: #ffffff;

            text-align: center;

            box-shadow:
                0 10px 30px
                rgba(20, 60, 40, .08);
        }

        .error-code {
            margin: 0;

            color: #176136;

            font-size: 56px;
            font-weight: 700;
        }

        .error-card h1 {
            margin: 10px 0 12px;

            color: #173d27;

            font-size: 24px;
        }

        .error-card p {
            margin: 0 0 28px;

            color: #718078;

            font-size: 14px;
            line-height: 1.6;
        }

        .error-button {
            display: inline-block;

            padding: 12px 22px;

            border-radius: 8px;

            background: #176136;

            color: #ffffff;

            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .error-button:hover {
            background: #124e2b;
        }

    </style>

</head>

<body>

    <main class="error-card">

        <p class="error-code">
            403
        </p>

        <h1>
            Acceso no permitido
        </h1>

        <p>
            Tu cuenta no tiene permisos para acceder
            a esta sección de LumaTek.
        </p>

        <a
            href="{{ route('dashboard') }}"
            class="error-button"
        >
            Volver al resumen general
        </a>

    </main>

</body>

</html>