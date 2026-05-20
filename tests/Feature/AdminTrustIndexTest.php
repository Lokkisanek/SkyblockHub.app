<?php

namespace Tests\Feature;

use App\Models\TrustIndexSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTrustIndexTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'discord_username' => 'lokkisan',
            'minecraft_username' => 'Lokkisanecek',
        ]);
    }

    public function test_non_admin_cannot_open_trust_index_queue(): void
    {
        $user = User::factory()->create([
            'discord_username' => 'user',
            'minecraft_username' => 'SomePlayer',
        ]);

        $this->actingAs($user)
            ->get(route('admin.trust-index.submissions'))
            ->assertForbidden();
    }

    public function test_admin_can_list_and_resolve_submission(): void
    {
        $admin = $this->adminUser();

        $submission = TrustIndexSubmission::query()->create([
            'type' => TrustIndexSubmission::TYPE_REPORT,
            'status' => TrustIndexSubmission::STATUS_PENDING,
            'minecraft_username' => 'BadActor',
            'category' => 'TRADE_SCAM',
            'description' => str_repeat('A detailed scam report for testing purposes. ', 2),
            'evidence' => 'https://example.com',
            'submitter_name' => 'Reporter',
            'submitter_contact' => null,
            'user_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.trust-index.submissions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TrustIndex/Submissions')
                ->has('submissions', 1));

        $this->actingAs($admin)
            ->get(route('admin.trust-index.submissions.show', $submission))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/TrustIndex/Review')
                ->where('submission.id', $submission->id));

        $this->actingAs($admin)
            ->post(route('admin.trust-index.submissions.approve', $submission), [
                'admin_notes' => 'Looks credible; follow up.',
            ])
            ->assertRedirect(route('admin.trust-index.submissions'))
            ->assertSessionHas('status');

        $submission->refresh();
        $this->assertSame(TrustIndexSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame('Looks credible; follow up.', $submission->admin_notes);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertSame($admin->id, $submission->reviewed_by);

        $this->actingAs($admin)
            ->post(route('admin.trust-index.submissions.reject', $submission), [
                'admin_notes' => 'n/a',
            ])
            ->assertRedirect(route('admin.trust-index.submissions.show', $submission))
            ->assertSessionHas('status');

        $submission->refresh();
        $this->assertSame(TrustIndexSubmission::STATUS_APPROVED, $submission->status);
    }
}
