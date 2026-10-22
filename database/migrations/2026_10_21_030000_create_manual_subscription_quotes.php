<?php

use App\Models\SubscriptionContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Manual subscription records require SQLite or MySQL.');
        }
        Schema::create('subscription_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('request_key', 100);
            $table->unique(['company_id', 'request_key']);
            $table->enum('billing_period', ['monthly', 'yearly']);
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('plan_name');
            $table->string('plan_slug');
            foreach (SubscriptionContract::LIMITS as $limit) {
                $table->unsignedSmallInteger($limit);
            }
            $table->string('terms_version');
            $table->json('terms');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->index(['company_id', 'expires_at']);
        });
        Schema::create('subscription_manual_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->unique()->constrained('subscription_quotes')->restrictOnDelete();
            $table->foreignId('contract_id')->unique()->constrained('subscription_contracts')->restrictOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->string('medium', 100);
            $table->string('reference', 255);
        });
        foreach (['subscription_quotes', 'subscription_manual_confirmations'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = $table.'_no_'.strtolower($operation);
                DB::unprepared($driver === 'sqlite'
                    ? "CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Manual subscription records are immutable'); END"
                    : "CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Manual subscription records are immutable'");
            }
            // Also reject REPLACE/upsert attempts against an existing identity.
            $collision = $table === 'subscription_quotes'
                ? 'id = NEW.id OR (company_id = NEW.company_id AND request_key = NEW.request_key)'
                : 'id = NEW.id OR quote_id = NEW.quote_id OR contract_id = NEW.contract_id';
            $exists = "EXISTS (SELECT 1 FROM {$table} WHERE {$collision})";
            DB::unprepared($driver === 'sqlite'
                ? "CREATE TRIGGER {$table}_no_replace BEFORE INSERT ON {$table} WHEN {$exists} BEGIN SELECT RAISE(ABORT, 'Manual subscription records are immutable'); END"
                : "CREATE TRIGGER {$table}_no_replace BEFORE INSERT ON {$table} FOR EACH ROW BEGIN IF {$exists} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Manual subscription records are immutable'; END IF; END");
        }
    }

    public function down(): void
    {
        foreach (['subscription_manual_confirmations', 'subscription_quotes'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Refusing to drop manual subscription history.');
            }
        }
        Schema::dropIfExists('subscription_manual_confirmations');
        Schema::dropIfExists('subscription_quotes');
    }
};
