@extends('layouts.platform')
@section('title', 'Planes | LumaTek')
@section('content')
<h1>Planes</h1>
<p class="platform-intro">Los precios y la disponibilidad se aplican a nuevas contrataciones y renovaciones. Las suscripciones vigentes conservan sus condiciones hasta su vencimiento.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())
    <div role="alert"><p>No fue posible guardar los cambios:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="platform-grid platform-plans">
@foreach($plans as $plan)
    <section class="platform-card platform-plan-card">
        <h2>{{ $plan->name }}</h2>
        <p class="platform-plan-description">{{ $plan->description }}</p>
        <dl class="platform-plan-details">
            <div class="platform-plan-row platform-plan-price">
                <dt>Precio mensual</dt>
                <dd>${{ number_format((float) $plan->price_monthly, 2) }} {{ $plan->currency }}</dd>
            </div>
            <div class="platform-plan-row platform-plan-price">
                <dt>Precio anual</dt>
                <dd>{{ $plan->slug === 'free' ? '$0.00 '.$plan->currency : ($plan->price_yearly === null ? 'Sin configurar' : '$'.number_format((float) $plan->price_yearly, 2).' '.$plan->currency) }}</dd>
            </div>
            <div class="platform-plan-row">
                <dt>Disponibilidad</dt>
                <dd>{{ $plan->is_active ? 'Disponible' : 'No disponible para nuevas contrataciones' }}</dd>
            </div>
            <div class="platform-plan-row">
                <dt>Invernaderos</dt>
                <dd>{{ $plan->max_greenhouses }}</dd>
            </div>
            <div class="platform-plan-row">
                <dt>Zonas por invernadero</dt>
                <dd>{{ $plan->max_zones_per_greenhouse }}</dd>
            </div>
            <div class="platform-plan-row">
                <dt>Sensores por zona</dt>
                <dd>{{ $plan->max_sensors_per_zone }}</dd>
            </div>
            <div class="platform-plan-row">
                <dt>Usuarios</dt>
                <dd>{{ $plan->max_users }}</dd>
            </div>
            <div class="platform-plan-row">
                <dt>Administradores adicionales</dt>
                <dd>{{ $plan->max_additional_admins }}</dd>
            </div>
        </dl>
        @if($plan->slug === 'pro')
        <h3 class="platform-plan-edit-title">Editar Pro</h3>
        @php
            $monthly = old('price_monthly', $plan->price_monthly);
            $yearly = old('price_yearly', $plan->price_yearly);
            $oldAvailability = old('is_active', (int) $plan->is_active);
            $availability = in_array($oldAvailability, [0, 1, '0', '1'], true) ? (int) $oldAvailability : (int) $plan->is_active;
        @endphp
        <form class="platform-plan-form" method="POST" action="{{ route('platform.plans.update', $plan) }}" onsubmit="return confirm('Confirma los nuevos precios y disponibilidad de Pro. Las suscripciones vigentes conservarán sus condiciones.');">
            @csrf
            @method('PATCH')
            <div class="platform-plan-prices">
                <label>Precio mensual ({{ $plan->currency }})
                    <input name="price_monthly" type="number" min="0" max="99999999.99" step="0.01" required value="{{ is_scalar($monthly) ? $monthly : $plan->price_monthly }}">
                </label>
                <div class="platform-plan-annual-field">
                    <label>Precio anual ({{ $plan->currency }})
                        <input name="price_yearly" type="number" min="0" max="99999999.99" step="0.01" value="{{ is_scalar($yearly) || $yearly === null ? $yearly : $plan->price_yearly }}">
                    </label>
                </div>
            </div>
            <label>Disponibilidad para nuevas contrataciones
                <select name="is_active" required>
                    <option value="1" @selected($availability === 1)>Disponible</option>
                    <option value="0" @selected($availability === 0)>No disponible</option>
                </select>
            </label>
            <label class="platform-plan-confirmation"><input type="checkbox" name="confirmed" value="1" required> Confirmo los precios y la disponibilidad de Pro.</label>
            <button class="platform-button" type="submit">Guardar cambios de Pro</button>
        </form>
        @else
            <p class="platform-plan-readonly">Gratis mantiene precio cero y permanece disponible. No admite modificaciones desde este panel.</p>
        @endif
    </section>
@endforeach
</div>
@endsection
