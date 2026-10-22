<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperadmin extends Command
{
    protected $signature = 'platform:create-superadmin';

    protected $description = 'Crea el primer superadministrador desde una terminal interactiva autorizada.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error(__('platform.superadmin.interactive_required'));

            return self::FAILURE;
        }

        try {
            return $this->createInteractively();
        } catch (QueryException) {
            // La excepción SQL puede contener parámetros de la cuenta: no
            // mostrarla ni enviarla a los registros de la aplicación.
            $this->error(__('platform.superadmin.persistence_failed'));

            return self::FAILURE;
        }
    }

    private function createInteractively(): int
    {
        if (User::whereHas('role', fn ($query) => $query->where('name', 'superadmin'))->exists()) {
            $this->error(__('platform.superadmin.already_exists'));

            return self::FAILURE;
        }
        if (! Role::where('name', 'superadmin')->exists()) {
            $this->error(__('platform.superadmin.role_missing'));

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask(__('platform.superadmin.name'))),
            'email' => strtolower(trim((string) $this->ask(__('platform.superadmin.email')))),
            // No visible-input fallback, command-line password or default password.
            'password' => $this->secret(__('platform.superadmin.password'), false),
            'password_confirmation' => $this->secret(__('platform.superadmin.password_confirmation'), false),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', function ($attribute, $value, $fail): void {
                if ($this->emailExists($value)) {
                    $fail(__('platform.superadmin.email_exists'));
                }
            }],
            'password' => ['required', 'confirmed', Password::min(16)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        if (! $this->confirm(__('platform.superadmin.confirm', ['email' => $data['email']]), false)) {
            $this->info(__('platform.superadmin.cancelled'));

            return self::FAILURE;
        }

        $error = DB::transaction(function () use ($data): ?string {
            // Serialize concurrent bootstrap attempts on the existing role row.
            $role = Role::where('name', 'superadmin')->lockForUpdate()->first();
            if (! $role) {
                return __('platform.superadmin.role_missing');
            }
            if (User::where('role_id', $role->id)->exists()) {
                return __('platform.superadmin.already_exists');
            }
            if ($this->emailExists($data['email'])) {
                return __('platform.superadmin.email_exists');
            }
            $user = new User([
                'company_id' => null,
                'role_id' => $role->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'active',
                'is_company_owner' => false,
                'plan_restricted' => false,
            ]);
            $user->email_verified_at = now();
            $user->save();

            return null;
        });
        if ($error !== null) {
            $this->error($error);

            return self::FAILURE;
        }
        $this->info(__('platform.superadmin.created'));

        return self::SUCCESS;
    }

    private function emailExists(string $email): bool
    {
        // La identidad por correo no debe depender de la intercalación de la BD.
        return User::whereRaw('LOWER(email) = ?', [$email])->exists();
    }
}
