<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\SubscriptionRestrictionSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncSubscriptionRestrictions extends Command
{
    protected $signature = 'subscriptions:sync-restrictions {--batch=100 : Companies per batch (1-1000)} {--company= : Restrict to one company ID} {--force : Reapply even if the fingerprint is unchanged}';

    protected $description = 'Apply effective contracted plan restrictions without modifying subscription history';

    public function handle(SubscriptionRestrictionSyncService $service): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $companyId = $this->option('company');
        if ($batch === false || ($companyId !== null && filter_var($companyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            $this->error('Invalid batch size or company ID.');

            return self::FAILURE;
        }
        $updated = $skipped = $failed = 0;
        Company::query()->select('id')->when($companyId !== null, fn ($query) => $query->whereKey($companyId))
            ->chunkById($batch, function ($companies) use ($service, &$updated, &$skipped, &$failed) {
                foreach ($companies as $company) {
                    try {
                        if ($service->sync($company, (bool) $this->option('force'))) {
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error("Company {$company->id}: {$exception->getMessage()}");
                    }
                }
            });
        $this->info("Synchronized: {$updated}; unchanged: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
