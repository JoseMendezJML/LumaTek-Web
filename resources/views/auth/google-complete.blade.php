@extends('layouts.auth')

@section('title', 'Completar registro | LumaTek')

@section('content')

<div class="auth-content">

    <div class="auth-header">
        <h1>Un paso más</h1>

        <p>
            Hola, {{ $name }}. Escribe el nombre de tu empresa
            para crear tu cuenta de administrador en LumaTek.
        </p>
    </div>

    @if ($errors->any())
        <div class="auth-message auth-message-error">
            {{ $errors->first() }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('google.complete.store') }}"
    >
        @csrf

        <div class="form-group">
            <label for="google_email">
                Correo electrónico
            </label>

            <input
                type="email"
                id="google_email"
                value="{{ $email }}"
                disabled
            >
        </div>

        <div class="form-group">
            <label for="company_name">
                Nombre de la empresa
            </label>

            <input
                type="text"
                id="company_name"
                name="company_name"
                value="{{ old('company_name') }}"
                placeholder="Ej. Invernaderos del Sur"
                maxlength="150"
                required
                autofocus
            >
        </div>

        <button
            type="submit"
            class="auth-primary-button"
        >
            Crear cuenta
        </button>

    </form>

    <div class="auth-back-home">
        <a href="{{ route('login') }}">
            ← Volver a iniciar sesión
        </a>
    </div>

</div>

@endsection
