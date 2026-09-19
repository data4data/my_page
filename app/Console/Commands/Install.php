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
 * Creates this install's identity: the one admin account, and the profile the
 * public page is drawn from.
 *
 * Deliberately a command rather than a seeder. Seeders are for *samples* —
 * placeholder page content and the demo week — and they run unattended, which
 * is why AdminUserSeeder had to read credentials from .env and then spend
 * sixty lines refusing the weak ones it might be handed. A command can simply
 * ask, and a password typed at a prompt never lands in a file.
 *
 * Safe to run twice: it updates rather than duplicating, and says which.
 */
class Install extends Command
{
    protected $signature = 'app:install
                            {--email= : Skip the prompt and use this address}
                            {--initials= : Skip the prompt and use these initials}';

    protected $description = 'Create the admin account and the profile the public page is drawn from';

    public function handle(PortfolioSeeder $seeder): int
    {
        // Prompts throws a raw NonInteractiveValidationException when there
        // is no terminal to answer with, which is a stack trace where an
        // instruction belongs. There is deliberately no --password: the whole
        // point of a command over a seeder is that the password is typed, not
        // stored in a file or a shell history.
        if (! $this->hasATerminal()) {
            $this->components->error('app:install asks for a password, so it needs a terminal. Run it without piping input.');

            return self::FAILURE;
        }

        // A name the role middleware refers to, not anybody's data — so it is
        // created here rather than asked for, and findOrCreate makes rerunning
        // harmless.
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
     * Symfony's isInteractive() only reports the --no-interaction flag, so it
     * is still true when input is piped or redirected. stream_isatty is what
     * actually answers "is there someone to type".
     *
     * Skipped under the test runner, where PHPUnit answers the prompts.
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
        // Never defaulted and never read from a file: the whole reason this
        // is a command is that a password can be asked for.
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
