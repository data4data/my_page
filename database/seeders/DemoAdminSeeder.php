<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * A way back into the workspace after `migrate:fresh --seed`, which drops the
 * users table along with everything else.
 *
 * `php artisan app:install` is still how a real install gets its account — the
 * password is typed rather than written down. This is the development
 * equivalent: a throwaway login you can set in .env (DEMO_ADMIN_EMAIL /
 * DEMO_ADMIN_PASSWORD, see config/admin.php), which is exactly why it refuses
 * to run anywhere but `local` and refuses to touch an account that exists.
 */
class DemoAdminSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'demo@my-page.test';

    /** In the repository on purpose. Never reuse it for anything real. */
    public const DEFAULT_PASSWORD = 'demo-workspace';

    /**
     * trim() then fall back: a key present in .env but left blank is a likelier
     * mistake than an address or a password made of spaces, and either would
     * make an account nobody can sign in to.
     */
    public static function email(): string
    {
        return trim((string) config('admin.demo.email')) ?: self::DEFAULT_EMAIL;
    }

    public static function password(): string
    {
        return trim((string) config('admin.demo.password')) ?: self::DEFAULT_PASSWORD;
    }

    public function run(): void
    {
        // Guarded here as well as in DatabaseSeeder: `db:seed --class=` names
        // this directly and would otherwise skip the caller's check.
        if (! app()->environment('local')) {
            $this->command?->warn('DemoAdminSeeder is local-only. Use `php artisan app:install`. Skipping.');

            return;
        }

        $role = Role::findOrCreate('admin', 'web');

        // Never a second admin, and never a new password on an existing one:
        // a real account must not be reachable with a password from a
        // repository just because someone re-ran the seeders.
        $existing = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->first();

        if ($existing) {
            $this->command?->info("DemoAdminSeeder: {$existing->email} is already the admin. Leaving it alone.");

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => self::email()],
            [
                'name' => 'Demo admin',
                'password' => Hash::make(self::password()),
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole($role);

        $this->command?->info('DemoAdminSeeder: sign in with '.self::email().' / '.self::password());
    }
}
