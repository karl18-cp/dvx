<?php

namespace Tests\Feature;

use App\Models\PublicUpdate;
use App\Models\PublicUpdateComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_landing_page_only_exposes_published_updates_and_approved_comments(): void
    {
        $live = PublicUpdate::create([
            'title' => 'Hiring update',
            'body' => 'Applications are open.',
            'author_name' => 'Admin',
            'is_published' => true,
            'published_at' => now(),
        ]);
        PublicUpdate::create([
            'title' => 'Internal draft',
            'body' => 'Do not publish.',
            'author_name' => 'Admin',
            'is_published' => false,
        ]);
        PublicUpdateComment::create([
            'request_key' => (string) Str::uuid(),
            'public_update_id' => $live->id,
            'name' => 'Visible Visitor',
            'body' => 'Thank you for the update.',
            'status' => 'approved',
        ]);
        PublicUpdateComment::create([
            'request_key' => (string) Str::uuid(),
            'public_update_id' => $live->id,
            'name' => 'Pending Visitor',
            'body' => 'Awaiting review.',
            'status' => 'pending',
        ]);

        $this->get('/careers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('publicUpdates', 1)
            ->where('publicUpdates.0.title', 'Hiring update')
            ->where('publicUpdates.0.comments_count', 1)
            ->has('publicUpdates.0.comments', 1)
            ->where('publicUpdates.0.comments.0.name', 'Visible Visitor'));
    }

    public function test_guest_comment_is_pending_and_cannot_inject_moderation_state(): void
    {
        $update = PublicUpdate::create([
            'title' => 'Community day',
            'body' => 'Join us.',
            'author_name' => 'Admin',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->from('/careers')->post('/public-updates/'.$update->id.'/comments', [
            'request_key' => (string) Str::uuid(),
            'name' => 'Guest',
            'email' => 'guest@example.test',
            'body' => 'Looking forward to it!',
            'status' => 'approved',
            'website' => '',
        ])->assertRedirect('/careers')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('public_update_comments', [
            'public_update_id' => $update->id,
            'name' => 'Guest',
            'status' => 'pending',
        ]);
        $this->post('/public-updates/'.$update->id.'/comments', [
            'request_key' => (string) Str::uuid(),
            'name' => 'Bot',
            'body' => 'Spam',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors('website');
    }

    public function test_admin_can_publish_updates_and_moderate_comments(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/public-updates/manage', [
            'title' => 'New office update',
            'body' => 'We have expanded our workspace.',
            'is_published' => true,
        ])->assertSessionHasNoErrors();
        $update = PublicUpdate::sole();
        $this->assertNotNull($update->published_at);

        $comment = PublicUpdateComment::create([
            'request_key' => (string) Str::uuid(),
            'public_update_id' => $update->id,
            'name' => 'Visitor',
            'body' => 'Congratulations!',
            'status' => 'pending',
        ]);
        $this->get('/public-updates/manage')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public-updates')
            ->has('updates.data', 1)
            ->has('pendingComments.data', 1));
        $this->patch('/public-updates/comments/'.$comment->id, ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $comment->fresh()->status);
        $this->assertSame($admin->id, $comment->fresh()->moderated_by);
    }

    public function test_non_admin_cannot_manage_public_updates(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->actingAs($agent)->get('/public-updates/manage')->assertForbidden();
        $this->post('/public-updates/manage', [
            'title' => 'Unauthorized',
            'body' => 'No access.',
            'is_published' => true,
        ])->assertForbidden();
    }
}
