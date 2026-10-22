<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionContract;
use App\Models\SubscriptionManualConfirmation;
use App\Models\SubscriptionQuote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Manual external verification and persistence; no payment processing or auto-renewal.
class SubscriptionContractService
{
    public function create(Company $company, Plan $plan, string $period, string $startsAt, string $endsAt, string $requestKey, string $origin = 'purchase'): Subscription
    {
        return $this->persist($company, $plan, $period, $startsAt, $endsAt, $requestKey, $origin);
    }

    private function persist(Company $company, Plan $plan, string $period, string $startsAt, string $endsAt, string $requestKey, string $origin, ?SubscriptionQuote $quote = null): Subscription
    {
        Validator::make(compact('period', 'startsAt', 'endsAt', 'requestKey', 'origin'), [
            'period' => ['required', 'in:monthly,yearly'],
            'origin' => ['required', 'in:purchase,renewal'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'requestKey' => ['required', 'string', 'max:100'],
        ])->validate();

        return DB::transaction(function () use ($company, $plan, $period, $startsAt, $endsAt, $requestKey, $origin, $quote) {
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            app(SubscriptionContractIntegrity::class)->assertSubscriptions($company->id);
            $existing = SubscriptionContract::where('request_key', $requestKey)->first();
            if ($existing) {
                if ($existing->company_id !== $company->id || $existing->plan_id !== $plan->id
                    || $existing->billing_period !== $period || $existing->origin !== $origin
                    || ! $existing->starts_at->equalTo($startsAt) || ! $existing->ends_at->equalTo($endsAt)) {
                    throw ValidationException::withMessages(['requestKey' => __('subscriptions.different_terms')]);
                }

                return Subscription::whereKey($existing->subscription_id)->where('company_id', $company->id)->firstOrFail();
            }
            $plan = Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($company->status !== 'active' || (! $quote && ! $plan->is_active)) {
                throw ValidationException::withMessages(['plan' => __('subscriptions.inactive')]);
            }
            $overlap = $company->subscriptions()->where('status', 'active')
                ->whereHas('plan', fn ($catalog) => $catalog->where('slug', '!=', 'free'))
                ->where(fn ($dates) => $dates->whereNull('starts_at')->orWhere('starts_at', '<', $endsAt))
                ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['startsAt' => __('subscriptions.overlap')]);
            }
            $subscription = new Subscription([
                'company_id' => $company->id, 'plan_id' => $plan->id,
                'status' => 'active', 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'auto_renew' => false,
            ]);
            if ($quote) {
                $subscription->withQuoteContext($quote, $origin)->save();
            } else {
                $subscription->withContractContext($period, $origin, $requestKey)->save();
            }

            return $subscription->load('contract');
        });
    }

    public function quote(User $actor, string $period, string $requestKey): SubscriptionQuote
    {
        Validator::make(compact('period', 'requestKey'), [
            'period' => ['required', 'in:monthly,yearly'],
            'requestKey' => ['required', 'string', 'max:100'],
        ])->validate();

        return DB::transaction(function () use ($actor, $period, $requestKey) {
            $company = Company::whereKey($actor->company_id)->lockForUpdate()->first();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($company && $company->status === 'active' && $actor->company_id === $company->id
                && $actor->status === 'active' && $actor->is_company_owner && $actor->role?->name === 'company_admin', 403);
            $existing = SubscriptionQuote::where('company_id', $company->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                if ($existing->billing_period !== $period || $existing->requested_by !== $actor->id) {
                    throw ValidationException::withMessages(['request_key' => __('subscriptions.different_quote')]);
                }

                return $existing;
            }
            app(SubscriptionContractIntegrity::class)->assertSubscriptions($company->id);
            if ($this->proPeriods($company)->whereNull('ends_at')->exists()) {
                $this->unsafeStart();
            }
            $plan = Plan::where('slug', 'pro')->lockForUpdate()->firstOrFail();
            abort_unless($plan->is_active, 422, __('subscriptions.unavailable'));
            $amount = $plan->{$period === 'monthly' ? 'price_monthly' : 'price_yearly'};
            Validator::make(['amount' => $amount, 'currency' => $plan->currency], [
                'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'], 'currency' => ['required', 'in:MXN'],
            ])->validate();
            $issuedAt = now()->startOfSecond();

            return SubscriptionQuote::create([
                'company_id' => $company->id, 'requested_by' => $actor->id, 'plan_id' => $plan->id,
                'request_key' => $requestKey, 'billing_period' => $period, 'amount' => $amount, 'currency' => $plan->currency,
                'plan_name' => $plan->name, 'plan_slug' => $plan->slug, ...$plan->only(SubscriptionContract::LIMITS),
                'terms_version' => 'manual-pro-v1',
                'terms' => ['start' => 'max(confirmation_time,last_contracted_pro_end)', 'calendar' => 'no_overflow',
                    'interval' => '[start,end)', 'auto_renew' => false, 'confirmation' => 'external_manual_verification'],
                'issued_at' => $issuedAt, 'expires_at' => $issuedAt->copy()->addHours(48),
            ]);
        }, 3);
    }

    public function confirm(User $actor, SubscriptionQuote $quote, string $medium, string $reference): SubscriptionManualConfirmation
    {
        Validator::make(compact('medium', 'reference'), [
            'medium' => ['required', 'string', 'max:100'], 'reference' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($actor, $quote, $medium, $reference) {
            $company = Company::whereKey($quote->company_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->status === 'active' && $actor->role?->name === 'superadmin', 403);
            $quote = SubscriptionQuote::whereKey($quote->id)->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
            // Successful retries never recalculate dates or recheck quote expiration.
            if ($existing = $quote->confirmation()->first()) {
                return $existing->load('contract');
            }
            abort_unless($company->status === 'active', 403);
            $confirmedAt = now()->startOfSecond();
            if ($confirmedAt->greaterThanOrEqualTo($quote->expires_at)) {
                throw ValidationException::withMessages(['quote' => __('subscriptions.expired')]);
            }
            app(SubscriptionContractIntegrity::class)->assertSubscriptions($company->id);
            if ($this->proPeriods($company)->whereNull('ends_at')->exists()) {
                $this->unsafeStart();
            }
            $lastEnd = $this->proPeriods($company)->max('ends_at');
            $start = $lastEnd && $confirmedAt->lessThan($lastEnd) ? Carbon::parse($lastEnd) : $confirmedAt->copy();
            $end = $quote->billing_period === 'monthly' ? $start->copy()->addMonthNoOverflow() : $start->copy()->addYearNoOverflow();
            $origin = $this->proPeriods($company)->exists() ? 'renewal' : 'purchase';
            $subscription = $this->persist($company, Plan::findOrFail($quote->plan_id), $quote->billing_period,
                $start->toDateTimeString(), $end->toDateTimeString(), 'manual-quote:'.$quote->id, $origin, $quote);

            return SubscriptionManualConfirmation::create([
                'quote_id' => $quote->id, 'contract_id' => $subscription->contract->id,
                'confirmed_by' => $actor->id, 'confirmed_at' => $confirmedAt, 'medium' => $medium, 'reference' => $reference,
            ])->load('contract');
        }, 3);
    }

    private function proPeriods(Company $company): HasMany
    {
        // Include future and historical contracted periods, independent of mutable catalog data/status.
        return $company->subscriptions()->whereHas('contract', fn ($query) => $query->where('plan_slug', 'pro'));
    }

    private function unsafeStart(): never
    {
        throw ValidationException::withMessages(['subscription' => __('subscriptions.unsafe_start')]);
    }
}
