<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlanController extends Controller
{
    private const AUDITED_FIELDS = ['price_monthly', 'price_yearly', 'currency', 'is_active'];

    public function index(): View
    {
        return view('platform.plans.index', ['plans' => Plan::whereIn('slug', ['free', 'pro'])->orderBy('id')->get()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($plan->slug === 'pro', 403);
        $price = ['numeric', 'min:0', 'max:99999999.99', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D'];
        $data = $request->validate([
            'price_monthly' => ['required', ...$price],
            'price_yearly' => ['present', 'nullable', ...$price],
            'is_active' => ['required', 'boolean'],
            'confirmed' => ['required', 'accepted'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'present' => 'Debes enviar el campo :attribute, aunque esté vacío.',
            'numeric' => 'El campo :attribute debe ser un importe numérico.',
            'min' => 'El campo :attribute no puede ser negativo.',
            'max' => 'El campo :attribute no puede superar 99999999.99.',
            'regex' => 'El campo :attribute debe tener como máximo dos decimales, separados por punto.',
            'boolean' => 'Selecciona una disponibilidad válida.',
            'accepted' => 'Debes confirmar los precios y la disponibilidad de Pro.',
        ], [
            'price_monthly' => 'precio mensual', 'price_yearly' => 'precio anual',
            'is_active' => 'disponibilidad', 'confirmed' => 'confirmación',
        ]);
        $changed = DB::transaction(function () use ($plan, $request, $data): bool {
            $locked = Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->slug === 'pro', 403);
            $before = $locked->only(self::AUDITED_FIELDS);
            $locked->fill([
                'price_monthly' => $data['price_monthly'],
                'price_yearly' => $data['price_yearly'],
                'is_active' => $data['is_active'],
            ]);
            $after = $locked->only(self::AUDITED_FIELDS);
            if ($before === $after) {
                return false;
            }
            $locked->save();
            PlanChange::create([
                'user_id' => $request->user()->id, 'plan_id' => $locked->id,
                'old_values' => $before, 'new_values' => $after, 'recorded_at' => now(),
            ]);

            return true;
        });

        return redirect()->route('platform.plans.index')->with('status', $changed
            ? 'Plan Pro actualizado. Las suscripciones vigentes conservan sus condiciones.'
            : 'No hay cambios para guardar.');
    }
}
