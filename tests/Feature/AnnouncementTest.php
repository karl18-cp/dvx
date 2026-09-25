<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_edit_and_delete_announcements(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/announcements', ['title' => 'Team meeting', 'body' => 'Meet tomorrow.', 'author_name' => 'Forged'])->assertSessionHasNoErrors()->assertRedirect('/announcements');
        $item = Announcement::sole();
        $this->assertSame($admin->name, $item->author_name);
        $coadmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($coadmin)->put("/announcements/{$item->id}", ['title' => 'Updated meeting', 'body' => 'Meet on Friday.'])->assertSessionHasNoErrors();
        $this->assertSame('Updated meeting', $item->fresh()->title);
        $this->delete("/announcements/{$item->id}")->assertRedirect('/announcements');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_employees_can_read_and_search_but_cannot_manage_announcements(): void
    {
        $item = Announcement::create(['title' => 'Team meeting', 'body' => 'Friday update', 'author_name' => 'Admin']);
        foreach (['agent', 'team_leader', 'manager', 'it_admin'] as $role) {
            $person = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($person)->get('/announcements?search=Friday')->assertOk()->assertInertia(fn (Assert $page) => $page->component('announcements')->where('canManage', false)->where('announcements.total', 1));
            $this->post('/announcements', ['title' => 'Unauthorized', 'body' => 'Test'])->assertForbidden();
            $this->put("/announcements/{$item->id}", ['title' => 'Changed', 'body' => 'Test'])->assertForbidden();
            $this->delete("/announcements/{$item->id}")->assertForbidden();
        }
        $this->get('/announcements?search=unmatched')->assertInertia(fn (Assert $page) => $page->where('announcements.total', 0));
        $this->assertSame('Team meeting', $item->fresh()->title);
    }

    public function test_inactive_accounts_and_guests_cannot_access_announcements(): void
    {
        $this->get('/announcements')->assertRedirect('/login');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'inactive']);
        $this->actingAs($admin)->get('/announcements')->assertForbidden();
        $this->post('/announcements', ['title' => 'Test', 'body' => 'Test'])->assertForbidden();
    }

    public function test_content_is_required_and_length_limited(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/announcements', ['title' => ' ', 'body' => ' '])->assertSessionHasErrors(['title', 'body']);
        $this->post('/announcements', ['title' => str_repeat('x', 181), 'body' => str_repeat('x', 10001)])->assertSessionHasErrors(['title', 'body']);
        $this->assertDatabaseCount('announcements', 0);
    }
}
