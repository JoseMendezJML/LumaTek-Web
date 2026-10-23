<?php

namespace App\Console\Commands;

use App\Services\MeasurementSummaries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildMeasurementSummaries extends Command
{
    protected $signature = 'history:summarize {--company= : Process only this company} {--chunk=500} {--batches=10} {--allow-persistent : Explicitly authorize this command to write to its configured persistent database}';

    protected $description = 'Incrementally build hourly and daily measurement statistics without deleting readings';

    public function handle(MeasurementSummaries $summaries): int
    {
        $connection = DB::connection();
        if (! $this->option('allow-persistent') && ! ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:')) {
            $this->error('Persistent database processing is disabled. Explicit target approval and --allow-persistent are required.');

            return self::FAILURE;
        }
        if (! ctype_digit((string) $this->option('chunk')) || ! ctype_digit((string) $this->option('batches'))
            || (int) $this->option('chunk') < 1 || (int) $this->option('chunk') > 500
            || (int) $this->option('batches') < 1 || (int) $this->option('batches') > 10000
            || ($this->option('company') !== null && (! ctype_digit((string) $this->option('company')) || (int) $this->option('company') < 1))) {
            $this->error('Invalid company or processing limits.');

            return self::FAILURE;
        }
        $query = DB::table('companies')->orderBy('id');
        if ($this->option('company') !== null) {
            $query->where('id', (int) $this->option('company'));
        }
        $count = 0;
        foreach ($query->lazyById(100) as $company) {
            $count += $summaries->process($company->id, (int) $this->option('chunk'), (int) $this->option('batches'))['processed_readings'];
        }
        $this->info('Processed readings: '.$count);

        return self::SUCCESS;
    }
}
