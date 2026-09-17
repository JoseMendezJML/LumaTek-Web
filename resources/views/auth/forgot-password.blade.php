@extends('layouts.auth')

@section('title', 'Recuperar contraseña | LumaTek')

@section('content')

<div class="auth-content">

    <div class="auth-header">
        <h1>Recuperar contraseña</h1>

        <p>
            Ingresa el correo asociado a tu cuenta.
            Te enviaremos un enlace para crear una nueva contraseña.
        </p>
    </div>

    <div
        id="recovery-message"
        class="auth-message"
        style="display: none;"
    ></div>

    <form id="recovery-form">

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

        <button
            type="submit"
            id="recovery-button"
            class="auth-primary-button"
        >
            Enviar enlace de recuperación
        </button>

    </form>

    <div class="auth-footer">

        <a href="{{ url('/login') }}">
            ← Volver a iniciar sesión
        </a>

    </div>

</div>

@endsection


@push('scripts')

<script>

    const recoveryForm =
        document.getElementById('recovery-form');

    const recoveryButton =
        document.getElementById('recovery-button');

    const recoveryMessage =
        document.getElementById('recovery-message');


    recoveryForm.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            recoveryMessage.style.display = 'none';

            recoveryButton.disabled = true;
            recoveryButton.textContent = 'Enviando...';

            const email =
                document.getElementById('email').value;

            try {

                const response =
                    await fetch('/api/forgot-password', {

                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },

                        body: JSON.stringify({
                            email: email
                        })

                    });


                const data =
                    await response.json();


                if (!response.ok) {

                    recoveryMessage.className =
                        'auth-message auth-message-error';

                    if (data.errors) {

                        const messages =
                            Object.values(data.errors).flat();

                        recoveryMessage.innerHTML =
                            messages.join('<br>');

                    } else {

                        recoveryMessage.textContent =
                            data.message ??
                            'No fue posible procesar la solicitud.';

                    }

                    recoveryMessage.style.display =
                        'block';

                    return;
                }


                recoveryMessage.className =
                    'auth-message auth-message-success';

                recoveryMessage.textContent =
                    data.message;

                recoveryMessage.style.display =
                    'block';


                recoveryForm.reset();


            } catch (error) {

                recoveryMessage.className =
                    'auth-message auth-message-error';

                recoveryMessage.textContent =
                    'No fue posible conectar con el servidor.';

                recoveryMessage.style.display =
                    'block';

            } finally {

                recoveryButton.disabled = false;

                recoveryButton.textContent =
                    'Enviar enlace de recuperación';

            }

        }
    );

</script>

@endpush