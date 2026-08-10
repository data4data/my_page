<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the "admin" role and a single admin user from env vars, if one doesn't exist yet.
     *
     * Credentials come from ADMIN_EMAIL / ADMIN_PASSWORD so nothing sensitive is hardcoded here.
     * Set them in .env before seeding in any shared/production environment.
     */
    public function run(): void
    {
        Role::findOrCreate('admin', 'web');

        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'Admin';
        $user->password = Hash::make($password);
        $user->save();

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn(
                'ADMIN_PASSWORD is not set — seeded the admin user with the insecure default password. '.
                'Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding a shared or production environment.'
            );
        }
    }
}
