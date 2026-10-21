<?php

namespace App\Services;

use App\Models\SubscriptionContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SubscriptionContractIntegrity
{
    public function assertSchema(): void
    {
        $required = ['id', 'subscription_id', 'company_id', 'plan_id', 'request_key', 'origin', 'amount', 'currency', 'billing_period', 'starts_at', 'ends_at', 'plan_name', 'plan_slug', ...SubscriptionContract::LIMITS, 'recorded_at'];
        if (! Schema::hasTable('subscription_contracts') || ! Schema::hasColumns('subscription_contracts', $required)) {
            throw new RuntimeException('Partial subscription_contracts schema. Restore/repair the schema before retrying; no historical rows will be replaced.');
        }
        $columns = collect(Schema::getColumns('subscription_contracts'))->keyBy('name');
        $nullable = ['request_key', 'amount', 'currency', 'billing_period', 'starts_at', 'ends_at'];
        foreach ($required as $name) {
            $column = $columns[$name];
            $type = strtolower($column['type_name']);
            $expected = in_array($name, ['id', 'subscription_id', 'company_id', 'plan_id', ...SubscriptionContract::LIMITS], true)
                ? '/int/' : (in_array($name, ['starts_at', 'ends_at', 'recorded_at'], true) ? '/timestamp|datetime/' : ($name === 'amount' ? '/decimal|numeric/' : '/char|text|enum/'));
            if ((bool) $column['nullable'] !== in_array($name, $nullable, true) || ! preg_match($expected, $type)) {
                throw new RuntimeException('Incompatible subscription_contracts column: '.$name.'. Repair explicitly before retrying.');
            }
        }
        $indexes = collect(Schema::getIndexes('subscription_contracts'));
        foreach (['id', 'subscription_id', 'request_key'] as $column) {
            if (! $indexes->contains(fn ($index) => $index['unique'] && $index['columns'] === [$column])) {
                throw new RuntimeException('Partial subscription_contracts schema: missing unique key '.$column.'.');
            }
        }
        $foreignKeys = collect(Schema::getForeignKeys('subscription_contracts'));
        foreach (['subscription_id' => 'subscriptions', 'company_id' => 'companies', 'plan_id' => 'plans'] as $column => $table) {
            if (! $foreignKeys->contains(fn ($key) => $key['columns'] === [$column] && $key['foreign_table'] === $table && $key['foreign_columns'] === ['id'] && strtolower($key['on_delete']) === 'restrict')) {
                throw new RuntimeException('Partial subscription_contracts schema: missing RESTRICT foreign key '.$column.'.');
            }
        }
    }

    // Read-only check; never invent a missing contract while serving a request.
    public function assertSubscriptions(?int $companyId = null, bool $allowMissing = false): void
    {
        $rows = DB::table('subscriptions as s')->leftJoin('subscription_contracts as c', 'c.subscription_id', '=', 's.id');
        if ($companyId !== null) {
            $rows->where('s.company_id', $companyId);
        }
        $invalid = (clone $rows)->whereNotNull('c.id')->where(function ($query) {
            foreach (['company_id', 'plan_id', 'starts_at', 'ends_at'] as $column) {
                $query->orWhereColumn('s.'.$column, '!=', 'c.'.$column)
                    ->orWhere(fn ($q) => $q->whereNull('s.'.$column)->whereNotNull('c.'.$column))
                    ->orWhere(fn ($q) => $q->whereNotNull('s.'.$column)->whereNull('c.'.$column));
            }
            $query->orWhere(fn ($q) => $q->whereIn('c.origin', ['legacy', 'compatibility'])->where(fn ($q) => $q->whereNotNull('c.amount')->orWhereNotNull('c.currency')->orWhereNotNull('c.billing_period')));
            $query->orWhere(fn ($q) => $q->whereIn('c.origin', ['purchase', 'renewal'])->where(fn ($q) => $q->whereNull('c.amount')->orWhere('c.amount', '<', 0)->orWhereNull('c.currency')->orWhere('c.currency', '!=', 'MXN')->orWhereNull('c.billing_period')->orWhereNotIn('c.billing_period', ['monthly', 'yearly'])));
        })->value('s.id');
        if ($invalid !== null) {
            throw new RuntimeException('Subscription contract integrity failed for subscription '.$invalid.'. Restore verified history; do not recapture current catalog values.');
        }
        if (! $allowMissing && ($missing = (clone $rows)->whereNull('c.id')->value('s.id')) !== null) {
            throw new RuntimeException('Subscription '.$missing.' has no contract. Access rules cannot be enabled until migration/integrity verification succeeds.');
        }
        if ($companyId === null && DB::table('subscription_contracts as c')->leftJoin('subscriptions as s', 's.id', '=', 'c.subscription_id')->whereNull('s.id')->exists()) {
            throw new RuntimeException('Orphan subscription contracts detected. Restore verified references before continuing.');
        }
    }

    public function ensureTriggers(bool $install = false): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Contract immutability requires SQLite or MySQL.');
        }
        $duplicate = 'EXISTS (SELECT 1 FROM subscription_contracts WHERE id = NEW.id OR subscription_id = NEW.subscription_id OR (NEW.request_key IS NOT NULL AND request_key = NEW.request_key))';
        $definitions = [];
        foreach (['UPDATE', 'DELETE'] as $operation) {
            $name = 'subscription_contracts_no_'.strtolower($operation);
            $definitions[$name] = $driver === 'sqlite'
                ? "CREATE TRIGGER {$name} BEFORE {$operation} ON subscription_contracts BEGIN SELECT RAISE(ABORT, 'Subscription contracts are immutable'); END"
                : "CREATE TRIGGER {$name} BEFORE {$operation} ON subscription_contracts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Subscription contracts are immutable'";
        }
        $name = 'subscription_contracts_no_replace';
        $definitions[$name] = $driver === 'sqlite'
            ? "CREATE TRIGGER {$name} BEFORE INSERT ON subscription_contracts WHEN {$duplicate} BEGIN SELECT RAISE(ABORT, 'Subscription contracts are immutable'); END"
            : "CREATE TRIGGER {$name} BEFORE INSERT ON subscription_contracts FOR EACH ROW BEGIN IF {$duplicate} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Subscription contracts are immutable'; END IF; END";
        $normalize = fn ($sql) => strtolower(preg_replace('/\s+/', ' ', trim($sql, " ;\n\r\t")));
        foreach ($definitions as $name => $definition) {
            if ($driver === 'sqlite') {
                $existing = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = ?", [$name]);
                $actual = $existing?->sql;
                $expected = $definition;
            } else {
                $existing = DB::selectOne('SELECT ACTION_STATEMENT AS body, EVENT_OBJECT_TABLE AS table_name, ACTION_TIMING AS timing, EVENT_MANIPULATION AS event_name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?', [$name]);
                $actual = $existing?->body;
                $expected = explode(' FOR EACH ROW ', $definition, 2)[1];
                if ($existing && ($existing->table_name !== 'subscription_contracts' || $existing->timing !== 'BEFORE' || $existing->event_name !== ($name === 'subscription_contracts_no_replace' ? 'INSERT' : strtoupper(substr($name, strrpos($name, '_') + 1))))) {
                    throw new RuntimeException('Conflicting contract trigger '.$name.'. Review it manually; it was not removed.');
                }
            }
            if ($actual !== null && $normalize($actual) !== $normalize($expected)) {
                throw new RuntimeException('Conflicting contract trigger '.$name.'. Review it manually; it was not removed.');
            }
            if ($actual === null) {
                if (! $install) {
                    throw new RuntimeException('Missing immutability trigger '.$name.'. Complete migration before enabling access.');
                }
                DB::unprepared($definition);
            }
        }
    }
}
