<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {

            $table->id();

            $table
                ->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table
                ->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();

            $table
                ->enum(
                    'status',
                    [
                        'active',
                        'inactive',
                        'cancelled',
                        'expired',
                    ]
                )
                ->default('active');

            $table
                ->timestamp('starts_at')
                ->nullable();

            $table
                ->timestamp('ends_at')
                ->nullable();

            $table
                ->boolean('auto_renew')
                ->default(false);

            $table->timestamps();

            $table
                ->index([
                    'company_id',
                    'status',
                ]);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};