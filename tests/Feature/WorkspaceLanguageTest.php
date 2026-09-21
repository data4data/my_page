<?php

namespace Tests\Feature;

use App\Http\Middleware\SetWorkspaceLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The public page says which language it is in with its URL. The workspace has
 * no prefix — the EN/NL switch is a preference held in the browser — so it
 * sends a header, and Laravel's own messages follow it. Without this every
 * server-side message in a Dutch workspace came back in English.
 */
class WorkspaceLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /** A task with no title, which trips `required`. */
    private function invalidTask(): array
    {
        return ['start_datetime' => '2026-06-10 09:00:00'];
    }

    public function test_validation_answers_in_the_language_the_header_asks_for(): void
    {
        $message = $this->actingAs($this->admin())
            ->withHeader(SetWorkspaceLocale::HEADER, 'nl')
            ->postJson($this->adminUrl('/tasks'), $this->invalidTask())
            ->assertStatus(422)
            ->json('errors.title.0');

        $this->assertSame('Vul de titel in.', $message);
    }

    public function test_it_answers_in_english_without_the_header(): void
    {
        $message = $this->actingAs($this->admin())
            ->postJson($this->adminUrl('/tasks'), $this->invalidTask())
            ->assertStatus(422)
            ->json('errors.title.0');

        $this->assertStringContainsString('title field is required', $message);
    }

    // The header is client-supplied, so anything off the list is ignored.
    public function test_an_unknown_language_is_ignored_rather_than_applied(): void
    {
        $message = $this->actingAs($this->admin())
            ->withHeader(SetWorkspaceLocale::HEADER, '../../etc/passwd')
            ->postJson($this->adminUrl('/tasks'), $this->invalidTask())
            ->assertStatus(422)
            ->json('errors.title.0');

        $this->assertStringContainsString('title field is required', $message);
    }

    // This app's own rules, not the framework's, and they were English everywhere.
    public function test_our_own_rule_messages_are_translated_too(): void
    {
        $message = $this->actingAs($this->admin())
            ->withHeader(SetWorkspaceLocale::HEADER, 'nl')
            ->getJson($this->adminUrl('/tasks?start=2020-01-01&end=2026-12-31'))
            ->assertStatus(422)
            ->json('errors.end.0');

        $this->assertSame('De gevraagde periode is te lang.', $message);
    }
}
