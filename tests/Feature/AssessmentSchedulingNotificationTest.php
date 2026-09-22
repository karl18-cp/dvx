<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentNotification;
use App\Models\User;
use App\Services\AssessmentScheduleService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssessmentSchedulingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_and_missed_scheduled_assessments_publish_idempotently(): void
    {
        Carbon::setTestNow('2026-08-18 00:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $future = $this->assessment($admin, 'Future', now()->addHour());
        $due = $this->assessment($admin, 'Due', now());
        $missed = $this->assessment($admin, 'Missed', now()->subHours(2));

        app(AssessmentScheduleService::class)->process();
        app(AssessmentScheduleService::class)->process();

        $this->assertSame('draft', $future->fresh()->status);
        $this->assertSame('published', $due->fresh()->status);
        $this->assertSame('published', $missed->fresh()->status);
        $this->assertDatabaseCount('assessment_activity_logs', 2);
        Carbon::setTestNow();
    }

    public function test_assignment_and_reminders_are_deduplicated_and_completed_assignments_are_skipped(): void
    {
        Carbon::setTestNow('2026-08-18 00:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $assessment = $this->assessment($admin, 'Grammar');
        $payload = ['assessment_id' => $assessment->id, 'employee_ids' => [$employee->id], 'team_ids' => [], 'all_employees' => false, 'available_from' => '2026-08-18 09:00', 'due_date' => '2026-08-19 07:00'];
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('assessments.assignments.store'), $payload)->assertRedirect();
        $assignment = AssessmentAssignment::query()->firstOrFail();
        $this->assertDatabaseCount('assessment_notifications', 1);

        Carbon::setTestNow('2026-08-18 01:30:00');
        app(AssessmentScheduleService::class)->process();
        app(AssessmentScheduleService::class)->process();
        $this->assertSame(1, AssessmentNotification::query()->where('assignment_id', $assignment->id)->where('type', 'available')->count());
        $this->assertSame(1, AssessmentNotification::query()->where('assignment_id', $assignment->id)->where('type', 'due_soon')->count());

        $assignment->update(['status' => 'passed']);
        Carbon::setTestNow('2026-08-18 23:30:00');
        app(AssessmentScheduleService::class)->process();
        $this->assertSame(0, AssessmentNotification::query()->where('assignment_id', $assignment->id)->whereIn('type', ['due_today', 'overdue'])->count());
        Carbon::setTestNow();
    }

    public function test_notification_ownership_read_and_deadline_extension_authorization(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $assessment = $this->assessment($admin, 'Calls');
        $assignment = AssessmentAssignment::query()->create(['assessment_id' => $assessment->id, 'employee_id' => $employee->id, 'assigned_by' => $admin->id, 'assigned_at' => now(), 'due_date' => now()->addDay(), 'status' => 'assigned']);
        $notification = AssessmentNotification::query()->create(['recipient_id' => $employee->id, 'assessment_id' => $assessment->id, 'assignment_id' => $assignment->id, 'type' => 'assigned', 'title' => 'Assigned', 'message' => 'Test', 'deduplication_key' => 'test']);

        $this->actingAs($other)->get(route('notifications.index'))->assertInertia(fn ($page) => $page->has('notifications.data', 0));
        $this->actingAs($other)->patch(route('notifications.read', $notification))->assertForbidden();
        $this->actingAs($employee)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);

        $payload = ['assignment_ids' => [$assignment->id], 'due_date' => '2026-08-22 17:00'];
        $this->actingAs($employee)->patch(route('assessments.assignments.deadlines.extend'), $payload)->assertForbidden();
        $this->actingAs($admin)->patch(route('assessments.assignments.deadlines.extend'), $payload)->assertRedirect();
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Bulk Deadline Extended', 'target_id' => $assignment->id]);
    }

    private function assessment(User $admin, string $title, ?CarbonInterface $publishAt = null): Assessment
    {
        return Assessment::query()->create(['title' => $title, 'passing_score' => 75, 'maximum_attempts' => 1, 'status' => $publishAt ? 'draft' : 'published', 'publish_at' => $publishAt, 'created_by' => $admin->id]);
    }
}
