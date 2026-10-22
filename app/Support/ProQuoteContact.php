<?php

namespace App\Support;

use App\Models\Company;
use App\Models\SubscriptionQuote;

class ProQuoteContact
{
    public function mailto(SubscriptionQuote $quote, Company $company): ?string
    {
        $recipient = config('subscriptions.quote_contact_email');
        if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || $quote->company_id !== $company->id || $quote->confirmation
            || now()->greaterThanOrEqualTo($quote->expires_at)) {
            return null;
        }
        $subject = "LumaTek - Instrucciones de pago para cotización #{$quote->id}";
        $period = $quote->billing_period === 'monthly' ? 'Mensual' : 'Anual';
        $body = implode("\r\n", [
            'Estimado equipo de administración de LumaTek:', '',
            'Por este medio solicito información sobre los medios de pago disponibles y las instrucciones para completar la contratación o renovación de LumaTek Pro correspondiente a la siguiente cotización:', '',
            "Cotización: #{$quote->id}", "Empresa: {$company->name}", "Modalidad: {$period}",
            "Importe cotizado: {$quote->amount} {$quote->currency}", '',
            'Agradeceré que me indiquen los pasos a seguir para realizar el pago externo y solicitar su verificación manual dentro de la vigencia de la cotización.', '',
            'Quedo atento a sus indicaciones.', 'Atentamente,', 'Propietario de la empresa solicitante.',
        ]);

        return 'mailto:'.str_replace('%40', '@', rawurlencode($recipient)).'?'.http_build_query([
            'subject' => $subject, 'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
