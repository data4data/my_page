<?php

namespace Tests\Feature;

use App\Models\DeveloperInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeveloperInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_submit_the_connect_form(): void
    {
        $response = $this->postJson('/hi-developer', [
            'name' => 'Jamie Dev',
            'email' => 'jamie@example.com',
            'message' => 'Would love to connect about your work.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('developer_inquiries', [
            'name' => 'Jamie Dev',
            'email' => 'jamie@example.com',
        ]);
    }

    public function test_submission_requires_name_email_and_message(): void
    {
        $response = $this->postJson('/hi-developer', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'message']);
    }

    public function test_guest_cannot_list_inquiries(): void
    {
        $this->get($this->adminUrl('/inquiries'))->assertRedirect('/login');
    }

    public function test_admin_can_list_submitted_inquiries_read_only(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        DeveloperInquiry::create([
            'name' => 'Jamie Dev',
            'email' => 'jamie@example.com',
            'message' => 'Hello!',
        ]);

        $response = $this->actingAs($user)->getJson($this->adminUrl('/inquiries'));

        $response->assertOk()->assertJsonCount(1, 'inquiries');
    }
}
