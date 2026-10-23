@extends('layouts.auth')
@section('title', 'Verificación de correo | LumaTek')
@section('content')
<div class="auth-content">
    <div class="auth-header"><h1>{{ in_array($state, ['verified', 'already_verified']) ? 'Correo verificado' : 'Revisa tu enlace' }}</h1></div>
    <p role="status">{{ $message }}</p>
    <a class="auth-primary-button" href="{{ route('login') }}">Iniciar sesión</a>
    @if(!in_array($state, ['verified', 'already_verified']))
        <div class="auth-footer"><a href="{{ route('verification.notice') }}">Solicitar otro correo de verificación</a></div>
    @endif
</div>
@endsection
