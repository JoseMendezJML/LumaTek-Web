@if($quote->terms_version === 'manual-pro-v1')
    <ul class="subscription-terms">
        <li>Precio y límites de esta cotización conservados hasta su vencimiento.</li>
        <li>El periodo comienza al confirmar, o al finalizar el último periodo Pro contratado si termina después.</li>
        <li>Mes o año calendario sin desbordamientos de fecha. El inicio está incluido y el fin está excluido.</li>
        <li>Se requiere verificación manual del pago externo. Solicitar una cotización no activa Pro.</li>
        <li>Sin cobros ni renovaciones automáticas.</li>
    </ul>
@else
    <p>Estas condiciones requieren revisión por soporte antes de continuar.</p>
@endif
