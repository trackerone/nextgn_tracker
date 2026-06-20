<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AlphaFeedback;
use App\Models\User;
use Tests\TestCase;

class AlphaFeedbackIntakeTest extends TestCase
{
    public function test_guest_cannot_open_feedback_form(): void
    {
        $this->get(route('alpha.feedback.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_member_can_open_feedback_form(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('alpha.feedback.create'))
            ->assertOk()
            ->assertSee('Report an alpha issue')
            ->assertSee('Submit alpha feedback');
    }

    public function test_authenticated_member_can_submit_feedback(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('alpha.feedback.store'), $this->validFeedbackPayload())
            ->assertRedirect(route('alpha.feedback.create'));

        $this->assertDatabaseHas('alpha_feedback', [
            'user_id' => $member->id,
            'severity' => 'blocker',
            'area' => 'download_magnet',
            'title' => 'Download fails for eligible member',
            'blocks_alpha' => true,
            'status' => 'open',
        ]);
    }

    public function test_submitted_feedback_stores_reproducible_issue_fields(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->post(route('alpha.feedback.store'), $this->validFeedbackPayload([
            'steps_to_reproduce' => "Open torrent detail\nClick download",
            'expected_result' => 'Torrent file downloads.',
            'actual_result' => 'The page returns a 500 error.',
        ]));

        $this->assertDatabaseHas('alpha_feedback', [
            'severity' => 'blocker',
            'area' => 'download_magnet',
            'title' => 'Download fails for eligible member',
            'steps_to_reproduce' => "Open torrent detail\nClick download",
            'expected_result' => 'Torrent file downloads.',
            'actual_result' => 'The page returns a 500 error.',
        ]);
    }

    public function test_normal_member_cannot_access_staff_feedback_index(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('staff.alpha-feedback.index'))
            ->assertForbidden();
    }

    public function test_staff_can_access_staff_feedback_index(): void
    {
        $staff = User::factory()->staff()->create();
        $feedback = $this->createFeedback();

        $this->actingAs($staff)
            ->get(route('staff.alpha-feedback.index'))
            ->assertOk()
            ->assertSee('Alpha feedback intake')
            ->assertSee($feedback->title);
    }

    public function test_staff_can_view_feedback_detail(): void
    {
        $staff = User::factory()->staff()->create();
        $feedback = $this->createFeedback([
            'actual_result' => 'The upload form shows a generic failure.',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.alpha-feedback.show', $feedback))
            ->assertOk()
            ->assertSee('Alpha feedback detail')
            ->assertSee('The upload form shows a generic failure.');
    }

    public function test_staff_can_update_feedback_status(): void
    {
        $staff = User::factory()->staff()->create();
        $feedback = $this->createFeedback();

        $this->actingAs($staff)
            ->patch(route('staff.alpha-feedback.update', $feedback), ['status' => 'reviewing'])
            ->assertRedirect(route('staff.alpha-feedback.show', $feedback));

        $this->assertDatabaseHas('alpha_feedback', [
            'id' => $feedback->id,
            'status' => 'reviewing',
            'status_updated_by' => $staff->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFeedbackPayload(array $overrides = []): array
    {
        return array_merge([
            'severity' => 'blocker',
            'area' => 'download_magnet',
            'role' => 'Member',
            'environment' => 'Staging alpha / Firefox',
            'title' => 'Download fails for eligible member',
            'steps_to_reproduce' => 'Open torrent detail and click download.',
            'expected_result' => 'Torrent file downloads.',
            'actual_result' => 'The page returns a 500 error.',
            'url_or_context' => 'https://alpha.example.test/torrents/123',
            'blocks_alpha' => '1',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createFeedback(array $overrides = []): AlphaFeedback
    {
        return AlphaFeedback::query()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'severity' => 'must_fix',
            'area' => 'upload',
            'role' => 'Uploader',
            'environment' => 'Staging alpha / Chrome',
            'title' => 'Upload validation is unclear',
            'steps_to_reproduce' => 'Submit an invalid torrent upload.',
            'expected_result' => 'Validation explains the fix.',
            'actual_result' => 'Validation is too generic.',
            'url_or_context' => '/torrents/upload',
            'blocks_alpha' => false,
            'status' => 'open',
        ], $overrides));
    }
}
