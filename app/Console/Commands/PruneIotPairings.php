<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PruneIotPairings extends Command
{
    protected $signature = 'iot:prune-pairings {--batch-size=500} {--batches=20} {--allow-persistent : Explicitly authorize deletion on the configured persistent database}';

    protected $description = 'Delete expired or consumed pairing challenges after their audit retention period';

    public function handle(): int
    {
        $days = config('iot_pairings.retention_days');
        if (! is_int($days) || $days < 1 || $days > 3650
            || ! ctype_digit((string) $this->option('batch-size')) || (int) $this->option('batch-size') < 1 || (int) $this->option('batch-size') > 1000
            || ! ctype_digit((string) $this->option('batches')) || (int) $this->option('batches') < 1 || (int) $this->option('batches') > 100) {
            $this->error('Invalid retention or batch limits. Retention must be at least one day.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        if (! $this->option('allow-persistent') && ! ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:')) {
            $this->error('Persistent database deletion requires target approval and --allow-persistent.');

            return self::FAILURE;
        }

        // Snapshot the cutoff once. Approval/exchange cannot use expired challenges;
        // consumed challenges are terminal, including those invalidated by revocation.
        $cutoff = now()->subDays($days);
        $eligible = fn (): Builder => $connection->table('iot_pairings')->where(function (Builder $query) use ($cutoff) {
            $query->where('consumed_at', '<', $cutoff)
                ->orWhere(fn (Builder $expired) => $expired->whereNull('consumed_at')->where('expires_at', '<', $cutoff));
        });
        $deleted = 0;
        for ($batch = 0; $batch < (int) $this->option('batches'); $batch++) {
            $ids = $eligible()->orderBy('id')->limit((int) $this->option('batch-size'))->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            // Recheck eligibility in the DELETE, rather than trusting an earlier SELECT.
            // Touch only challenge rows: never revoke or delete devices/credentials.
            $deleted += $eligible()->whereIn('id', $ids)->delete();
        }
        $this->info('Deleted pairing challenges: '.$deleted);

        return self::SUCCESS;
    }
}
