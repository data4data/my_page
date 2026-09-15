<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the "admin" role and a single admin user from env vars.
     *
     * Credentials come from ADMIN_EMAIL / ADMIN_PASSWORD so nothing sensitive
     * is hardcoded here. Set them in .env before seeding anywhere shared.
     */
    // Outside local development the seeder refuses rather than creating a
    // weak account.
    private const MINIMUM_PASSWORD_LENGTH = 12;

    // What ships in .env.example, plus the usual suspects.
    private const REFUSED_PASSWORDS = [
        'password',
        'secret',
        'admin',
        'changeme',
        'letmein',
        '12345678',
    ];

    public function run(): void
    {
        Role::findOrCreate('admin', 'web');

        // config, not env(): once config:cache has run, env() outside a
        // config file returns null and this would seed the placeholder
        // credentials from .env.example without saying so.
        $email = (string) config('admin.seed.email');
        $password = (string) config('admin.seed.password');

        $this->guardPassword($password);

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'Admin';
        $user->password = Hash::make($password);
        $user->save();

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }

    /**
     * Local stays convenient: forcing a passphrase before the app runs once is
     * how people end up disabling the check. Anywhere else it is a hard stop,
     * not a warning nobody reads.
     */
    private function guardPassword(string $password): void
    {
        $weak = mb_strlen($password) < self::MINIMUM_PASSWORD_LENGTH
            || in_array(mb_strtolower($password), self::REFUSED_PASSWORDS, true);

        if (! $weak) {
            return;
        }

        if (app()->environment('local', 'testing')) {
            $this->command?->warn(
                'ADMIN_PASSWORD is weak. That is allowed here because APP_ENV is '.app()->environment().
                ', but seeding will refuse it anywhere else.'
            );

            return;
        }

        throw new RuntimeException(
            'Refusing to seed the admin account: ADMIN_PASSWORD must be at least '.
            self::MINIMUM_PASSWORD_LENGTH.' characters and must not be one of the well-known defaults. '.
            'Set a real one in .env and run the seeder again.'
        );
    }
}
