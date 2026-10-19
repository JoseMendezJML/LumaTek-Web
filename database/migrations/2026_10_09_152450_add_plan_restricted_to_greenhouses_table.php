<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('greenhouses', function (Blueprint $table) {
            $table
                ->boolean('plan_restricted')
                ->default(false)
                ->after('status');

            $table->index('plan_restricted');
        });
    }

    public function down(): void
    {
        Schema::table('greenhouses', function (Blueprint $table) {
            $table->dropIndex([
                'plan_restricted',
            ]);

            $table->dropColumn(
                'plan_restricted'
            );
        });
    }
};