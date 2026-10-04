<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="LumaTek - Monitoreo inteligente para invernaderos."
    >

    <title>
        LumaTek | Monitoreo inteligente de invernaderos
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
    scroll-behavior: smooth;
    scroll-padding-top: 95px;
}

        body {
            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f6f8f6;
            color: #26372d;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }


        /* =========================================================
           VARIABLES VISUALES
        ========================================================== */

        :root {
            --green-dark: #123d28;
            --green: #176136;
            --green-soft: #eaf4ed;

            --text: #26372d;
            --text-soft: #6f7d74;

            --border: #dfe7e1;

            --white: #ffffff;

            --shadow:
                0 18px 50px
                rgba(18, 61, 40, .10);
        }


        /* =========================================================
           CONTENEDOR
        ========================================================== */

        .container {
            width: min(
                1180px,
                calc(100% - 40px)
            );

            margin: 0 auto;
        }


        /* =========================================================
           HEADER
        ========================================================== */

        .public-header {
            position: fixed;

            top: 0;
            left: 0;
            right: 0;

            z-index: 1000;

            background:
                rgba(255, 255, 255, .95);

            border-bottom:
                1px solid rgba(
                    220,
                    229,
                    222,
                    .85
                );

            backdrop-filter:
                blur(10px);
        }

        .navbar {
            min-height: 76px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 25px;
        }

        .brand-link {
            display: flex;
            align-items: center;
        }

        .brand-link img {
            width: 165px;
            max-height: 52px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            align-items: center;

            gap: 28px;
        }

        .nav-link {
            color: #4f6056;

            font-size: 13px;
            font-weight: 600;

            transition:
                color .15s ease;
        }

        .nav-link:hover {
            color: var(--green);
        }

        .nav-actions {
            display: flex;
            align-items: center;

            gap: 9px;
        }

        .nav-login {
            padding: 9px 14px;

            border-radius: 8px;

            color: var(--green-dark);

            font-size: 12px;
            font-weight: 700;
        }

        .nav-login:hover {
            background: #f1f6f2;
        }

        .nav-register {
            padding: 10px 15px;

            border-radius: 8px;

            background: var(--green);
            color: #ffffff;

            font-size: 12px;
            font-weight: 700;
        }

        .nav-register:hover {
            background: #124e2b;
        }

        .mobile-nav-button {
            display: none;

            width: 42px;
            height: 42px;

            border: 1px solid var(--border);
            border-radius: 9px;

            background: #ffffff;

            color: var(--green-dark);

            cursor: pointer;

            font-size: 20px;
        }


        /* =========================================================
           HERO
        ========================================================== */

        .hero {
            position: relative;

            min-height: 720px;

            display: flex;
            align-items: center;

            padding:
                130px 0
                80px;

            overflow: hidden;

            background:
                linear-gradient(
                    90deg,
                    rgba(10, 45, 28, .94) 0%,
                    rgba(14, 58, 35, .89) 42%,
                    rgba(13, 57, 34, .56) 70%,
                    rgba(12, 53, 33, .38) 100%
                ),
                url('{{ asset('images/login-greenhouse.jpg') }}');

            background-size: cover;
            background-position: center;
        }

        .hero-content {
            position: relative;
            z-index: 2;

            max-width: 680px;
        }

        .hero-eyebrow {
            display: inline-flex;

            margin-bottom: 18px;

            padding: 7px 12px;

            border:
                1px solid
                rgba(255, 255, 255, .25);

            border-radius: 20px;

            background:
                rgba(255, 255, 255, .10);

            color: #dcebe0;

            font-size: 11px;
            font-weight: 700;

            letter-spacing: .4px;
        }

        .hero h1 {
            margin: 0;

            color: #ffffff;

            font-size: clamp(
                42px,
                6vw,
                68px
            );

            line-height: 1.05;
            letter-spacing: -2px;
        }

        .hero h1 span {
            color: #a9d4b7;
        }

        .hero-description {
            max-width: 610px;

            margin:
                22px 0
                0;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .83
                );

            font-size: 17px;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;

            gap: 12px;

            margin-top: 30px;
        }

        .hero-primary {
            padding: 13px 19px;

            border-radius: 9px;

            background: #ffffff;
            color: var(--green-dark);

            font-size: 12px;
            font-weight: 700;
        }

        .hero-primary:hover {
            background: #eff6f1;
        }

        .hero-secondary {
            padding: 12px 19px;

            border:
                1px solid
                rgba(255, 255, 255, .34);

            border-radius: 9px;

            color: #ffffff;

            font-size: 12px;
            font-weight: 700;
        }

        .hero-secondary:hover {
            background:
                rgba(
                    255,
                    255,
                    255,
                    .10
                );
        }

        .hero-points {
            display: flex;
            flex-wrap: wrap;

            gap: 18px;

            margin-top: 35px;
        }

        .hero-point {
            display: flex;
            align-items: center;

            gap: 8px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .75
                );

            font-size: 11px;
        }

        .hero-point-mark {
            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: #8cc49d;
        }


        /* =========================================================
           SECCIONES
        ========================================================== */

        .section {
            padding: 90px 0;
        }

        .section-white {
            background: #ffffff;
        }

        .section-heading {
            max-width: 680px;

            margin:
                0 auto
                48px;

            text-align: center;
        }

        .section-label {
            display: inline-block;

            margin-bottom: 10px;

            color: var(--green);

            font-size: 11px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-heading h2 {
            margin: 0;

            color: var(--green-dark);

            font-size: clamp(
                28px,
                4vw,
                40px
            );

            line-height: 1.2;
        }

        .section-heading p {
            margin: 15px 0 0;

            color: var(--text-soft);

            font-size: 14px;
            line-height: 1.7;
        }


        /* =========================================================
           SOLUCIONES
        ========================================================== */

        .features-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap: 18px;
        }

        .feature-card {
            padding: 25px;

            border: 1px solid var(--border);
            border-radius: 14px;

            background: #ffffff;

            transition:
                transform .18s ease,
                box-shadow .18s ease;
        }

        .feature-card:hover {
            transform:
                translateY(-4px);

            box-shadow: var(--shadow);
        }

        .feature-icon {
            width: 43px;
            height: 43px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-bottom: 18px;

            border-radius: 11px;

            background: var(--green-soft);
            color: var(--green);

            font-size: 12px;
            font-weight: 800;
        }

        .feature-card h3 {
            margin: 0;

            color: var(--green-dark);

            font-size: 15px;
        }

        .feature-card p {
            margin: 10px 0 0;

            color: var(--text-soft);

            font-size: 11px;
            line-height: 1.65;
        }


        /* =========================================================
           CÓMO FUNCIONA
        ========================================================== */

        .process-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    4,
                    minmax(0, 1fr)
                );

            gap: 16px;
        }

        .process-card {
            padding: 22px;

            border-radius: 13px;

            background: #f4f8f5;
        }

        .process-number {
            display: block;

            margin-bottom: 13px;

            color: var(--green);

            font-size: 22px;
            font-weight: 800;
        }

        .process-card h3 {
            margin: 0;

            color: var(--green-dark);

            font-size: 14px;
        }

        .process-card p {
            margin: 8px 0 0;

            color: var(--text-soft);

            font-size: 10px;
            line-height: 1.6;
        }


        /* =========================================================
           PLANES
        ========================================================== */

        .plans-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 22px;

            max-width: 920px;

            margin: 0 auto;
        }

        .plan-card {
            position: relative;

            padding: 30px;

            border: 1px solid var(--border);
            border-radius: 16px;

            background: #ffffff;
        }

        .plan-card.pro {
            border:
                2px solid
                var(--green);

            box-shadow: var(--shadow);
        }

        .plan-featured {
            position: absolute;

            top: -13px;
            right: 22px;

            padding: 6px 10px;

            border-radius: 20px;

            background: var(--green);
            color: #ffffff;

            font-size: 9px;
            font-weight: 700;
        }

        .plan-name {
            color: var(--green-dark);

            font-size: 18px;
            font-weight: 800;
        }

        .plan-price {
            margin-top: 12px;

            color: var(--green);

            font-size: 30px;
            font-weight: 800;
        }

        .plan-price small {
            color: var(--text-soft);

            font-size: 11px;
            font-weight: 500;
        }

        .plan-description {
            margin-top: 11px;

            color: var(--text-soft);

            font-size: 11px;
            line-height: 1.6;
        }

        .plan-divider {
            height: 1px;

            margin: 22px 0;

            background: var(--border);
        }

        .plan-list {
            display: grid;

            gap: 12px;
        }

        .plan-item {
            display: flex;
            align-items: flex-start;

            gap: 9px;

            color: #4c5d53;

            font-size: 11px;
            line-height: 1.45;
        }

        .plan-check {
            flex: 0 0 auto;

            width: 18px;
            height: 18px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: var(--green-soft);
            color: var(--green);

            font-size: 10px;
            font-weight: 800;
        }

        .plan-button {
            width: 100%;

            display: block;

            margin-top: 25px;
            padding: 12px 15px;

            border:
                1px solid
                var(--green);

            border-radius: 9px;

            color: var(--green);

            text-align: center;

            font-size: 11px;
            font-weight: 700;
        }

        .plan-card.pro
        .plan-button {
            background: var(--green);
            color: #ffffff;
        }


        /* =========================================================
           LUMATEK LOCAL
        ========================================================== */

        .local-card {
            display: grid;

            grid-template-columns:
                1.2fr
                .8fr;

            align-items: center;

            gap: 35px;

            padding: 42px;

            border-radius: 18px;

            background: var(--green-dark);
            color: #ffffff;
        }

        .local-label {
            display: inline-flex;

            padding: 6px 10px;

            border-radius: 20px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .12
                );

            color: #b9dbc4;

            font-size: 9px;
            font-weight: 700;
        }

        .local-card h2 {
            margin: 15px 0 0;

            font-size: 32px;
        }

        .local-card p {
            margin: 13px 0 0;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .72
                );

            font-size: 12px;
            line-height: 1.7;
        }

        .local-status {
            display: flex;
            align-items: center;
            justify-content: center;

            min-height: 180px;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .15
                );

            border-radius: 15px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .06
                );
        }

        .local-status strong {
            color: #ffffff;

            font-size: 22px;
        }

        .local-status span {
            display: block;

            margin-top: 6px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .58
                );

            text-align: center;

            font-size: 10px;
        }


        /* =========================================================
           NOSOTROS
        ========================================================== */

        .about-grid {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr);

            gap: 50px;

            align-items: center;
        }

        .about-content h2 {
            margin: 0;

            color: var(--green-dark);

            font-size: 36px;
        }

        .about-content p {
            margin: 16px 0 0;

            color: var(--text-soft);

            font-size: 13px;
            line-height: 1.75;
        }

        .about-values {
            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 12px;
        }

        .about-value {
            padding: 18px;

            border: 1px solid var(--border);
            border-radius: 12px;

            background: #ffffff;
        }

        .about-value strong {
            display: block;

            color: var(--green);

            font-size: 13px;
        }

        .about-value span {
            display: block;

            margin-top: 6px;

            color: var(--text-soft);

            font-size: 10px;
            line-height: 1.5;
        }


        /* =========================================================
           CTA
        ========================================================== */

        .cta {
            padding: 80px 0;

            background: #eaf4ed;
        }

        .cta-box {
            max-width: 760px;

            margin: 0 auto;

            text-align: center;
        }

        .cta-box h2 {
            margin: 0;

            color: var(--green-dark);

            font-size: 34px;
        }

        .cta-box p {
            margin: 14px 0 0;

            color: var(--text-soft);

            font-size: 13px;
            line-height: 1.6;
        }

        .cta-actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;

            gap: 10px;

            margin-top: 24px;
        }

        .cta-primary {
            padding: 12px 17px;

            border-radius: 9px;

            background: var(--green);
            color: #ffffff;

            font-size: 11px;
            font-weight: 700;
        }

        .cta-secondary {
            padding: 11px 17px;

            border: 1px solid #b9cbbf;
            border-radius: 9px;

            background: #ffffff;
            color: var(--green-dark);

            font-size: 11px;
            font-weight: 700;
        }


        /* =========================================================
           FOOTER
        ========================================================== */

        .public-footer {
            padding: 45px 0 25px;

            background: #102e20;
            color: #ffffff;
        }

        .footer-grid {
            display: grid;

            grid-template-columns:
                1.5fr
                repeat(
                    2,
                    minmax(0, .7fr)
                );

            gap: 45px;
        }

        .footer-brand img {
    width: 165px;
    max-height: 55px;
    object-fit: contain;
}

        .footer-brand p {
            max-width: 360px;

            margin: 15px 0 0;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .60
                );

            font-size: 10px;
            line-height: 1.7;
        }

        .footer-column strong {
            display: block;

            margin-bottom: 12px;

            font-size: 11px;
        }

        .footer-column a {
            display: block;

            margin-top: 9px;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .62
                );

            font-size: 10px;
        }

        .footer-column a:hover {
            color: #ffffff;
        }

        .footer-bottom {
            margin-top: 35px;
            padding-top: 20px;

            border-top:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            color:
                rgba(
                    255,
                    255,
                    255,
                    .45
                );

            text-align: center;

            font-size: 9px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 950px) {

            .nav-links {
                display: none;
            }

            .mobile-nav-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .nav-actions {
                margin-left: auto;
            }

            .nav-links.mobile-open {
                position: absolute;

                top: 76px;
                left: 0;
                right: 0;

                display: flex;
                flex-direction: column;
                align-items: stretch;

                gap: 0;

                padding: 12px 20px;

                background: #ffffff;

                border-bottom:
                    1px solid
                    var(--border);
            }

            .nav-links.mobile-open
            .nav-link {
                padding: 12px 0;

                border-bottom:
                    1px solid #edf1ee;
            }

            .features-grid {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

            .process-grid {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

            .local-card {
                grid-template-columns: 1fr;
            }

            .about-grid {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 700px) {

            .container {
                width: min(
                    100% - 28px,
                    1180px
                );
            }

            .navbar {
                min-height: 68px;
            }

            .brand-link img {
                width: 140px;
            }

            .nav-register {
                display: none;
            }

            .hero {
                min-height: auto;

                padding:
                    120px 0
                    70px;
            }

            .hero h1 {
                letter-spacing: -1px;
            }

            .hero-description {
                font-size: 14px;
            }

            .section {
                padding: 65px 0;
            }

            .features-grid,
            .plans-grid,
            .process-grid,
            .about-values {
                grid-template-columns: 1fr;
            }

            .local-card {
                padding: 28px 22px;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }

        }

    </style>

</head>


<body>

{{-- =============================================================
     HEADER
============================================================= --}}

<header class="public-header">

    <div class="container">

        <nav class="navbar">

            <a
                href="#inicio"
                class="brand-link"
            >
                <img
                    src="{{ asset('images/lumatek-logo.png') }}"
                    alt="LumaTek"
                >
            </a>


            <div
                id="nav-links"
                class="nav-links"
            >

                <a
                    href="#inicio"
                    class="nav-link"
                >
                    Inicio
                </a>

                <a
                    href="#soluciones"
                    class="nav-link"
                >
                    Soluciones
                </a>

                <a
                    href="#planes"
                    class="nav-link"
                >
                    Planes
                </a>

                <a
                    href="#nosotros"
                    class="nav-link"
                >
                    Nosotros
                </a>

            </div>


            <div class="nav-actions">

                <a
                    href="{{ route('login') }}"
                    class="nav-login"
                >
                    Iniciar sesión
                </a>


                <a
                    href="{{ route('register') }}"
                    class="nav-register"
                >
                    Crear cuenta
                </a>


                <button
                    type="button"
                    id="mobile-nav-button"
                    class="mobile-nav-button"
                    aria-label="Abrir menú"
                >
                    ≡
                </button>

            </div>

        </nav>

    </div>

</header>


{{-- =============================================================
     HERO
============================================================= --}}

<section
    id="inicio"
    class="hero"
>

    <div class="container">

        <div class="hero-content">

            <span class="hero-eyebrow">
                Monitoreo inteligente para invernaderos
            </span>


            <h1>
                Cultivos más
                <span>
                    inteligentes
                </span>
                con LumaTek
            </h1>


            <p class="hero-description">
                Supervisa las condiciones de tus invernaderos,
                organiza zonas, sensores y dispositivos, recibe
                alertas y toma decisiones con información
                centralizada en una sola plataforma.
            </p>


            <div class="hero-actions">

                <a
                    href="{{ route('register') }}"
                    class="hero-primary"
                >
                    Comenzar gratis
                </a>


                <a
                    href="#soluciones"
                    class="hero-secondary"
                >
                    Conocer la plataforma
                </a>

            </div>


            <div class="hero-points">

                <div class="hero-point">
                    <span class="hero-point-mark"></span>
                    Monitoreo ambiental
                </div>

                <div class="hero-point">
                    <span class="hero-point-mark"></span>
                    Alertas
                </div>

                <div class="hero-point">
                    <span class="hero-point-mark"></span>
                    Gestión de riego
                </div>

            </div>

        </div>

    </div>

</section>


{{-- =============================================================
     SOLUCIONES
============================================================= --}}

<section
    id="soluciones"
    class="section section-white"
>

    <div class="container">

        <div class="section-heading">

            <span class="section-label">
                Soluciones
            </span>

            <h2>
                Información útil para gestionar
                mejor tus invernaderos
            </h2>

            <p>
                LumaTek concentra el monitoreo y la administración
                del cultivo para que puedas consultar el estado
                de cada invernadero desde una misma plataforma.
            </p>

        </div>


        <div class="features-grid">

            <article class="feature-card">

                <div class="feature-icon">
                    TMP
                </div>

                <h3>
                    Temperatura
                </h3>

                <p>
                    Consulta la temperatura registrada por los
                    sensores y compárala con los umbrales
                    configurados para el invernadero.
                </p>

            </article>


            <article class="feature-card">

                <div class="feature-icon">
                    HSU
                </div>

                <h3>
                    Humedad del suelo
                </h3>

                <p>
                    Supervisa el nivel de humedad del suelo para
                    detectar condiciones bajas, normales o altas
                    y apoyar las decisiones de riego.
                </p>

            </article>


            <article class="feature-card">

                <div class="feature-icon">
                    HAM
                </div>

                <h3>
                    Humedad ambiental
                </h3>

                <p>
                    Consulta las condiciones ambientales y detecta
                    valores fuera de los rangos establecidos.
                </p>

            </article>


            <article class="feature-card">

                <div class="feature-icon">
                    ALT
                </div>

                <h3>
                    Centro de alertas
                </h3>

                <p>
                    Identifica incidencias generadas por los
                    sensores y lleva seguimiento de alertas
                    activas, atendidas y resueltas.
                </p>

            </article>


            <article class="feature-card">

                <div class="feature-icon">
                    RGO
                </div>

                <h3>
                    Gestión de riego
                </h3>

                <p>
                    Registra riegos manuales y configura reglas
                    de riego automático de acuerdo con la humedad
                    del suelo.
                </p>

            </article>


            <article class="feature-card">

                <div class="feature-icon">
                    HIS
                </div>

                <h3>
                    Historial y gráficas
                </h3>

                <p>
                    Analiza las lecturas registradas mediante
                    historiales, filtros y representaciones
                    gráficas de las variables monitoreadas.
                </p>

            </article>

        </div>

    </div>

</section>


{{-- =============================================================
     FUNCIONAMIENTO
============================================================= --}}

<section class="section">

    <div class="container">

        <div class="section-heading">

            <span class="section-label">
                Funcionamiento
            </span>

            <h2>
                Una estructura preparada para crecer
                contigo
            </h2>

            <p>
                Desde la empresa hasta cada sensor,
                la información se organiza de manera
                clara y separada.
            </p>

        </div>


        <div class="process-grid">

            <article class="process-card">

                <span class="process-number">
                    01
                </span>

                <h3>
                    Registra tu empresa
                </h3>

                <p>
                    Crea la cuenta administrativa de tu empresa
                    para comenzar a trabajar en LumaTek.
                </p>

            </article>


            <article class="process-card">

                <span class="process-number">
                    02
                </span>

                <h3>
                    Configura invernaderos
                </h3>

                <p>
                    Organiza tus invernaderos y divide cada uno
                    en las zonas que necesites.
                </p>

            </article>


            <article class="process-card">

                <span class="process-number">
                    03
                </span>

                <h3>
                    Conecta dispositivos
                </h3>

                <p>
                    Asocia dispositivos y sensores para registrar
                    las variables del cultivo.
                </p>

            </article>


            <article class="process-card">

                <span class="process-number">
                    04
                </span>

                <h3>
                    Supervisa y actúa
                </h3>

                <p>
                    Consulta lecturas, alertas, historial y riego
                    desde el panel de monitoreo.
                </p>

            </article>

        </div>

    </div>

</section>


{{-- =============================================================
     PLANES
============================================================= --}}

<section
    id="planes"
    class="section section-white"
>

    <div class="container">

        <div class="section-heading">

            <span class="section-label">
                Planes
            </span>

            <h2>
                Empieza con lo esencial y crece
                cuando lo necesites
            </h2>

            <p>
                LumaTek contempla dos niveles para adaptarse
                a diferentes necesidades de monitoreo.
            </p>

        </div>


        <div class="plans-grid">

            {{-- GRATIS --}}

<article class="plan-card">

    <div class="plan-name">
        Gratis
    </div>

    <div class="plan-price">
        $0

        <small>
            MXN
        </small>
    </div>

    <p class="plan-description">
        Para comenzar a gestionar y monitorear
        un invernadero con LumaTek.
    </p>

    <div class="plan-divider"></div>

    <div class="plan-list">

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Gestión de invernaderos
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Organización por zonas
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Monitoreo de sensores
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Resumen general
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Centro de alertas
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Gestión de riego
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Historial y gráficas
        </div>

    </div>

    <a
        href="{{ route('register') }}"
        class="plan-button"
    >
        Crear cuenta gratis
    </a>

</article>


{{-- PRO --}}

<article class="plan-card pro">

    <span class="plan-featured">
        Próximamente
    </span>

    <div class="plan-name">
        Pro
    </div>

    <div class="plan-price">
        Próximamente
    </div>

    <p class="plan-description">
        Pensado para empresas que requieran
        mayor capacidad de administración,
        análisis y seguimiento.
    </p>

    <div class="plan-divider"></div>

    <div class="plan-list">

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Mayor capacidad de invernaderos
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Mayor capacidad de zonas y sensores
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Historial ampliado
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Reportes y exportación
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Más usuarios por empresa
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Funciones avanzadas de monitoreo
        </div>

        <div class="plan-item">
            <span class="plan-check">✓</span>
            Herramientas adicionales de gestión
        </div>

    </div>

    <span class="plan-button">
        Disponible próximamente
    </span>

</article>

        </div>

    </div>

</section>




{{-- =============================================================
     NOSOTROS
============================================================= --}}

<section
    id="nosotros"
    class="section section-white"
>

    <div class="container">

        <div class="about-grid">

            <div class="about-content">

                <span class="section-label">
                    Nosotros
                </span>

                <h2>
                    Tecnología para una gestión agrícola
                    más informada
                </h2>

                <p>
                    LumaTek nace como una plataforma orientada
                    al monitoreo y gestión de invernaderos,
                    integrando información de sensores,
                    alertas, riego e historial en un entorno
                    centralizado.
                </p>

                <p>
                    El sistema está diseñado con una arquitectura
                    multiempresa para mantener separada la
                    información de cada organización y permitir
                    que la plataforma pueda crecer de forma
                    ordenada.
                </p>

            </div>


            <div class="about-values">

                <article class="about-value">

                    <strong>
                        Monitoreo
                    </strong>

                    <span>
                        Información ambiental disponible
                        desde un mismo panel.
                    </span>

                </article>


                <article class="about-value">

                    <strong>
                        Organización
                    </strong>

                    <span>
                        Empresas, invernaderos, zonas,
                        dispositivos y sensores.
                    </span>

                </article>


                <article class="about-value">

                    <strong>
                        Seguridad
                    </strong>

                    <span>
                        Separación de información por empresa
                        y control de usuarios.
                    </span>

                </article>


                <article class="about-value">

                    <strong>
                        Escalabilidad
                    </strong>

                    <span>
                        Una base preparada para incorporar
                        nuevas funciones y servicios.
                    </span>

                </article>

            </div>

        </div>

    </div>

</section>


{{-- =============================================================
     CTA
============================================================= --}}

<section class="cta">

    <div class="container">

        <div class="cta-box">

            <h2>
                Comienza a organizar el monitoreo
                de tus invernaderos
            </h2>

            <p>
                Crea tu cuenta y accede a las herramientas
                de gestión y monitoreo disponibles en LumaTek.
            </p>


            <div class="cta-actions">

                <a
                    href="{{ route('register') }}"
                    class="cta-primary"
                >
                    Crear cuenta
                </a>


                <a
                    href="{{ route('login') }}"
                    class="cta-secondary"
                >
                    Ya tengo una cuenta
                </a>

            </div>

        </div>

    </div>

</section>


{{-- =============================================================
     FOOTER
============================================================= --}}

<footer class="public-footer">

    <div class="container">

        <div class="footer-grid">

            <div class="footer-brand">

                <img
                    src="{{ asset('images/lumatek-logo.png') }}"
                    alt="LumaTek"
                >

                <p>
                    Plataforma para el monitoreo inteligente
                    y la gestión de invernaderos.
                </p>

            </div>


            <div class="footer-column">

                <strong>
                    Plataforma
                </strong>

                <a href="#soluciones">
                    Soluciones
                </a>

                <a href="#planes">
                    Planes
                </a>

            </div>


            <div class="footer-column">

                <strong>
                    Cuenta
                </strong>

                <a href="{{ route('login') }}">
                    Iniciar sesión
                </a>

                <a href="{{ route('register') }}">
                    Crear cuenta
                </a>

            </div>

        </div>


        <div class="footer-bottom">

            © {{ date('Y') }} LumaTek.
            Monitoreo inteligente de invernaderos.

        </div>

    </div>

</footer>


{{-- =============================================================
     MENÚ MÓVIL
============================================================= --}}

<script>

    const mobileNavButton =
        document.getElementById(
            'mobile-nav-button'
        );


    const navLinks =
        document.getElementById(
            'nav-links'
        );


    mobileNavButton.addEventListener(
        'click',
        function () {

            navLinks.classList.toggle(
                'mobile-open'
            );

        }
    );


    navLinks
        .querySelectorAll('a')
        .forEach(
            link => {

                link.addEventListener(
                    'click',
                    function () {

                        navLinks
                            .classList
                            .remove(
                                'mobile-open'
                            );
                    }
                );

            }
        );

</script>

</body>

</html>