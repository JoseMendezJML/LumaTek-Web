<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionQuote;
use App\Services\SubscriptionContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        return view('platform.subscriptions.index', [
            'quotes' => SubscriptionQuote::with(['company', 'requester', 'confirmation.contract'])
                ->orderByDesc('id')->paginate(15),
        ]);
    }

    public function show(string $quote): View
    {
        // Keep identifiers scalar: platform.admin must run before any quote lookup.
        return view('platform.subscriptions.show', [
            'quote' => SubscriptionQuote::with(['company', 'requester', 'confirmation.contract', 'confirmation.confirmer'])->findOrFail($quote),
        ]);
    }

    public function confirm(Request $request, string $quote, SubscriptionContractService $service): RedirectResponse
    {
        $quote = SubscriptionQuote::findOrFail($quote);
        $data = $request->validate([
            'medium' => ['required', 'string', 'max:100'], 'reference' => ['required', 'string', 'max:255'],
            'payment_verified' => ['required', 'accepted'],
        ], ['required' => 'El campo :attribute es obligatorio.', 'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute excede la longitud permitida.',
            'accepted' => 'Debes confirmar que verificaste el pago externo.'],
            ['medium' => 'medio', 'reference' => 'referencia', 'payment_verified' => 'verificación del pago externo']);
        try {
            $service->confirm($request->user(), $quote, $data['medium'], $data['reference']);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['confirmation' => isset($exception->errors()['quote'])
                ? 'La cotización venció. El propietario debe solicitar una nueva.'
                : 'No fue posible confirmar el periodo. Revisa la suscripción de la empresa antes de continuar.']);
        }

        return redirect()->route('platform.subscriptions.show', $quote->id)
            ->with('status', 'Confirmación manual registrada o recuperada. No se ha procesado ningún cobro desde LumaTek.');
    }
}
