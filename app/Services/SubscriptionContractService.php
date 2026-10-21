<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Internal persistence boundary only: no checkout, payment, UI or auto-renewal.
class SubscriptionContractService
{
    public function create(Company $company, Plan $plan, string $period, string $startsAt, string $endsAt, string $requestKey, string $origin = 'purchase'): Subscription
    {
        Validator::make(compact('period', 'startsAt', 'endsAt', 'requestKey', 'origin'), [
            'period' => ['required', 'in:monthly,yearly'],
            'origin' => ['required', 'in:purchase,renewal'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'requestKey' => ['required', 'string', 'max:100'],
        ])->validate();

        return DB::transaction(function () use ($company, $plan, $period, $startsAt, $endsAt, $requestKey, $origin) {
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            app(SubscriptionContractIntegrity::class)->assertSubscriptions($company->id);
            $existing = SubscriptionContract::where('request_key', $requestKey)->first();
            if ($existing) {
                if ($existing->company_id !== $company->id || $existing->plan_id !== $plan->id
                    || $existing->billing_period !== $period || $existing->origin !== $origin
                    || ! $existing->starts_at->equalTo($startsAt) || ! $existing->ends_at->equalTo($endsAt)) {
                    throw ValidationException::withMessages(['requestKey' => 'The request key already identifies different contracted terms.']);
                }

                return Subscription::whereKey($existing->subscription_id)->where('company_id', $company->id)->firstOrFail();
            }
            $plan = Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($company->status !== 'active' || ! $plan->is_active) {
                throw ValidationException::withMessages(['plan' => 'The company and catalog plan must be active for a new contract.']);
            }
            $overlap = $company->subscriptions()->where('status', 'active')
                ->whereHas('plan', fn ($catalog) => $catalog->where('slug', '!=', 'free'))
                ->where(fn ($dates) => $dates->whereNull('starts_at')->orWhere('starts_at', '<', $endsAt))
                ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['startsAt' => 'The requested period overlaps an existing paid subscription.']);
            }
            $subscription = new Subscription([
                'company_id' => $company->id, 'plan_id' => $plan->id,
                'status' => 'active', 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'auto_renew' => false,
            ]);
            $subscription->withContractContext($period, $origin, $requestKey)->save();

            return $subscription->load('contract');
        });
    }
}
