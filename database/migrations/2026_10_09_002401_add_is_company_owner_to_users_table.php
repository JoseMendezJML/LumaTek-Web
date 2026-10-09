<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table
                ->boolean('is_company_owner')
                ->default(false)
                ->after('role_id');

        });


        /*
        |--------------------------------------------------------------------------
        | Asignar propietario a empresas existentes
        |--------------------------------------------------------------------------
        |
        | Para cada empresa se toma el primer administrador registrado.
        | Esto permite adaptar los datos actuales sin depender de un nombre
        | específico como "Juan Lara".
        |
        */

        $companyIds =
            DB::table('users')
                ->whereNotNull('company_id')
                ->distinct()
                ->pluck('company_id');


        foreach ($companyIds as $companyId) {

            $ownerId =
                DB::table('users')
                    ->join(
                        'roles',
                        'users.role_id',
                        '=',
                        'roles.id'
                    )
                    ->where(
                        'users.company_id',
                        $companyId
                    )
                    ->where(
                        'roles.name',
                        'company_admin'
                    )
                    ->orderBy('users.id')
                    ->value('users.id');


            if ($ownerId) {

                DB::table('users')
                    ->where('id', $ownerId)
                    ->update([
                        'is_company_owner' => true,
                    ]);

            }
        }
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn(
                'is_company_owner'
            );

        });
    }
};