@if($quote->confirmation)
    <span class="subscription-state">Confirmada</span>
@elseif(now()->greaterThanOrEqualTo($quote->expires_at))
    <span class="subscription-state subscription-state-expired">Vencida</span>
@else
    <span class="subscription-state subscription-state-pending">Pendiente</span>
@endif
