<?php

namespace App\Models;

use App\Database\ImmutableQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class SubscriptionContract extends Model
{
    public $timestamps = false;

    private bool $capturing = false;

    protected function newBaseQueryBuilder()
    {
        return new ImmutableQueryBuilder($this->getConnection(), allowCapture: $this->capturing);
    }

    protected $guarded = ['id'];

    public const LIMITS = ['max_greenhouses', 'max_zones_per_greenhouse', 'max_sensors_per_zone', 'max_users', 'max_additional_admins'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'recorded_at' => 'datetime'];
    }

    public static function capture(Subscription $subscription, array $context = []): self
    {
        $subscription = Subscription::findOrFail($subscription->getKey());
        $plan = Plan::whereKey($subscription->plan_id)->lockForUpdate()->firstOrFail();
        $quote = isset($context['quote_id']) ? SubscriptionQuote::findOrFail($context['quote_id']) : null;
        if ($quote && ($quote->company_id !== $subscription->company_id || $quote->plan_id !== $subscription->plan_id
            || $quote->billing_period !== ($context['billing_period'] ?? null)
            || ($context['request_key'] ?? null) !== 'manual-quote:'.$quote->id)) {
            throw new \LogicException('Quote does not match subscription contract context.');
        }
        $source = $quote ?? $plan;
        $period = $context['billing_period'] ?? null;
        $amount = $quote ? $quote->amount : ($period ? $plan->{$period === 'monthly' ? 'price_monthly' : 'price_yearly'} : null);
        if ($period) {
            Validator::make(['amount' => $amount, 'currency' => $source->currency], [
                'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'currency' => ['required', 'in:MXN'],
            ])->validate();
        }

        $contract = new static([
            'subscription_id' => $subscription->id, 'company_id' => $subscription->company_id, 'plan_id' => $plan->id,
            'request_key' => $context['request_key'] ?? null,
            'origin' => $context['origin'] ?? 'compatibility',
            'amount' => $amount, 'currency' => $period ? $source->currency : null, 'billing_period' => $period,
            'starts_at' => $subscription->starts_at, 'ends_at' => $subscription->ends_at,
            'plan_name' => $quote ? $quote->plan_name : $plan->name, 'plan_slug' => $quote ? $quote->plan_slug : $plan->slug,
            ...$source->only(self::LIMITS), 'recorded_at' => now(),
        ]);
        $contract->capturing = true;
        try {
            $contract->save();
        } finally {
            $contract->capturing = false;
        }

        return $contract;
    }

    public function effectivePlan(): Plan
    {
        $plan = new ContractedPlan;
        $plan->setRawAttributes([
            'id' => $this->plan_id, 'name' => $this->plan_name, 'slug' => $this->plan_slug,
            'price_monthly' => $this->billing_period === 'monthly' ? $this->amount : null,
            'price_yearly' => $this->billing_period === 'yearly' ? $this->amount : null,
            'currency' => $this->currency, 'is_active' => true,
            ...$this->only(self::LIMITS),
        ], true);

        return $plan;
    }

    public function save(array $options = [])
    {
        if ($this->exists || ! $this->capturing) {
            throw new \LogicException('Subscription contracts are immutable.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new \LogicException('Subscription contracts are immutable.');
    }
}
