@extends('layouts.platform')
@section('title', 'Empresa | LumaTek')
@section('content')
<a href="{{ route('platform.companies.index') }}">Volver a empresas</a>
<h1>{{ $company->name }}</h1>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div class="platform-grid">
    <section class="platform-card">
        <h2>Datos generales</h2>
        <dl>
            <dt>Identificador</dt><dd>{{ $company->id }}</dd>
            <dt>Nombre legal</dt><dd>{{ $company->legal_name ?? 'Sin registrar' }}</dd>
            <dt>Correo</dt><dd>{{ $company->email }}</dd>
            <dt>Teléfono</dt><dd>{{ $company->phone ?? 'Sin registrar' }}</dd>
            <dt>Registro</dt><dd>{{ $company->created_at?->format('d/m/Y') }}</dd>
            <dt>Estado</dt><dd>{{ $company->status === 'active' ? 'Activa' : 'Suspendida' }}</dd>
        </dl>
    </section>
    <section class="platform-card">
        <h2>Propietario</h2>
        @forelse($company->owners as $owner)
            <p>{{ $owner->name }}<br>{{ $owner->email }}<br>{{ $owner->status === 'active' ? 'Cuenta activa' : 'Cuenta inactiva' }}</p>
        @empty
            <p>Sin propietario registrado.</p>
        @endforelse
        <h2>Plan efectivo</h2>
        <p>{{ $plan?->name ?? 'Sin plan disponible' }}</p>
        @if($company->activeSubscription?->ends_at)
            <p>Fin de la suscripción registrada: {{ $company->activeSubscription->ends_at->format('d/m/Y') }}</p>
        @endif
    </section>
</div>
<section class="platform-card" style="margin-top:24px">
    <h2>{{ $company->status === 'active' ? 'Suspender empresa' : 'Reactivar empresa' }}</h2>
    <p>La suspensión bloquea el acceso empresarial y revoca sus sesiónes y tokens. Conserva los datos, estados individuales de usuarios y suscripciónes.</p>
    <p>Al reactivar, los usuarios activos deberán iniciar sesión nuevamente.</p>
    <form method="POST" action="{{ route('platform.companies.status', $company) }}" onsubmit="return confirm('Confirma el cambio de acceso de esta empresa.');">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="{{ $company->status === 'active' ? 'inactive' : 'active' }}">
        <label><input type="checkbox" name="confirmed" value="1" required> Confirmo el cambio de estado de {{ $company->name }}.</label>
        <button class="platform-button" type="submit">{{ $company->status === 'active' ? 'Suspender empresa' : 'Reactivar empresa' }}</button>
    </form>
</section>
@endsection
