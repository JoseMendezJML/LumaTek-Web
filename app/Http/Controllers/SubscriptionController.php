<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SubscriptionQuote;
use App\Services\SubscriptionContractService;
use App\Support\ProQuoteContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;
        $plan = $company->effectivePlan();
        $current = $company->activeSubscription()->with('contract')->first();
        $renewals = $company->subscriptions()->where('status', 'active')->where('starts_at', '>', now())
            ->whereHas('contract', fn ($query) => $query->where('plan_slug', 'pro'))
            ->with('contract')->orderBy('starts_at')->paginate(10, ['*'], 'renewals_page')->withQueryString();
        $quotes = SubscriptionQuote::where('company_id', $company->id)->where('requested_by', $request->user()->id)
            ->with('confirmation.contract')->orderByDesc('id')->paginate(10)->withQueryString();
        $oldKey = $request->old('request_key');
        $contact = app(ProQuoteContact::class);
        $contactLinks = $quotes->getCollection()->mapWithKeys(fn ($quote) => [$quote->id => $contact->mailto($quote, $company)]);

        return view('subscription.index', [
            'plan' => $plan, 'current' => $current, 'renewals' => $renewals, 'quotes' => $quotes,
            'contactLinks' => $contactLinks,
            'pro' => Plan::where('slug', 'pro')->first(),
            'requestKey' => is_string($oldKey) && Str::isUuid($oldKey) ? $oldKey : (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, SubscriptionContractService $service): RedirectResponse
    {
        $data = $request->validate([
            'billing_period' => ['required', 'in:monthly,yearly'], 'request_key' => ['required', 'uuid'],
        ], ['required' => 'El campo :attribute es obligatorio.', 'in' => 'Selecciona una modalidad válida.',
            'uuid' => 'La solicitud no es válida. Recarga la página e intenta de nuevo.']);
        try {
            $quote = $service->quote($request->user(), $data['billing_period'], $data['request_key']);
        } catch (ValidationException $exception) {
            $message = match (true) {
                isset($exception->errors()['subscription']) => 'Existe un periodo Pro sin fecha final. Contacta a soporte para revisar tu suscripción.',
                isset($exception->errors()['request_key']) => 'Esta solicitud ya identifica otra cotización. Recarga la página para solicitar una nueva.',
                default => 'No fue posible emitir la cotización con esa modalidad. Revisa los precios disponibles o contacta a soporte.',
            };
            throw ValidationException::withMessages(['subscription' => $message]);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }
            throw ValidationException::withMessages(['subscription' => 'Pro no está disponible para nuevas cotizaciones.']);
        }

        return redirect()->route('subscription.index')->with('status', "Cotización #{$quote->id} consultada o emitida. Solicitarla no activa Pro; requiere verificación manual del pago externo.");
    }
}
