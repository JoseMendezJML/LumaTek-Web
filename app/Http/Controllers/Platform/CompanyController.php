<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:active,inactive'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $freePlan = Plan::where('slug', 'free')->where('is_active', true)->first();
        $query = Company::with(['owners', 'activeSubscription.contract'])->orderBy('name')->orderBy('id');
        if ($search = trim($filters['q'] ?? '')) {
            $query->where(function ($query) use ($search) {
                $pattern = '%'.$search.'%';
                $query->where('name', 'like', $pattern)->orWhere('legal_name', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)
                    ->orWhereHas('owners', fn ($owner) => $owner->where(fn ($fields) => $fields->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern)));
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($planId = $filters['plan_id'] ?? null) {
            $query->where(function ($query) use ($planId, $freePlan) {
                $query->whereHas('activeSubscription', fn ($subscription) => $subscription->where('plan_id', $planId));
                if ((int) $planId === $freePlan?->id) {
                    $query->orWhereDoesntHave('activeSubscription');
                }
            });
        }

        return view('platform.companies.index', [
            'companies' => $query->paginate(15)->withQueryString(),
            'plans' => Plan::orderBy('name')->get(),
            'freePlan' => $freePlan,
            'filters' => $filters,
        ]);
    }

    public function show(Company $company): View
    {
        $company->load(['owners', 'activeSubscription.contract']);

        return view('platform.companies.show', [
            'company' => $company,
            'plan' => $company->effectivePlan(),
        ]);
    }

    public function changeStatus(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
            'confirmed' => ['required', 'accepted'],
        ]);
        DB::transaction(function () use ($company, $data): void {
            $locked = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $data['status']) {
                return;
            }
            $locked->update(['status' => $data['status']]);
            // Revoke on both suspension and reactivation: previously-inactive
            // companies may still have credentials issued before revocation existed.
            // Preserve user statuses and independent platform credentials.
            User::where('company_id', $locked->id)
                ->whereHas('role', fn ($role) => $role->where('name', '!=', 'superadmin'))
                ->update(['auth_version' => (string) Str::uuid(), 'remember_token' => Str::random(60)]);
        });

        return redirect()->route('platform.companies.show', $company)
            ->with('status', $data['status'] === 'inactive' ? 'Empresa suspendida.' : 'Empresa reactivada. Los usuarios deben iniciar sesión nuevamente.');
    }
}
