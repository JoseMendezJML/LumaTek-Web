<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\AppDownloadQr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    private const LIMIT_LABELS = [
        'max_greenhouses' => 'Invernaderos',
        'max_zones_per_greenhouse' => 'Zonas por invernadero',
        'max_sensors_per_zone' => 'Sensores por zona',
        'max_users' => 'Usuarios por empresa',
        'max_additional_admins' => 'Administradores adicionales',
    ];

    public function __invoke(Request $request, AppDownloadQr $appQr): View
    {
        $plans = Plan::query()->whereIn('slug', ['free', 'pro'])->where('is_active', true)
            ->get(['name', 'slug', 'description', 'price_monthly', 'price_yearly', 'currency', ...array_keys(self::LIMIT_LABELS)])
            ->sortBy(fn (Plan $plan) => $plan->slug === 'free' ? 0 : 1)
            ->map(function (Plan $plan): array {
                $limits = [];
                foreach (self::LIMIT_LABELS as $field => $label) {
                    $limits[$label] = $plan->{$field} === null || $plan->{$field} < 0
                        ? 'Consultar' : (string) $plan->{$field};
                }

                return [
                    'slug' => $plan->slug,
                    'name' => filled($plan->name) ? $plan->name : ($plan->slug === 'free' ? 'Gratis' : 'Pro'),
                    'description' => $plan->description,
                    'monthly' => $this->price($plan->price_monthly, $plan->currency, $plan->slug === 'pro'),
                    'yearly' => $this->price($plan->price_yearly, $plan->currency, $plan->slug === 'pro'),
                    'limits' => $limits,
                ];
            });

        $user = $request->user();
        $isOwner = $user?->is_company_owner && $user->status === 'active'
            && $user->role?->name === 'company_admin' && $user->company?->status === 'active';

        return view('landing', [
            'plans' => $plans,
            'proAudience' => $isOwner ? 'owner' : ($user ? 'member' : 'guest'),
            'appQr' => $appQr,
        ]);
    }

    private function price(?string $amount, ?string $currency, bool $isPro): ?string
    {
        $currency = strtoupper(trim((string) $currency));
        if ($amount === null || ! is_numeric($amount) || ! preg_match('/^[A-Z]{3}$/D', $currency)
            || ($isPro ? (float) $amount <= 0 : (float) $amount < 0)) {
            return null;
        }

        if (! $isPro && (float) $amount === 0.0 && $currency === 'MXN') {
            return '$'.number_format((float) $amount, 0, '.', ',').' '.$currency;
        }

        return number_format((float) $amount, 2, '.', ',').' '.$currency;
    }
}
