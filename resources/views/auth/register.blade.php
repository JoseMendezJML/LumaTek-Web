@extends('layouts.auth')

@section('title', 'Crear cuenta | LumaTek')

@section('content')

<div class="auth-content">

    <div class="auth-header">
        <h1>Crear cuenta</h1>

        <p>
            Registra tu empresa y crea tu cuenta de
            administrador en LumaTek.
        </p>
    </div>

    <div
        id="register-message"
        class="auth-message"
        style="display: none;"
    ></div>

    <form id="register-form">

        {{-- Empresa --}}
        <div class="form-group">
            <label for="company_name">
                Nombre de la empresa
            </label>

            <input
                type="text"
                id="company_name"
                name="company_name"
                placeholder="Ej. Invernaderos del Sur"
                maxlength="150"
                required
            >
        </div>

        {{-- Responsable --}}
        <div class="form-group">
            <label for="name">
                Nombre del responsable
            </label>

            <input
                type="text"
                id="name"
                name="name"
                placeholder="Nombre completo"
                maxlength="120"
                autocomplete="name"
                required
            >
        </div>

        {{-- Correo --}}
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

        {{-- Contraseña --}}
        <div class="form-group">

            <label for="password">
                Contraseña
            </label>

            <div class="password-input">

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Crea una contraseña"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-target="password"
                >
                    Mostrar
                </button>

            </div>

            <div class="password-requirements">
                <span>La contraseña debe contener:</span>

                <ul>
                    <li>Mínimo 8 caracteres</li>
                    <li>Una letra mayúscula y una minúscula</li>
                    <li>Un número</li>
                    <li>Un símbolo</li>
                </ul>
            </div>

        </div>

        {{-- Confirmación --}}
        <div class="form-group">

            <label for="password_confirmation">
                Confirmar contraseña
            </label>

            <div class="password-input">

                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    placeholder="Repite tu contraseña"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-target="password_confirmation"
                >
                    Mostrar
                </button>

            </div>

        </div>

        <button
            type="submit"
            id="register-button"
            class="auth-primary-button"
        >
            Crear cuenta
        </button>

    </form>

    <div class="auth-footer">

        <span>
            ¿Ya tienes una cuenta?
        </span>

        <a href="{{ url('/login') }}">
            Iniciar sesión
        </a>

    </div>

</div>

@endsection


@push('styles')

<style>

    .password-requirements {
        margin-top: 10px;
        color: #6c776f;
        font-size: 12px;
        line-height: 1.5;
    }

    .password-requirements span {
        font-weight: 600;
        color: #536158;
    }

    .password-requirements ul {
        margin: 5px 0 0 18px;
        padding: 0;
    }

    .password-requirements li {
        margin-bottom: 2px;
    }

</style>

@endpush


@push('scripts')

<script>

    const registerForm =
        document.getElementById('register-form');

    const registerButton =
        document.getElementById('register-button');

    const registerMessage =
        document.getElementById('register-message');


    /*
    |--------------------------------------------------------------------------
    | Mostrar / ocultar contraseñas
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.password-toggle')
        .forEach(function (button) {

            button.addEventListener('click', function () {

                const targetId =
                    button.dataset.passwordTarget;

                const input =
                    document.getElementById(targetId);

                const hidden =
                    input.type === 'password';

                input.type =
                    hidden ? 'text' : 'password';

                button.textContent =
                    hidden ? 'Ocultar' : 'Mostrar';

            });

        });


    /*
    |--------------------------------------------------------------------------
    | Registro
    |--------------------------------------------------------------------------
    */

    registerForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            registerMessage.style.display = 'none';

            registerButton.disabled = true;
            registerButton.textContent =
                'Creando cuenta...';


            const companyName =
                document.getElementById(
                    'company_name'
                ).value;

            const name =
                document.getElementById(
                    'name'
                ).value;

            const email =
                document.getElementById(
                    'email'
                ).value;

            const password =
                document.getElementById(
                    'password'
                ).value;

            const passwordConfirmation =
                document.getElementById(
                    'password_confirmation'
                ).value;


            try {

                const response =
                    await fetch('/api/register', {

                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'
                        },

                        body: JSON.stringify({
                            company_name:
                                companyName,

                            name:
                                name,

                            email:
                                email,

                            password:
                                password,

                            password_confirmation:
                                passwordConfirmation
                        })

                    });


                const data =
                    await response.json();


                /*
                |--------------------------------------------------------------------------
                | Errores de validación
                |--------------------------------------------------------------------------
                */

                if (!response.ok) {

                    registerMessage.className =
                        'auth-message auth-message-error';

                    if (data.errors) {

                        const messages =
                            Object.values(
                                data.errors
                            ).flat();

                        registerMessage.innerHTML =
                            messages.join('<br>');

                    } else {

                        registerMessage.textContent =
                            data.message ??
                            'No fue posible crear la cuenta.';

                    }

                    registerMessage.style.display =
                        'block';

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Registro correcto
                |--------------------------------------------------------------------------
                */

                registerMessage.className =
                    'auth-message auth-message-success';

                registerMessage.textContent =
                    data.message;

                registerMessage.style.display =
                    'block';


                registerForm.reset();


            } catch (error) {

                registerMessage.className =
                    'auth-message auth-message-error';

                registerMessage.textContent =
                    'No fue posible conectar con el servidor.';

                registerMessage.style.display =
                    'block';

            } finally {

                registerButton.disabled = false;

                registerButton.textContent =
                    'Crear cuenta';

            }

        }
    );

</script>

@endpush