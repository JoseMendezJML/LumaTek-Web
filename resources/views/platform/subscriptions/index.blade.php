@extends('layouts.platform')
@section('title', 'Solicitudes Pro | LumaTek')
@push('styles')<link rel="stylesheet" href="{{ asset('css/subscriptions.css') }}">@endpush
@section('content')
<div class="subscriptions">
    <h1>Solicitudes Pro</h1>
    <p class="subscription-intro">Cotizaciones y verificaciones manuales de pagos externos. Este apartado no procesa cobros.</p>
    @include('subscription.partials.messages')
    <div class="subscription-card subscription-table-wrap">
        <table class="subscription-table">
            <caption>Cotizaciones de contratación y renovación</caption>
            <thead><tr><th scope="col">Cotización</th><th scope="col">Empresa y solicitante</th><th scope="col">Importe</th><th scope="col">Vencimiento</th><th scope="col">Estado</th></tr></thead>
            <tbody>
            @forelse($quotes as $quote)
                <tr>
                    <td><a href="{{ route('platform.subscriptions.show', $quote->id) }}">Cotización #{{ $quote->id }}</a><br>{{ $quote->billing_period === 'monthly' ? 'Mensual' : 'Anual' }}</td>
                    <td>{{ $quote->company->name }}<br>{{ $quote->requester->name }}<br>{{ $quote->requester->email }}</td>
                    <td>@include('subscription.partials.money', ['amount' => $quote->amount, 'currency' => $quote->currency])</td>
                    <td>{{ $quote->expires_at->format('d/m/Y H:i:s T') }}</td>
                    <td>@include('subscription.partials.status')
                        @if($quote->confirmation)<br>Contrato #{{ $quote->confirmation->contract->id }}@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No hay solicitudes Pro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('subscription.partials.pagination', ['paginator' => $quotes, 'label' => 'Páginas de solicitudes Pro'])
</div>
@endsection
