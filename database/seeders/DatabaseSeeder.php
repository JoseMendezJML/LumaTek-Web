<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'name' => 'superadmin',
                'description' => 'Administrador general de la plataforma LumaTek.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'company_admin',
                'description' => 'Administrador de una empresa registrada en LumaTek.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'employee',
                'description' => 'Empleado con permisos limitados dentro de una empresa.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
