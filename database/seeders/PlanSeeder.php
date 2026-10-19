<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            [
                'slug' => 'free',
            ],
            [
                'name' => 'Gratis',

                'description' =>
                    'Plan para empresas pequeñas que desean comenzar a monitorear sus invernaderos con LumaTek.',

                'price_monthly' => 0,

                'max_greenhouses' => 2,

                'max_zones_per_greenhouse' => 2,

                'max_sensors_per_zone' => 3,

                'max_users' => 3,

                'max_additional_admins' => 0,

                'is_active' => true,
            ]
        );


        Plan::updateOrCreate(
            [
                'slug' => 'pro',
            ],
            [
                'name' => 'Pro',

                'description' =>
                    'Plan para empresas que necesitan administrar más invernaderos, zonas, sensores y usuarios.',

                /*
                 * El precio se definirá más adelante.
                 * Por ahora permanece en 0 y no se mostrará
                 * como plan gratuito en la interfaz.
                 */
                'price_monthly' => 0,

                'max_greenhouses' => 10,

                'max_zones_per_greenhouse' => 10,

                'max_sensors_per_zone' => 10,

                'max_users' => 20,

                'max_additional_admins' => 2,

                'is_active' => true,
            ]
        );
    }
}