<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionContract;
use App\Models\SubscriptionQuote;
use App\Support\ProQuoteContact;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mi suscripción" para la app móvil: la misma información que la página web
 * (SubscriptionController@index), en JSON. Solo lectura; las cotizaciones se
 * solicitan con POST /api/company/pro/quotes.
 */
class CompanySubscriptionController extends Controller
{
    private const LIST_LIMIT = 10;

    public function show(Request $request, ProQuoteContact $contact): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        $plan = $company->effectivePlan();
        $pro = Plan::where('slug', 'pro')->first();

        $current = $company->activeSubscription()->with('contract')->first();

        $renewals = $company->subscriptions()
            ->where('status', 'active')
            ->where('starts_at', '>', now())
            ->whereHas('contract', fn ($query) => $query->where('plan_slug', 'pro'))
            ->with('contract')
            ->orderBy('starts_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        // Igual que en la web: solo las cotizaciones de este propietario en su empresa.
        $quotes = SubscriptionQuote::where('company_id', $company->id)
            ->where('requested_by', $user->id)
            ->with('confirmation.contract')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        return response()->json([
            'plan' => $plan ? [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'limits' => $plan->only(SubscriptionContract::LIMITS),
            ] : null,
            'current' => $current ? $this->period($current) : null,
            'renewals' => $renewals->map(fn (Subscription $renewal) => $this->period($renewal))->values(),
            'pro' => [
                'available' => (bool) $pro?->is_active,
                'price_monthly' => $pro?->price_monthly,
                'price_yearly' => $pro?->price_yearly,
                'currency' => $pro?->currency,
            ],
            'quotes' => $quotes->map(fn (SubscriptionQuote $quote) => [
                'id' => $quote->id,
                'billing_period' => $quote->billing_period,
                'amount' => $quote->amount,
                'currency' => $quote->currency,
                'status' => $this->quoteStatus($quote),
                'issued_at' => $this->date($quote->issued_at),
                'expires_at' => $this->date($quote->expires_at),
                'contact_url' => $contact->mailto($quote, $company),
                'contract' => $quote->confirmation?->contract ? [
                    'starts_at' => $this->date($quote->confirmation->contract->starts_at),
                    'ends_at' => $this->date($quote->confirmation->contract->ends_at),
                ] : null,
            ])->values(),
        ]);
    }

    private function period(Subscription $subscription): array
    {
        return [
            'id' => $subscription->contract?->id,
            'plan_name' => $subscription->contract?->plan_name,
            'billing_period' => $subscription->contract?->billing_period,
            'amount' => $subscription->contract?->amount,
            'currency' => $subscription->contract?->currency,
            'starts_at' => $this->date($subscription->starts_at),
            'ends_at' => $this->date($subscription->ends_at),
        ];
    }

    /**
     * Misma regla que subscription/partials/status: la confirmación prevalece
     * aunque la cotización ya haya vencido.
     */
    private function quoteStatus(SubscriptionQuote $quote): string
    {
        return match (true) {
            $quote->confirmation !== null => 'confirmed',
            now()->greaterThanOrEqualTo($quote->expires_at) => 'expired',
            default => 'pending',
        };
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(config('app.timezone'))->toIso8601String();
    }
}
