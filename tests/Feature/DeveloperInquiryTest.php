<?php

namespace Tests\Feature;

use App\Http\Controllers\DeveloperInquiryController;
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
        $this->get($this->adminUrl('/inquiries'))->assertRedirect($this->adminUrl('/login'));
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

    // The public form is throttled per minute, not in total, so the list is paged.
    public function test_the_inquiry_list_is_paginated(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $perPage = DeveloperInquiryController::PER_PAGE;

        foreach (range(1, $perPage + 3) as $index) {
            DeveloperInquiry::create([
                'name' => "Sender {$index}",
                'email' => "sender{$index}@example.com",
                'message' => 'Hello',
            ]);
        }

        $first = $this->actingAs($admin)->getJson($this->adminUrl('/inquiries'))->assertOk();
        $first->assertJsonCount($perPage, 'inquiries');
        $this->assertTrue($first->json('has_more'));

        $second = $this->actingAs($admin)->getJson($this->adminUrl('/inquiries?page=2'))->assertOk();
        $second->assertJsonCount(3, 'inquiries');
        $this->assertFalse($second->json('has_more'));

        // No row appears on both pages.
        $ids = array_merge(
            array_column($first->json('inquiries'), 'id'),
            array_column($second->json('inquiries'), 'id'),
        );
        $this->assertSame($ids, array_unique($ids));
    }

    // A filled honeypot gets the same response a real submission does.
    public function test_a_filled_honeypot_is_accepted_and_discarded(): void
    {
        $this->postJson('/hi-developer', [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'message' => 'Buy things',
            'website' => 'http://spam.example',
        ])->assertOk();

        $this->assertDatabaseCount('developer_inquiries', 0);
    }

    public function test_an_empty_honeypot_goes_through(): void
    {
        $this->postJson('/hi-developer', [
            'name' => 'Jamie Dev',
            'email' => 'jamie@example.com',
            'message' => 'Would love to connect.',
            'website' => '',
        ])->assertOk();

        $this->assertDatabaseCount('developer_inquiries', 1);
    }

    // An older cached page sends no honeypot key at all, and must still work.
    public function test_a_submission_without_the_field_still_works(): void
    {
        $this->postJson('/hi-developer', [
            'name' => 'Jamie Dev',
            'email' => 'jamie@example.com',
            'message' => 'Would love to connect.',
        ])->assertOk();

        $this->assertDatabaseCount('developer_inquiries', 1);
    }

    // Caught before validation, so probing with an invalid payload tells nothing.
    public function test_the_honeypot_wins_over_validation_errors(): void
    {
        $this->postJson('/hi-developer', ['website' => 'http://spam.example'])
            ->assertOk();

        $this->assertDatabaseCount('developer_inquiries', 0);
    }
}
