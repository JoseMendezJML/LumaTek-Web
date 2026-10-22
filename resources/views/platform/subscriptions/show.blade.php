@extends('layouts.platform')
@section('title', 'Cotización #'.$quote->id.' | LumaTek')
@push('styles')<link rel="stylesheet" href="{{ asset('css/subscriptions.css') }}">@endpush
@section('content')
<div class="subscriptions">
    <a href="{{ route('platform.subscriptions.index') }}">Volver a Solicitudes Pro</a>
    <h1>Cotización #{{ $quote->id }}</h1>
    @include('subscription.partials.messages')
    <section class="subscription-card">
        <h2>{{ $quote->company->name }}</h2>
        <p>Solicitante: {{ $quote->requester->name }} · {{ $quote->requester->email }}</p>
        <p>{{ $quote->billing_period === 'monthly' ? 'Mensual' : 'Anual' }} · @include('subscription.partials.status')</p>
        <p class="subscription-price">@include('subscription.partials.money', ['amount' => $quote->amount, 'currency' => $quote->currency])</p>
        <p>Emitida: {{ $quote->issued_at->format('d/m/Y H:i:s T') }} · Vence: {{ $quote->expires_at->format('d/m/Y H:i:s T') }}</p>
        <h3>Condiciones y límites congelados</h3>
        @include('subscription.partials.limits', ['limits' => $quote])
        @include('subscription.partials.terms')
    </section>
    @if($quote->confirmation)
        <section class="subscription-card">
            <h2>Confirmación manual registrada</h2>
            <p>Verificada por {{ $quote->confirmation->confirmer->name }} el {{ $quote->confirmation->confirmed_at->format('d/m/Y H:i:s T') }}.</p>
            <dl class="subscription-limits">
                <div><dt>Medio externo</dt><dd>{{ $quote->confirmation->medium }}</dd></div>
                <div><dt>Referencia</dt><dd>{{ $quote->confirmation->reference }}</dd></div>
            </dl>
            <h3>Contrato #{{ $quote->confirmation->contract->id }}</h3>
            <p>{{ $quote->confirmation->contract->plan_name }} · @include('subscription.partials.money', ['amount' => $quote->confirmation->contract->amount, 'currency' => $quote->confirmation->contract->currency])</p>
            <p>Vigencia: {{ $quote->confirmation->contract->starts_at?->format('d/m/Y H:i:s T') }} — {{ $quote->confirmation->contract->ends_at?->format('d/m/Y H:i:s T') }}</p>
            @include('subscription.partials.limits', ['limits' => $quote->confirmation->contract])
            <p>La confirmación ya existe. No se requiere otra aprobación ni se ha procesado un cobro desde LumaTek.</p>
        </section>
    @elseif(now()->greaterThanOrEqualTo($quote->expires_at))
        <p class="subscription-notice">La cotización venció. El propietario debe solicitar una nueva; no puede confirmarse.</p>
    @elseif($quote->company->status !== 'active')
        <p class="subscription-notice">La empresa no está activa. No es posible confirmar esta cotización.</p>
    @else
        <section class="subscription-card">
            <h2>Verificar pago externo</h2>
            <p>Comprueba fuera de LumaTek el importe completo y la moneda de esta cotización antes de confirmar. Esta acción crea el periodo contratado y su registro histórico.</p>
            <form class="subscription-form" method="POST" action="{{ route('platform.subscriptions.confirm', $quote->id) }}">
                @csrf
                <label for="medium">Medio externo</label>
                <input id="medium" name="medium" maxlength="100" required value="{{ is_string(old('medium')) ? old('medium') : '' }}" placeholder="Por ejemplo: transferencia bancaria">
                <label for="reference">Referencia</label>
                <input id="reference" name="reference" maxlength="255" required value="{{ is_string(old('reference')) ? old('reference') : '' }}">
                <label class="subscription-checkbox"><input type="checkbox" name="payment_verified" value="1" required> Verifiqué el pago externo por el importe completo y la moneda de esta cotización.</label>
                <button type="submit">Registrar confirmación manual</button>
            </form>
        </section>
    @endif
</div>
@endsection
