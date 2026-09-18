@extends('layouts.auth')

@section('title', 'Nueva contraseña | LumaTek')

@section('content')

<div class="auth-content">

    <div class="auth-header">
        <h1>Nueva contraseña</h1>

        <p>
            Crea una nueva contraseña para recuperar
            el acceso a tu cuenta de LumaTek.
        </p>
    </div>

    <div
        id="reset-message"
        class="auth-message"
        style="display: none;"
    ></div>

    <form id="reset-form">

        <input
            type="hidden"
            id="token"
            value="{{ $token }}"
        >

        <input
            type="hidden"
            id="email"
            value="{{ $email }}"
        >

        <div class="form-group">

            <label for="password">
                Nueva contraseña
            </label>

            <div class="password-input">

                <input
                    type="password"
                    id="password"
                    placeholder="Ingresa tu nueva contraseña"
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
                    <li>Una mayúscula y una minúscula</li>
                    <li>Un número</li>
                    <li>Un símbolo</li>
                </ul>
            </div>

        </div>

        <div class="form-group">

            <label for="password_confirmation">
                Confirmar contraseña
            </label>

            <div class="password-input">

                <input
                    type="password"
                    id="password_confirmation"
                    placeholder="Repite tu nueva contraseña"
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
            id="reset-button"
            class="auth-primary-button"
        >
            Actualizar contraseña
        </button>

    </form>

    <div class="auth-footer">
        <a href="{{ url('/login') }}">
            ← Volver a iniciar sesión
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

</style>

@endpush


@push('scripts')

<script>

    document
        .querySelectorAll('.password-toggle')
        .forEach(function (button) {

            button.addEventListener('click', function () {

                const input =
                    document.getElementById(
                        button.dataset.passwordTarget
                    );

                const hidden =
                    input.type === 'password';

                input.type =
                    hidden ? 'text' : 'password';

                button.textContent =
                    hidden ? 'Ocultar' : 'Mostrar';

            });

        });


    const resetForm =
        document.getElementById('reset-form');

    const resetButton =
        document.getElementById('reset-button');

    const resetMessage =
        document.getElementById('reset-message');


    resetForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            resetMessage.style.display = 'none';

            resetButton.disabled = true;
            resetButton.textContent =
                'Actualizando...';


            const response =
                await fetch('/api/password/reset', {

                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body: JSON.stringify({

                        token:
                            document.getElementById(
                                'token'
                            ).value,

                        email:
                            document.getElementById(
                                'email'
                            ).value,

                        password:
                            document.getElementById(
                                'password'
                            ).value,

                        password_confirmation:
                            document.getElementById(
                                'password_confirmation'
                            ).value

                    })

                });


            const data =
                await response.json();


            resetMessage.className =
                response.ok
                    ? 'auth-message auth-message-success'
                    : 'auth-message auth-message-error';


            if (data.errors) {

                resetMessage.innerHTML =
                    Object.values(
                        data.errors
                    ).flat().join('<br>');

            } else {

                resetMessage.textContent =
                    data.message;

            }


            resetMessage.style.display =
                'block';


            if (response.ok) {

                resetForm.reset();

                setTimeout(function () {

                    window.location.href =
                        '/login';

                }, 2500);

            }


            resetButton.disabled = false;

            resetButton.textContent =
                'Actualizar contraseña';

        }
    );

</script>

@endpush