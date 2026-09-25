<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\OpaquePageUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpaquePageUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['navigation.opaque_urls' => true]);
    }

    public function test_legacy_page_redirects_and_opaque_page_refreshes_with_navigation_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $redirect = $this->actingAs($admin)->get('/dashboard')->assertRedirect();
        $url = $redirect->headers->get('Location');
        $this->assertStringContainsString('/p/', $url);
        $this->assertStringNotContainsString('dashboard', $url);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component('dashboard')->where('navigation.currentPath', '/dashboard')->where('navigation.links./dashboard', fn ($value) => str_starts_with($value, '/p/')));
        $this->get($url)->assertOk();
    }

    public function test_inertia_visits_return_opaque_page_url_and_preserve_filters(): void
    {
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $response = $this->actingAs($leader)->get('/leave-requests?scope=team&status=needs_review', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())])->assertOk();
        $url = $response->json('url');
        $this->assertStringStartsWith('/p/', $url);
        $this->assertStringContainsString('scope=team', $url);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->component('leave-requests')->where('scope', 'team')->where('filterStatus', 'needs_review'));
    }

    public function test_tokens_do_not_bypass_login_role_or_employee_access_and_invalid_tokens_fail(): void
    {
        $urls = app(OpaquePageUrls::class);
        $dashboard = $urls->encode('/dashboard');
        $employees = $urls->encode('/employees');
        $this->get($dashboard)->assertRedirect('/login');
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $this->actingAs($leader)->get($employees)->assertForbidden();
        $other = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $report = \App\Models\EodReport::create(['request_id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $other->id, 'author_name' => $other->name, 'author_role' => 'team_leader', 'report_date' => '2026-09-25', 'summary' => 'Private report', 'task_snapshots' => [], 'task_count' => 0]);
        $this->get($urls->encode('/eod-reports/'.$report->id))->assertForbidden();
        $this->get('/p/not-a-valid-token')->assertNotFound();
        $token = rtrim(strtr(Crypt::encryptString('https://example.com/dashboard'), '+/', '-_'), '=');
        $this->get('/p/'.$token)->assertNotFound();
        $token = rtrim(strtr(Crypt::encryptString('/logout'), '+/', '-_'), '=');
        $this->get('/p/'.$token)->assertNotFound();
        $this->post($dashboard)->assertStatus(405);
    }

    public function test_post_redirects_are_opaque_but_json_endpoints_still_work(): void
    {
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $response = $this->actingAs($leader)->post('/leave-requests', ['request_id' => (string) Str::uuid(), 'start_date' => '2026-09-28', 'end_date' => '2026-09-28', 'leave_type' => 'Vacation', 'reason' => 'Test'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertStringContainsString('/p/', $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $p) => $p->has('requests.data', 1)->where('statusMessage', 'Leave submitted for admin approval.'));
        $this->getJson('/divertext/unread')->assertOk()->assertJsonPath('count', 0);
        $this->assertSame('https://example.com/dashboard', app(OpaquePageUrls::class)->encode('https://example.com/dashboard'));
    }
}
