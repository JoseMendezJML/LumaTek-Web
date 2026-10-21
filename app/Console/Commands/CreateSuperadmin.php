<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperadmin extends Command
{
    protected $signature = 'platform:create-superadmin';

    protected $description = 'Create the first platform account through a trusted interactive terminal.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('An interactive terminal is required.');

            return self::FAILURE;
        }
        if (User::whereHas('role', fn ($query) => $query->where('name', 'superadmin'))->exists()) {
            $this->error('A superadmin already exists. This bootstrap command cannot create another.');

            return self::FAILURE;
        }
        if (! Role::where('name', 'superadmin')->exists()) {
            $this->error('The superadmin role must already exist.');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Name')),
            'email' => strtolower(trim((string) $this->ask('Email'))),
            // No visible-input fallback, command-line password or default password.
            'password' => $this->secret('Password (16+ characters)', false),
            'password_confirmation' => $this->secret('Confirm password', false),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(16)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        if (! $this->confirm('I verified this email belongs to the authorized platform operator. Create the first superadmin?', false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($data): void {
            // Serialize concurrent bootstrap attempts on the existing role row.
            $role = Role::where('name', 'superadmin')->lockForUpdate()->firstOrFail();
            if (User::where('role_id', $role->id)->exists()) {
                throw new \RuntimeException('A superadmin already exists.');
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
        });
        $this->info('Platform account created. Sign in through /login.');

        return self::SUCCESS;
    }
}
