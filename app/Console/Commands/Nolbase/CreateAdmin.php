<?php

namespace App\Console\Commands\Nolbase;

use App\Models\NolbaseAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'nolbase:admin:create
        {--name= : Full name}
        {--email= : Login email (must be unique)}
        {--role=support : One of support|staff|superadmin}
        {--password= : Plain-text password (will be hashed)}';

    protected $description = 'Create a Nolbase super-admin user (separate from tenant users).';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email', required: true, validate: ['email' => 'required|email|unique:nolbase_admins,email']);
        $role = $this->option('role') ?: select('Role', [
            NolbaseAdmin::ROLE_SUPPORT => 'support (read + impersonate)',
            NolbaseAdmin::ROLE_STAFF => 'staff (suspend + refund)',
            NolbaseAdmin::ROLE_SUPERADMIN => 'superadmin (manage other admins)',
        ], default: NolbaseAdmin::ROLE_SUPPORT);

        if (! in_array($role, [NolbaseAdmin::ROLE_SUPPORT, NolbaseAdmin::ROLE_STAFF, NolbaseAdmin::ROLE_SUPERADMIN], true)) {
            $this->error("Unknown role: {$role}");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: password('Password', required: true);

        if (NolbaseAdmin::where('email', $email)->exists()) {
            $this->error("A Nolbase admin already exists with email {$email}.");

            return self::FAILURE;
        }

        $validator = validator(['password' => $password], [
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = NolbaseAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        $this->info("Created Nolbase admin #{$admin->id}: {$admin->email} ({$admin->role}).");

        return self::SUCCESS;
    }
}
