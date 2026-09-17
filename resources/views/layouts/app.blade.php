<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    <title>
        @yield('title', 'LumaTek')
    </title>

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
        href="{{ asset('css/panel.css') }}"
    >

    @stack('styles')
</head>

<body class="panel-body">

    <div class="app-shell">

        {{-- ================================
             MENÚ LATERAL
        ================================= --}}

        <aside class="sidebar">

            <div class="sidebar-brand">

                <img
                    src="{{ asset('images/lumatek-icon.png') }}"
                    alt="LumaTek"
                    class="sidebar-logo"
                >

                <div class="sidebar-brand-text">
                    <strong>LUMATEK</strong>
                    <span>Monitoreo inteligente</span>
                </div>

            </div>

            <nav class="sidebar-nav">

                <a
                    href="#"
                    class="sidebar-link"
                >
                    <span class="sidebar-icon">⌂</span>
                    <span>Inicio</span>
                </a>

                <a
                    href="{{ url('/greenhouses') }}"
                    class="sidebar-link"
                >
                    <span class="sidebar-icon">🌱</span>
                    <span>Invernaderos</span>
                </a>

                <a
                    href="#"
                    class="sidebar-link"
                >
                    <span class="sidebar-icon">◉</span>
                    <span>Sensores</span>
                </a>

                <a
                    href="#"
                    class="sidebar-link"
                >
                    <span class="sidebar-icon">⚠</span>
                    <span>Alertas</span>
                </a>

                <a
                    href="#"
                    class="sidebar-link"
                >
                    <span class="sidebar-icon">💧</span>
                    <span>Riego</span>
                </a>

                <a
                    href="#"
                    class="sidebar-link"
                >
                    <span class="sidebar-icon">▥</span>
                    <span>Reportes</span>
                </a>

            </nav>

            <div class="sidebar-footer">

                <span>
                    LumaTek
                </span>

                <small>
                    Tecnología inteligente para un cultivo eficiente.
                </small>

            </div>

        </aside>


        {{-- ================================
             CONTENIDO PRINCIPAL
        ================================= --}}

        <div class="main-area">

            {{-- BARRA SUPERIOR --}}

            <header class="topbar">

                <div class="topbar-left">

                    <button
                        type="button"
                        class="sidebar-toggle"
                        id="sidebar-toggle"
                        aria-label="Abrir o cerrar menú"
                    >
                        ☰
                    </button>

                    <div>

                        <h1 class="topbar-title">
                            @yield('page-title', 'LumaTek')
                        </h1>

                        <p class="topbar-subtitle">
                            @yield(
                                'page-subtitle',
                                'Gestión inteligente de tus invernaderos'
                            )
                        </p>

                    </div>

                </div>


                {{-- PERFIL --}}

                <div class="profile-menu">

                    <button
                        type="button"
                        class="profile-button"
                        id="profile-button"
                    >

                        <div class="profile-avatar">
                            <span id="profile-initial">
                                U
                            </span>
                        </div>

                        <div class="profile-info">

                            <strong id="profile-name">
                                Usuario
                            </strong>

                            <span id="profile-role">
                                Administrador
                            </span>

                        </div>

                        <span class="profile-arrow">
                            ▾
                        </span>

                    </button>


                    <div
                        class="profile-dropdown"
                        id="profile-dropdown"
                    >

                        <div class="profile-dropdown-header">

                            <strong id="dropdown-user-name">
                                Usuario
                            </strong>

                            <span id="dropdown-user-email">
                                usuario@correo.com
                            </span>

                        </div>

                        <div class="profile-dropdown-divider"></div>

                        <a
                            href="#"
                            class="profile-dropdown-link"
                        >
                            Mi perfil
                        </a>

                        <a
                            href="#"
                            class="profile-dropdown-link"
                        >
                            Configuración
                        </a>

                        <div class="profile-dropdown-divider"></div>

                        <button
                            type="button"
                            id="logout-button"
                            class="profile-dropdown-link logout-link"
                        >
                            Cerrar sesión
                        </button>

                    </div>

                </div>

            </header>


            {{-- ================================
                 CONTENIDO DE CADA PÁGINA
            ================================= --}}

            <main class="page-content">

                @yield('content')

            </main>

        </div>

    </div>


    {{-- ================================
         JAVASCRIPT GENERAL DEL PANEL
    ================================= --}}

    <script>

        /*
        |--------------------------------------------------------------------------
        | Verificar sesión
        |--------------------------------------------------------------------------
        */

        const accessToken =
            sessionStorage.getItem('lumatek_access_token');

        if (!accessToken) {
            window.location.href = '/login';
        }


        /*
        |--------------------------------------------------------------------------
        | Datos del usuario almacenados
        |--------------------------------------------------------------------------
        */

        const storedUser =
            sessionStorage.getItem('lumatek_user');

        if (storedUser) {

            try {

                const user =
                    JSON.parse(storedUser);

                const profileName =
                    document.getElementById(
                        'profile-name'
                    );

                const dropdownName =
                    document.getElementById(
                        'dropdown-user-name'
                    );

                const dropdownEmail =
                    document.getElementById(
                        'dropdown-user-email'
                    );

                const profileInitial =
                    document.getElementById(
                        'profile-initial'
                    );

                const profileRole =
                    document.getElementById(
                        'profile-role'
                    );


                if (profileName) {
                    profileName.textContent =
                        user.name ?? 'Usuario';
                }

                if (dropdownName) {
                    dropdownName.textContent =
                        user.name ?? 'Usuario';
                }

                if (dropdownEmail) {
                    dropdownEmail.textContent =
                        user.email ?? '';
                }

                if (
                    profileInitial &&
                    user.name
                ) {
                    profileInitial.textContent =
                        user.name
                            .charAt(0)
                            .toUpperCase();
                }

                if (profileRole) {

                    const roles = {
                        superadmin:
                            'Superadministrador',

                        company_admin:
                            'Administrador',

                        employee:
                            'Empleado'
                    };

                    profileRole.textContent =
                        roles[user.role]
                        ?? 'Usuario';
                }

            } catch (error) {

                console.error(
                    'No se pudieron cargar los datos del usuario.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Menú del perfil
        |--------------------------------------------------------------------------
        */

        const profileButton =
            document.getElementById(
                'profile-button'
            );

        const profileDropdown =
            document.getElementById(
                'profile-dropdown'
            );


        profileButton?.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

                profileDropdown.classList.toggle(
                    'show'
                );

            }
        );


        document.addEventListener(
            'click',
            function () {

                profileDropdown?.classList.remove(
                    'show'
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Menú lateral
        |--------------------------------------------------------------------------
        */

        const sidebarToggle =
            document.getElementById(
                'sidebar-toggle'
            );

        const appShell =
            document.querySelector(
                '.app-shell'
            );


        sidebarToggle?.addEventListener(
            'click',
            function () {

                appShell.classList.toggle(
                    'sidebar-collapsed'
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Cerrar sesión
        |--------------------------------------------------------------------------
        */

        const logoutButton =
            document.getElementById(
                'logout-button'
            );


        logoutButton?.addEventListener(
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

    </script>

    @stack('scripts')

</body>

</html>