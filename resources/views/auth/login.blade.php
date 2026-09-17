@extends('layouts.auth')

@section('title', 'Iniciar sesión | LumaTek')

@section('content')

<div class="auth-content">

    <div class="auth-header">
        <h1>Bienvenido</h1>

        <p>
            Inicia sesión para acceder al monitoreo
            de tus invernaderos.
        </p>
    </div>

    <div
        id="login-message"
        class="auth-message"
        style="display: none;"
    ></div>

    <form id="login-form">

        <div class="form-group">
            <label for="email">
                Correo electrónico
            </label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="ejemplo@correo.com"
                autocomplete="email"
                required
            >
        </div>

        <div class="form-group">
            <div class="password-label">
                <label for="password">
                    Contraseña
                </label>

                <a href="{{ url('/forgot-password') }}" class="forgot-link">
    ¿Olvidaste tu contraseña?
</a>
            </div>

            <div class="password-input">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    id="toggle-password"
                    class="password-toggle"
                    aria-label="Mostrar contraseña"
                >
                    Mostrar
                </button>
            </div>
        </div>

        <div class="form-options">

            <label class="remember-option">
                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                >

                <span>
                    Recordarme
                </span>
            </label>

        </div>

        <button
            type="submit"
            id="login-button"
            class="auth-primary-button"
        >
            Iniciar sesión
        </button>

    </form>

    <div class="auth-footer">
        <span>
            ¿Aún no tienes una cuenta?
        </span>

        <a href="{{ url('/register') }}">
            Crear cuenta
        </a>
    </div>

</div>

@endsection


@push('scripts')

<script>

    const loginForm = document.getElementById('login-form');
    const loginButton = document.getElementById('login-button');
    const loginMessage = document.getElementById('login-message');

    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('toggle-password');


    togglePassword.addEventListener('click', function () {

        const isPassword =
            passwordInput.type === 'password';

        passwordInput.type =
            isPassword ? 'text' : 'password';

        togglePassword.textContent =
            isPassword ? 'Ocultar' : 'Mostrar';

    });


    loginForm.addEventListener('submit', async function (event) {

        event.preventDefault();

        loginMessage.style.display = 'none';

        loginButton.disabled = true;
        loginButton.textContent = 'Iniciando sesión...';

        const email =
            document.getElementById('email').value;

        const password =
            document.getElementById('password').value;

        try {

            const response = await fetch('/api/login', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({
                    email: email,
                    password: password
                })

            });


            const data = await response.json();


            if (!response.ok) {

                loginMessage.className =
                    'auth-message auth-message-error';

                loginMessage.textContent =
                    data.message ??
                    'No fue posible iniciar sesión.';

                loginMessage.style.display = 'block';

                return;
            }


            /*
             * Por ahora guardaremos el token durante
             * la sesión del navegador.
             *
             * Más adelante conectaremos el dashboard
             * y definiremos el flujo definitivo de la web.
             */

            /*
|--------------------------------------------------------------------------
| Inicio de sesión correcto
|--------------------------------------------------------------------------
*/

sessionStorage.setItem(
    'lumatek_access_token',
    data.access_token
);

sessionStorage.setItem(
    'lumatek_user',
    JSON.stringify(data.user)
);

loginMessage.className =
    'auth-message auth-message-success';

loginMessage.textContent =
    'Inicio de sesión correcto.';

loginMessage.style.display =
    'block';
    
    setTimeout(function () {

    window.location.href =
        '/greenhouses';

}, 700);
            loginMessage.className =
                'auth-message auth-message-success';

            loginMessage.textContent =
                'Inicio de sesión correcto.';

            loginMessage.style.display = 'block';


            console.log(
                'Usuario autenticado:',
                data.user
            );


        } catch (error) {

            loginMessage.className =
                'auth-message auth-message-error';

            loginMessage.textContent =
                'No fue posible conectar con el servidor.';

            loginMessage.style.display = 'block';

        } finally {

            loginButton.disabled = false;
            loginButton.textContent = 'Iniciar sesión';

        }

    });

</script>

@endpush