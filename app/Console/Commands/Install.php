<?php

namespace App\Console\Commands;

use App\Models\PortfolioProfile;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\PortfolioSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * This install's identity: the one admin account and the profile the public
 * page is drawn from. A command rather than a seeder so the password is typed
 * rather than read from a file. Safe to run twice — it updates, not duplicates.
 */
class Install extends Command
{
    protected $signature = 'app:install
                            {--email= : Skip the prompt and use this address}
                            {--initials= : Skip the prompt and use these initials}';

    protected $description = 'Create the admin account and the profile the public page is drawn from';

    public function handle(PortfolioSeeder $seeder): int
    {
        // Prompts throws a raw exception with no terminal to answer with.
        // There is deliberately no --password option.
        if (! $this->hasATerminal()) {
            $this->components->error('app:install asks for a password, so it needs a terminal. Run it without piping input.');

            return self::FAILURE;
        }

        // A name the middleware refers to, not anybody's data: never asked for.
        Role::findOrCreate('admin', 'web');

        $email = $this->resolveEmail();

        if ($email === null) {
            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing && ! confirm("An account already exists for {$email}. Set a new password for it?", default: false)) {
            $this->components->warn('Left the existing account alone.');
        } else {
            $this->createOrUpdateAdmin($email, $existing);
        }

        $this->createProfile($seeder);

        $this->newLine();
        $this->components->info('Ready. The workspace is at /'.config('admin.path').' — set ADMIN_PATH in .env to something random before going live.');

        return self::SUCCESS;
    }

    /**
     * Not isInteractive(), which only reports --no-interaction and stays true
     * for piped input. Skipped under the test runner, which answers the prompts.
     */
    private function hasATerminal(): bool
    {
        return app()->runningUnitTests()
            || (defined('STDIN') && stream_isatty(STDIN));
    }

    private function resolveEmail(): ?string
    {
        $email = (string) ($this->option('email') ?: text(
            label: 'Which email address signs in to the workspace?',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'That is not an email address.',
        ));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('That is not an email address.');

            return null;
        }

        return $email;
    }

    private function createOrUpdateAdmin(string $email, ?User $existing): void
    {
        $secret = password(
            label: 'Password for that account',
            required: true,
            validate: fn (string $value) => (new StrongPassword)->complaint($value),
        );

        $user = $existing ?? new User(['name' => 'Admin']);
        $user->email = $email;
        $user->name = $user->name ?: 'Admin';
        $user->password = Hash::make($secret);
        $user->save();

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $this->components->info($existing ? "Updated the password for {$email}." : "Created {$email}.");
    }

    private function createProfile(PortfolioSeeder $seeder): void
    {
        if (PortfolioProfile::query()->exists()) {
            $this->components->warn('A profile already exists — left its content alone.');

            return;
        }

        $profile = $seeder->seed();

        $initials = (string) ($this->option('initials') ?: text(
            label: 'Initials for the public page',
            default: (string) $profile->initials,
            hint: 'Two or three letters. Everything else is editable in the workspace.',
        ));

        if ($initials !== '') {
            $profile->update(['initials' => mb_substr($initials, 0, 12)]);
        }

        $this->components->info('Created the profile with placeholder content.');
    }
}
