@extends('layouts.platform')

@section('title', 'Plataforma | LumaTek')

@section('content')
    <h1>Administración de plataforma</h1>
    <p class="platform-intro">Bienvenido, {{ auth()->user()->name }}. Este espacio está reservado para la administración de LumaTek.</p>
    <section class="platform-card" aria-labelledby="platform-access-title">
        <h2 id="platform-access-title">Cuenta de plataforma</h2>
        <p>{{ auth()->user()->email }}</p>
        <p>Rol: Superadministrador</p>
    </section>
    <div class="platform-grid">
        <section class="platform-card">
            <h2>Empresas y cuentas</h2>
            <p>La gestión global estará disponible próximamente.</p>
        </section>
        <section class="platform-card">
            <h2>Planes y suscripciones</h2>
            <p>La consulta y administración estarán disponibles próximamente.</p>
        </section>
    </div>
@endsection
