<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'LumaTek')
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/panel.css') }}"
    >

    @stack('styles')

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f5;
            color: #26372d;
        }

        .app-layout {
            min-height: 100vh;
            display: flex;
        }

        /* =========================================================
           SIDEBAR
        ========================================================== */

        .app-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;

            width: 245px;

            display: flex;
            flex-direction: column;

            background: #153d2b;
            color: #ffffff;

            z-index: 1000;

            overflow-y: auto;
        }

        .sidebar-brand {
            min-height: 86px;

            display: flex;
            align-items: center;

            padding: 18px 20px;

            border-bottom:
                1px solid rgba(255, 255, 255, .10);
        }

        .sidebar-brand img {
            display: block;
            max-width: 170px;
            max-height: 52px;
            object-fit: contain;
        }

        /* =========================================================
           MENÚ
        ========================================================== */

        .sidebar-menu {
            flex: 1;
            padding: 18px 12px;
        }

        .sidebar-section-title {
            margin: 15px 12px 8px;

            color:
                rgba(255, 255, 255, .45);

            font-size: 10px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .sidebar-link {
            width: 100%;

            display: flex;
            align-items: center;
            gap: 11px;

            margin-bottom: 5px;

            padding: 11px 13px;

            border-radius: 9px;

            color:
                rgba(255, 255, 255, .82);

            text-decoration: none;

            font-size: 13px;
            font-weight: 600;

            transition:
                background .15s ease,
                color .15s ease;
        }

        .sidebar-link:hover {
            background:
                rgba(255, 255, 255, .10);

            color: #ffffff;
        }

        .sidebar-link.active {
            background:
                rgba(255, 255, 255, .16);

            color: #ffffff;
        }

        .sidebar-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-link.disabled {
            opacity: .45;
            cursor: default;
        }

        .sidebar-link.disabled:hover {
            background: transparent;
        }

        .menu-coming-soon {
            margin-left: auto;

            padding: 3px 6px;

            border-radius: 10px;

            background:
                rgba(255, 255, 255, .10);

            font-size: 8px;
        }

        /* =========================================================
           ÁREA PRINCIPAL
        ========================================================== */

        .app-main {
            width: calc(100% - 245px);
            min-height: 100vh;

            margin-left: 245px;
        }

        /* =========================================================
           TOPBAR
        ========================================================== */

        .app-topbar {
            min-height: 72px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding: 12px 28px;

            background: #ffffff;

            border-bottom:
                1px solid #e3ebe5;

            position: sticky;
            top: 0;

            z-index: 900;
        }

        .page-heading h1 {
            margin: 0;

            color: #173d27;

            font-size: 20px;
        }

        .page-heading p {
            margin: 4px 0 0;

            color: #7b867f;

            font-size: 11px;
        }

        /* =========================================================
           PERFIL
        ========================================================== */

        .profile-container {
            position: relative;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 7px 10px;

            border: 1px solid #e0e8e2;
            border-radius: 10px;

            background: #ffffff;

            cursor: pointer;
        }

        .profile-button:hover {
            background: #f6f9f7;
        }

        .profile-avatar {
            width: 34px;
            height: 34px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #e5f2e9;

            color: #176136;

            font-size: 13px;
            font-weight: 700;
        }

        .profile-info {
            text-align: left;
        }

        .profile-name {
            display: block;

            max-width: 170px;

            color: #314239;

            font-size: 11px;
            font-weight: 700;

            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-role {
            display: block;

            margin-top: 2px;

            color: #88918b;

            font-size: 9px;
        }

        .profile-arrow {
            color: #78827c;
            font-size: 10px;
        }

        /* =========================================================
           DROPDOWN
        ========================================================== */

        .profile-menu {
            position: absolute;

            top: calc(100% + 8px);
            right: 0;

            width: 220px;

            display: none;

            padding: 7px;

            border: 1px solid #e0e8e2;
            border-radius: 11px;

            background: #ffffff;

            box-shadow:
                0 10px 30px rgba(20, 60, 40, .10);

            z-index: 1100;
        }

        .profile-menu.show {
            display: block;
        }

        .profile-menu-header {
            padding: 10px 11px;

            border-bottom:
                1px solid #edf1ee;

            margin-bottom: 6px;
        }

        .profile-menu-header strong {
            display: block;

            color: #314239;

            font-size: 11px;
        }

        .profile-menu-header span {
            display: block;

            margin-top: 3px;

            color: #879088;

            font-size: 9px;

            overflow-wrap: anywhere;
        }

        .profile-menu-item {
            width: 100%;

            display: flex;
            align-items: center;
            gap: 9px;

            padding: 10px 11px;

            border: 0;
            border-radius: 8px;

            background: transparent;

            color: #435449;

            cursor: pointer;

            text-align: left;
            text-decoration: none;

            font-size: 11px;
        }

        .profile-menu-item:hover {
            background: #f4f8f5;
        }

        .profile-menu-item.logout {
            color: #a13232;
        }

        /* =========================================================
           CONTENIDO
        ========================================================== */

        .app-content {
            padding: 26px 28px 40px;
        }

        .mobile-menu-button {
            display: none;

            width: 39px;
            height: 39px;

            border: 1px solid #dce5de;
            border-radius: 9px;

            background: #ffffff;

            cursor: pointer;

            font-size: 18px;
        }

        .sidebar-overlay {
            display: none;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================== */

        @media (max-width: 900px) {

            .app-sidebar {
                transform: translateX(-100%);
                transition: transform .2s ease;
            }

            .app-sidebar.open {
                transform: translateX(0);
            }

            .app-main {
                width: 100%;
                margin-left: 0;
            }

            .mobile-menu-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .app-topbar {
                padding: 12px 17px;
            }

            .app-content {
                padding: 20px 16px 35px;
            }

            .page-heading {
                flex: 1;
            }

            .profile-info {
                display: none;
            }

            .profile-arrow {
                display: none;
            }

            .sidebar-overlay {
                position: fixed;
                inset: 0;

                z-index: 950;

                background:
                    rgba(0, 0, 0, .35);
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 600px) {

            .page-heading h1 {
                font-size: 17px;
            }

            .page-heading p {
                display: none;
            }
        }

    </style>

</head>

<body>

<div class="app-layout">

    {{-- =========================================================
         SIDEBAR
    ========================================================== --}}

    <aside
        id="app-sidebar"
        class="app-sidebar"
    >

        {{-- LOGO --}}

        <div class="sidebar-brand">

            <a href="{{ url('/dashboard') }}">

                <img
                    src="{{ asset('images/lumatek-logo.png') }}"
                    alt="LumaTek"
                >

            </a>

        </div>


        <nav class="sidebar-menu">

            <div class="sidebar-section-title">
                Monitoreo
            </div>


            {{-- DASHBOARD --}}

            <a
                href="{{ url('/dashboard') }}"
                class="
                    sidebar-link
                    {{ request()->is('dashboard*') ? 'active' : '' }}
                "
            >

                <span class="sidebar-icon">
                    📊
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            {{-- INVERNADEROS --}}

            <a
                href="{{ url('/greenhouses') }}"
                class="
                    sidebar-link
                    {{ request()->is('greenhouses*') ? 'active' : '' }}
                "
            >

                <span class="sidebar-icon">
                    🌱
                </span>

                <span>
                    Invernaderos
                </span>

            </a>


            {{-- ZONAS Y SENSORES --}}

            <a
                href="{{ url('/zones') }}"
                class="
                    sidebar-link
                    {{ request()->is('zones*') ? 'active' : '' }}
                "
            >

                <span class="sidebar-icon">
                    📡
                </span>

                <span>
                    Zonas y sensores
                </span>

            </a>


            {{-- ALERTAS --}}

            <a
                href="{{ url('/alerts') }}"
                class="
                    sidebar-link
                    {{ request()->is('alerts*') ? 'active' : '' }}
                "
            >

                <span class="sidebar-icon">
                    🚨
                </span>

                <span>
                    Alertas
                </span>

            </a>


            {{-- =================================================
                 GESTIÓN
            ================================================== --}}

            <div class="sidebar-section-title">
                Gestión
            </div>


            {{-- RIEGO --}}

            <a
                href="#"
                class="sidebar-link disabled"
                onclick="return false;"
            >

                <span class="sidebar-icon">
                    💧
                </span>

                <span>
                    Riego
                </span>

                <span class="menu-coming-soon">
                    Próximo
                </span>

            </a>


            {{-- HISTORIAL --}}

            <a
                href="#"
                class="sidebar-link disabled"
                onclick="return false;"
            >

                <span class="sidebar-icon">
                    📈
                </span>

                <span>
                    Historial
                </span>

                <span class="menu-coming-soon">
                    Próximo
                </span>

            </a>


            {{-- REPORTES --}}

            <a
                href="#"
                class="sidebar-link disabled"
                onclick="return false;"
            >

                <span class="sidebar-icon">
                    📄
                </span>

                <span>
                    Reportes
                </span>

                <span class="menu-coming-soon">
                    Próximo
                </span>

            </a>


            {{-- USUARIOS --}}

            <a
                href="#"
                class="sidebar-link disabled"
                onclick="return false;"
            >

                <span class="sidebar-icon">
                    👥
                </span>

                <span>
                    Usuarios
                </span>

                <span class="menu-coming-soon">
                    Próximo
                </span>

            </a>


            {{-- =================================================
                 SISTEMA
            ================================================== --}}

            <div class="sidebar-section-title">
                Sistema
            </div>


            <a
                href="#"
                class="sidebar-link disabled"
                onclick="return false;"
            >

                <span class="sidebar-icon">
                    ⚙️
                </span>

                <span>
                    Configuración
                </span>

            </a>

        </nav>

    </aside>


    <div
        id="sidebar-overlay"
        class="sidebar-overlay"
    ></div>


    {{-- =========================================================
         PRINCIPAL
    ========================================================== --}}

    <main class="app-main">

        <header class="app-topbar">

            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:13px;
                    min-width:0;
                    flex:1;
                "
            >

                <button
                    type="button"
                    id="mobile-menu-button"
                    class="mobile-menu-button"
                    aria-label="Abrir menú"
                >
                    ☰
                </button>


                <div class="page-heading">

                    <h1>
                        @yield(
                            'page-title',
                            'LumaTek'
                        )
                    </h1>

                    <p>
                        @yield(
                            'page-subtitle',
                            'Monitoreo inteligente de invernaderos'
                        )
                    </p>

                </div>

            </div>


            {{-- PERFIL --}}

            <div class="profile-container">

                <button
                    type="button"
                    id="profile-button"
                    class="profile-button"
                >

                    <div
                        id="profile-avatar"
                        class="profile-avatar"
                    >
                        U
                    </div>


                    <div class="profile-info">

                        <span
                            id="profile-name"
                            class="profile-name"
                        >
                            Usuario
                        </span>

                        <span
                            id="profile-role"
                            class="profile-role"
                        >
                            Administrador
                        </span>

                    </div>


                    <span class="profile-arrow">
                        ▼
                    </span>

                </button>


                <div
                    id="profile-menu"
                    class="profile-menu"
                >

                    <div class="profile-menu-header">

                        <strong id="profile-menu-name">
                            Usuario
                        </strong>

                        <span id="profile-menu-email">
                            usuario@lumatek.com
                        </span>

                    </div>


                    <button
                        type="button"
                        id="logout-button"
                        class="profile-menu-item logout"
                    >

                        <span>
                            ↪
                        </span>

                        Cerrar sesión

                    </button>

                </div>

            </div>

        </header>


        <section class="app-content">

            @yield('content')

        </section>

    </main>

</div>


<script>

    const appSidebar =
        document.getElementById(
            'app-sidebar'
        );


    const sidebarOverlay =
        document.getElementById(
            'sidebar-overlay'
        );


    const mobileMenuButton =
        document.getElementById(
            'mobile-menu-button'
        );


    const profileButton =
        document.getElementById(
            'profile-button'
        );


    const profileMenu =
        document.getElementById(
            'profile-menu'
        );


    const logoutButton =
        document.getElementById(
            'logout-button'
        );


    /*
    |--------------------------------------------------------------------------
    | USUARIO
    |--------------------------------------------------------------------------
    */

    function loadUserInformation() {

        const storedUser =
            sessionStorage.getItem(
                'lumatek_user'
            );


        if (!storedUser) {
            return;
        }


        try {

            const user =
                JSON.parse(
                    storedUser
                );


            const name =
                user.name
                ?? 'Usuario';


            const email =
                user.email
                ?? '';


            let role =
                user.role
                ?? user.role_name
                ?? 'Administrador';


            const roleLabels = {

                company_admin:
                    'Administrador',

                employee:
                    'Empleado',

                superadmin:
                    'Superadministrador'

            };


            role =
                roleLabels[role]
                ?? role;


            document.getElementById(
                'profile-name'
            ).textContent =
                name;


            document.getElementById(
                'profile-menu-name'
            ).textContent =
                name;


            document.getElementById(
                'profile-menu-email'
            ).textContent =
                email;


            document.getElementById(
                'profile-role'
            ).textContent =
                role;


            const initial =
                name
                    .trim()
                    .charAt(0)
                    .toUpperCase();


            document.getElementById(
                'profile-avatar'
            ).textContent =
                initial || 'U';


        } catch (error) {

            console.error(
                'No fue posible cargar la información del usuario.'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | PERFIL
    |--------------------------------------------------------------------------
    */

    profileButton.addEventListener(
        'click',
        function (event) {

            event.stopPropagation();


            profileMenu.classList.toggle(
                'show'
            );

        }
    );


    document.addEventListener(
        'click',
        function (event) {

            if (
                !profileMenu.contains(
                    event.target
                )
                &&
                !profileButton.contains(
                    event.target
                )
            ) {

                profileMenu.classList.remove(
                    'show'
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    logoutButton.addEventListener(
        'click',
        function () {

            sessionStorage.removeItem(
                'lumatek_access_token'
            );


            sessionStorage.removeItem(
                'lumatek_user'
            );


            window.location.href =
                '/login';

        }
    );


    /*
    |--------------------------------------------------------------------------
    | SIDEBAR MÓVIL
    |--------------------------------------------------------------------------
    */

    mobileMenuButton.addEventListener(
        'click',
        function () {

            appSidebar.classList.add(
                'open'
            );


            sidebarOverlay.classList.add(
                'show'
            );

        }
    );


    sidebarOverlay.addEventListener(
        'click',
        function () {

            appSidebar.classList.remove(
                'open'
            );


            sidebarOverlay.classList.remove(
                'show'
            );

        }
    );


    loadUserInformation();

</script>


@stack('scripts')

</body>

</html>