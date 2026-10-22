@extends('layouts.app')
@section('title', 'Mi suscripción | LumaTek')
@section('page-title', 'Mi suscripción')
@section('page-subtitle', 'Plan, vigencia y solicitudes Pro')
@push('styles')<link rel="stylesheet" href="{{ asset('css/subscriptions.css') }}">@endpush
@section('content')
<div class="subscriptions">
    <h1>Mi suscripción</h1>
    <p class="subscription-intro">Consulta tus condiciones y solicita Pro. LumaTek no procesa pagos: la contratación requiere verificación manual de un pago externo.</p>
    @include('subscription.partials.messages')
    <div class="subscription-grid">
        <section class="subscription-card" aria-labelledby="effective-plan-title">
            <h2 id="effective-plan-title">Plan efectivo: {{ $plan?->name ?? 'No disponible' }}</h2>
            @if($plan)
                @include('subscription.partials.limits', ['limits' => $plan])
            @else
                <p>No hay un plan efectivo disponible. Contacta a soporte.</p>
            @endif
            @if($current)
                <p>Vigencia actual: {{ $current->starts_at?->format('d/m/Y H:i:s T') ?? 'Inicio no registrado' }} — {{ $current->ends_at?->format('d/m/Y H:i:s T') ?? 'Sin fecha final registrada' }}.</p>
                @if($current->contract->billing_period)
                    <p>{{ $current->contract->billing_period === 'monthly' ? 'Mensual' : 'Anual' }} · @include('subscription.partials.money', ['amount' => $current->contract->amount, 'currency' => $current->contract->currency])</p>
                @endif
            @else
                <p>Sin periodo contratado vigente. Se aplican las condiciones de Gratis si está disponible.</p>
            @endif
        </section>
        <section class="subscription-card" aria-labelledby="quote-title">
            <h2 id="quote-title">Solicitar Pro</h2>
            <p>Precios actuales para nuevas cotizaciones:</p>
            <dl class="subscription-limits">
                <div><dt>Mensual</dt><dd>@include('subscription.partials.money', ['amount' => $pro?->price_monthly, 'currency' => $pro?->currency, 'unknown' => 'Sin configurar'])</dd></div>
                <div><dt>Anual</dt><dd>@include('subscription.partials.money', ['amount' => $pro?->price_yearly, 'currency' => $pro?->currency, 'unknown' => 'Sin configurar'])</dd></div>
            </dl>
            @if($pro?->is_active)
                <form class="subscription-form" method="POST" action="{{ route('subscription.quotes.store') }}">
                    @csrf
                    <input type="hidden" name="request_key" value="{{ $requestKey }}">
                    <label for="billing-period">Modalidad</label>
                    <select name="billing_period" id="billing-period" required>
                        @if($pro->price_monthly !== null)<option value="monthly" @selected(old('billing_period') === 'monthly')>Mensual</option>@endif
                        @if($pro->price_yearly !== null)<option value="yearly" @selected(old('billing_period') === 'yearly')>Anual</option>@endif
                    </select>
                    <p>La cotización conserva su precio y condiciones durante 48 horas. Al vencer, deberás solicitar otra. Puedes elegir otra modalidad para tu siguiente renovación.</p>
                    <button type="submit">Solicitar cotización</button>
                </form>
            @else
                <p>Pro no está disponible para nuevas cotizaciones. Las cotizaciones ya emitidas conservan su vigencia.</p>
            @endif
        </section>
    </div>
    <section class="subscription-card">
        <h2>Renovaciones programadas</h2>
        <p>Periodos ya confirmados que comenzarán en el futuro; no son renovaciones automáticas.</p>
        @forelse($renewals as $renewal)
            <article class="subscription-record">
                <h3>Periodo #{{ $renewal->contract->id }} · {{ $renewal->contract->billing_period === 'monthly' ? 'Mensual' : ($renewal->contract->billing_period === 'yearly' ? 'Anual' : 'Modalidad no registrada') }}</h3>
                <p>{{ $renewal->starts_at->format('d/m/Y H:i:s T') }} — {{ $renewal->ends_at?->format('d/m/Y H:i:s T') ?? 'Sin fecha final registrada' }}</p>
                <p>@include('subscription.partials.money', ['amount' => $renewal->contract->amount, 'currency' => $renewal->contract->currency])</p>
                <details><summary>Límites de este periodo</summary>@include('subscription.partials.limits', ['limits' => $renewal->contract])</details>
            </article>
        @empty
            <p>No tienes renovaciones programadas.</p>
        @endforelse
        @include('subscription.partials.pagination', ['paginator' => $renewals, 'label' => 'Páginas de renovaciones programadas'])
    </section>
    <section class="subscription-card">
        <h2>Mis cotizaciones</h2>
        <p>Solo se muestran las solicitudes que realizaste como propietario de esta empresa. Los estados se consultan al cargar esta página.</p>
        @forelse($quotes as $quote)
            <article class="subscription-record" id="quote-{{ $quote->id }}">
                <h3>Cotización #{{ $quote->id }} · {{ $quote->billing_period === 'monthly' ? 'Mensual' : 'Anual' }} @include('subscription.partials.status')</h3>
                <p class="subscription-price">@include('subscription.partials.money', ['amount' => $quote->amount, 'currency' => $quote->currency])</p>
                <p>Emitida: {{ $quote->issued_at->format('d/m/Y H:i:s T') }} · Vence: {{ $quote->expires_at->format('d/m/Y H:i:s T') }}</p>
                <details><summary>Condiciones y límites congelados</summary>
                    @include('subscription.partials.limits', ['limits' => $quote])
                    @include('subscription.partials.terms')
                </details>
                @if($quote->confirmation)
                    <p>Verificación manual registrada el {{ $quote->confirmation->confirmed_at->format('d/m/Y H:i:s T') }}. Contrato #{{ $quote->confirmation->contract->id }}: {{ $quote->confirmation->contract->starts_at?->format('d/m/Y H:i:s T') }} — {{ $quote->confirmation->contract->ends_at?->format('d/m/Y H:i:s T') }}.</p>
                @elseif(now()->greaterThanOrEqualTo($quote->expires_at))
                    <p>Vencida: solicita una nueva cotización para continuar.</p>
                @else
                    <p>Pendiente de verificación manual del pago externo. Esta solicitud no activa Pro.</p>
                    @if($contactLinks[$quote->id])
                        <a class="subscription-contact-button" href="{{ $contactLinks[$quote->id] }}" aria-describedby="contact-help-{{ $quote->id }}">Solicitar instrucciones por correo</a>
                        <p id="contact-help-{{ $quote->id }}">El botón prepara el correo en tu aplicación de correo. Revisa el mensaje y envíalo desde esa aplicación; LumaTek no lo envía automáticamente.</p>
                    @else
                        <p>El contacto por correo no está disponible. Solicita las instrucciones de pago a administración mediante tu canal de contacto habitual.</p>
                    @endif
                @endif
            </article>
        @empty
            <p>Aún no has solicitado cotizaciones.</p>
        @endforelse
        @include('subscription.partials.pagination', ['paginator' => $quotes, 'label' => 'Páginas de mis cotizaciones'])
    </section>
</div>
@endsection
