<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            throw new RuntimeException('Missing plans table; restore the base schema before migrating prices.');
        }
        $annual = Schema::hasColumn('plans', 'price_yearly');
        $currency = Schema::hasColumn('plans', 'currency');
        if ($annual !== $currency) {
            throw new RuntimeException('Partial plan pricing schema: price_yearly and currency must exist together. Repair explicitly before retrying; no values were changed.');
        }
        if ($annual) {
            $columns = collect(Schema::getColumns('plans'))->keyBy('name');
            if (! $columns['price_yearly']['nullable'] || ! preg_match('/decimal|numeric/', $columns['price_yearly']['type_name'])
                || $columns['currency']['nullable'] || ! preg_match('/char/', $columns['currency']['type_name'])) {
                throw new RuntimeException('Incompatible plan pricing schema; review column types and nullability before retrying.');
            }

            return;
        }
        Schema::table('plans', function (Blueprint $table) {
            // Unknown annual prices must not be inferred from monthly prices.
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->char('currency', 3)->default('MXN');
        });
    }

    public function down(): void
    {
        if (DB::table('plans')->whereNotNull('price_yearly')->orWhere('currency', '!=', 'MXN')->exists()) {
            throw new RuntimeException('Refusing to drop configured plan pricing. Use a reviewed forward recovery migration.');
        }
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn(['price_yearly', 'currency']));
    }
};
