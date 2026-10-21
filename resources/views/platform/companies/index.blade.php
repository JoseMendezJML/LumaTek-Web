@extends('layouts.platform')
@section('title', 'Empresas | LumaTek')
@section('content')
<h1>Empresas</h1>
<p class="platform-intro">Consulta las empresas registradas y administra su acceso a LumaTek.</p>
@if($errors->any())
    <div role="alert">{{ $errors->first() }}</div>
@endif
<form class="platform-filters" method="GET" action="{{ route('platform.companies.index') }}">
    <label>Buscar empresa o propietario
        <input type="search" name="q" maxlength="150" value="{{ $filters['q'] ?? '' }}">
    </label>
    <label>Estado
        <select name="status">
            <option value="">Todos</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activa</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Suspendida</option>
        </select>
    </label>
    <label>Plan efectivo
        <select name="plan_id">
            <option value="">Todos</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected((string)($filters['plan_id'] ?? '') === (string)$plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
    </label>
    <button class="platform-button" type="submit">Filtrar</button>
    <a href="{{ route('platform.companies.index') }}">Limpiar</a>
</form>
<div class="platform-card platform-table-wrap">
<table class="platform-table">
    <caption>{{ $companies->total() }} empresas encontradas</caption>
    <thead><tr><th>Empresa</th><th>Propietario</th><th>Plan efectivo</th><th>Estado</th><th>Consulta</th></tr></thead>
    <tbody>
    @forelse($companies as $company)
        @php($subscription = $company->activeSubscription)
        @php($effectivePlan = $subscription && !$subscription->isExpired() ? $subscription->plan : $freePlan)
        <tr>
            <td>{{ $company->name }}<br><small>{{ $company->email }}</small></td>
            <td>@forelse($company->owners as $owner)
                {{ $owner->name }}<br><small>{{ $owner->email }}</small><br>
                @empty Sin propietario registrado @endforelse</td>
            <td>{{ $effectivePlan?->name ?? 'Sin plan disponible' }}</td>
            <td>{{ $company->status === 'active' ? 'Activa' : 'Suspendida' }}</td>
            <td><a href="{{ route('platform.companies.show', $company) }}">Ver empresa</a></td>
        </tr>
    @empty
        <tr><td colspan="5">No hay empresas que coincidan con los filtros.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<nav class="platform-pagination" aria-label="Paginación de empresas">
    @if($companies->previousPageUrl())<a href="{{ $companies->previousPageUrl() }}">Anterior</a>@endif
    <span>Página {{ $companies->currentPage() }} de {{ $companies->lastPage() }}</span>
    @if($companies->hasMorePages())<a href="{{ $companies->nextPageUrl() }}">Siguiente</a>@endif
</nav>
@endsection
