<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionContract;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionRestrictionSyncService
{
    public function sync(Company $company, bool $force = false): bool
    {
        return $company->withPlanRestrictionLock(function (?Plan $plan, string $identity) use ($company, $force) {
            if (! $plan) {
                throw new RuntimeException('No effective plan is available; restrictions were not synchronized.');
            }
            $fingerprint = hash('sha256', json_encode([
                'rules_version' => 1, 'identity' => $identity, 'limits' => $plan->only(SubscriptionContract::LIMITS),
            ], JSON_THROW_ON_ERROR));
            $state = DB::table('subscription_restriction_states')->where('company_id', $company->id)->first();
            if (! $force && $state?->fingerprint === $fingerprint) {
                return false;
            }

            // Sensors already synchronize zones and greenhouses recursively.
            $company->syncSensorPlanRestrictions();
            $company->syncUserPlanRestrictions();

            DB::table('subscription_restriction_states')->updateOrInsert(['company_id' => $company->id], [
                'fingerprint' => $fingerprint, 'synced_at' => now(),
            ]);

            return true;
        });
    }
}
