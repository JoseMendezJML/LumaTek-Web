<?php

namespace App\Console\Commands;

use App\Services\SubscriptionContractIntegrity;
use Illuminate\Console\Command;
use RuntimeException;

class VerifySubscriptionContracts extends Command
{
    protected $signature = 'subscriptions:verify-integrity';

    protected $description = 'Read-only verification of contract schema, coverage and immutability guards';

    public function handle(SubscriptionContractIntegrity $integrity): int
    {
        try {
            $integrity->assertSchema();
            $integrity->ensureTriggers();
            $integrity->assertSubscriptions();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Subscription contracts verified.');

        return self::SUCCESS;
    }
}
