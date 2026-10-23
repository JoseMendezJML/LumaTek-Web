@extends('layouts.auth')
@section('title', 'Verifica tu correo | LumaTek')
@section('content')
<div class="auth-content">
    <div class="auth-header"><h1>Verifica tu correo</h1><p>Abre el enlace del correo para activar el acceso. Revisa también spam. Si el envío falló o el enlace venció, solicita uno nuevo con tu correo y contraseña.</p></div>
    <p>Este formulario no inicia sesión ni crea otra cuenta. La recepción depende del proveedor de correo. Por seguridad, los envíos tienen límites por correo, usuario e IP.</p>
    <p id="verification-message" role="status" aria-live="polite"></p>
    <form id="verification-resend" autocomplete="on">
        <div class="form-group"><label for="verification-email">Correo electrónico</label><input id="verification-email" type="email" autocomplete="username" maxlength="150" required></div>
        <div class="form-group"><label for="verification-password">Contraseña</label><input id="verification-password" type="password" autocomplete="current-password" maxlength="1024" required></div>
        <button class="auth-primary-button" id="verification-button" type="submit">Reenviar correo de verificación</button>
    </form>
    <div class="auth-footer"><a href="{{ route('login') }}">Iniciar sesión</a><a href="{{ route('password.request') }}">Olvidé mi contraseña</a></div>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('verification-resend').addEventListener('submit', async function (event) {
    event.preventDefault();
    const button = document.getElementById('verification-button');
    if (button.disabled) return;
    const password = document.getElementById('verification-password');
    const payload = {email: document.getElementById('verification-email').value, password: password.value};
    password.value = '';
    const message = document.getElementById('verification-message');
    button.disabled = true;
    message.textContent = 'Solicitando el envío…';
    try {
        const response = await fetch(@json(route('verification.send', [], false)), {
            method: 'POST', credentials: 'omit', redirect: 'error', cache: 'no-store',
            headers: {'Content-Type': 'application/json', Accept: 'application/json'}, body: JSON.stringify(payload)
        });
        const data = await response.json();
        message.textContent = response.status === 429 ? 'Demasiados intentos. Espera antes de volver a solicitar un correo.' :
            response.status === 422 ? 'Ingresa un correo válido y tu contraseña.' : data.message || 'No se pudo solicitar el envío. Intenta más tarde.';
    } catch {
        message.textContent = 'No se pudo confirmar la solicitud. Intenta más tarde; no necesitas crear otra cuenta.';
    } finally {
        payload.password = '';
        button.disabled = false;
    }
});
</script>
@endpush
