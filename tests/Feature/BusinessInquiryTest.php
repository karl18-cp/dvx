<?php

namespace Tests\Feature;

use App\Mail\BusinessInquiryReceived;
use App\Models\BusinessInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessInquiryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['submission_id' => (string) Str::uuid(), 'name' => 'Client Tester', 'company' => 'Test Company', 'email' => 'client@example.test', 'phone' => '+1 555 1234', 'service' => 'Customer Support', 'message' => 'We need a support team.', 'meeting_time' => now('America/New_York')->addDays(3)->format('Y-m-d\TH:i'), 'timezone' => 'America/New_York'];
    }
    private function configureMail(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'example.test', 'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'test', 'recruitment.inbox' => 'divertexcorp@gmail.com']);
    }
    public function test_public_submission_is_saved_emailed_and_not_duplicated(): void
    {
        Mail::fake(); $this->configureMail();
        $data = $this->payload();
        $this->from('/careers')->post('/business-inquiries', [...$data, 'status' => 'closed', 'admin_notes' => 'injected'])->assertRedirect('/careers')->assertSessionHasNoErrors();
        $record = BusinessInquiry::firstOrFail();
        $this->assertSame('new', $record->status);
        $this->assertNull($record->admin_notes);
        $this->assertSame($data['meeting_time'], $record->meeting_at->setTimezone($data['timezone'])->format('Y-m-d\TH:i'));
        Mail::assertSent(BusinessInquiryReceived::class, fn ($mail) => $mail->hasTo('divertexcorp@gmail.com'));
        $this->post('/business-inquiries', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_inquiries', 1);
        Mail::assertSentCount(1);
    }
    public function test_invalid_meeting_or_timezone_is_rejected(): void
    {
        Mail::fake();
        $this->post('/business-inquiries', [...$this->payload(), 'meeting_time' => '2020-01-01T09:00'])->assertSessionHasErrors('meeting_time');
        $this->post('/business-inquiries', [...$this->payload(), 'timezone' => 'invalid'])->assertSessionHasErrors('timezone');
        $this->assertDatabaseCount('business_inquiries', 0);
        Mail::assertNothingSent();
    }
    public function test_failed_email_keeps_inquiry_and_admin_can_retry(): void
    {
        $this->configureMail();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        Mail::shouldReceive('getDefaultDriver')->andReturn('smtp');
        $this->post('/business-inquiries', $this->payload())->assertSessionHasNoErrors();
        $record = BusinessInquiry::firstOrFail();
        $this->assertSame('failed', $record->email_status);
        Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))->post('/business-inquiries/'.$record->id.'/email')->assertRedirect();
        $this->assertSame('sent', $record->fresh()->email_status);
        Mail::assertSentCount(1);
    }
    public function test_only_admin_can_view_and_update_inquiries(): void
    {
        Mail::fake();
        $this->post('/business-inquiries', $this->payload());
        $record = BusinessInquiry::firstOrFail();
        $this->get('/business-inquiries')->assertRedirect('/login');
        foreach (['manager', 'qa_admin', 'team_leader', 'agent', 'trainee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            $this->get('/business-inquiries')->assertForbidden();
            $this->patch('/business-inquiries/'.$record->id, ['status' => 'closed'])->assertForbidden();
            $this->post('/business-inquiries/'.$record->id.'/email')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->get('/business-inquiries')->assertOk();
        $this->patch('/business-inquiries/'.$record->id, ['status' => 'contacted', 'admin_notes' => 'Called client'])->assertSessionHasNoErrors();
        $this->assertSame('contacted', $record->fresh()->status);
    }
}
