<?php

namespace App\Http\Controllers;

use App\Models\InterviewAppointment;
use App\Models\InterviewAppointmentEvent;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\AuditLogService;
use App\Services\InterviewInvitationService;
use App\Services\RecruitmentEmailService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InterviewScheduleController extends Controller
{
    private const ACTIVE_STATUSES = ['scheduled', 'rescheduled'];

    public function index(Request $request)
    {
        $this->manager($request);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'status' => ['nullable', Rule::in([...self::ACTIVE_STATUSES, 'completed', 'no_show', 'cancelled'])]]);
        $month = isset($data['month']) ? CarbonImmutable::createFromFormat('Y-m', $data['month'], 'Asia/Manila') : CarbonImmutable::now('Asia/Manila');
        $from = $month->startOfMonth()->startOfDay()->utc();
        $to = $month->endOfMonth()->endOfDay()->utc();
        $appointments = InterviewAppointment::query()->with(['application:id,first_name,last_name,email,phone,position,applicant_stage', 'interviewer:id,name,email,role', 'events.actor:id,name'])
            ->whereBetween('starts_at', [$from, $to])->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('starts_at')->get()->map(fn (InterviewAppointment $appointment) => $this->row($appointment));

        return Inertia::render('interview-calendar', [
            'appointments' => $appointments,
            'applicants' => JobApplication::query()->whereIn('applicant_stage', ['for_screening', 'for_final_interview'])->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'position', 'applicant_stage']),
            'interviewers' => User::query()->where('status', 'active')->whereIn('role', ['admin', 'manager', 'qa_admin', 'team_leader'])->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'month' => $month->format('Y-m'), 'status' => $data['status'] ?? '',
            'summary' => [
                'scheduled' => $appointments->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'no_show' => $appointments->where('status', 'no_show')->count(),
                'needs_invite' => $appointments->filter(fn ($item) => $item['applicant_invite_status'] !== 'sent' || $item['interviewer_invite_status'] !== 'sent')->count(),
            ],
            'emailConfigured' => app(RecruitmentEmailService::class)->configured(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $this->manager($request);
        $data = $request->validate([
            'interviewer_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')->whereIn('role', ['admin', 'manager', 'qa_admin', 'team_leader']))], 'date' => ['required', 'date_format:Y-m-d'],
            'duration' => ['required', Rule::in([30, 45, 60, 90, 120])], 'exclude' => ['nullable', 'integer', 'exists:interview_appointments,id'],
        ]);
        $day = CarbonImmutable::createFromFormat('Y-m-d', $data['date'], 'Asia/Manila');
        if ($day->endOfDay()->isPast()) {
            throw ValidationException::withMessages(['date' => 'Choose today or a future date.']);
        }
        $duration = (int) $data['duration'];
        $appointments = InterviewAppointment::query()->where('interviewer_id', $data['interviewer_id'])->whereIn('status', self::ACTIVE_STATUSES)
            ->when($data['exclude'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->where('starts_at', '<', $day->endOfDay()->utc())->where('ends_at', '>', $day->startOfDay()->utc())->get();
        $slots = [];
        for ($slot = $day->setTime(9, 0); $slot->addMinutes($duration)->lte($day->setTime(18, 0)); $slot = $slot->addMinutes(30)) {
            $end = $slot->addMinutes($duration);
            $available = $appointments->doesntContain(fn (InterviewAppointment $appointment) => $appointment->starts_at->lt($end->utc()) && $appointment->ends_at->gt($slot->utc()));
            if ($available && $slot->isFuture()) {
                $slots[] = ['value' => $slot->format('Y-m-d\TH:i'), 'label' => $slot->format('g:i A').' – '.$end->format('g:i A')];
            }
        }

        return response()->json(['slots' => $slots, 'timezone' => 'Asia/Manila']);
    }

    public function store(Request $request, InterviewInvitationService $invitations, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $this->manager($request);
        $data = $this->validated($request);
        [$startsAt, $endsAt] = $this->times($data);
        $appointment = DB::transaction(function () use ($actor, $data, $startsAt, $endsAt, $audit) {
            $this->ensureAvailable((int) $data['interviewer_id'], $startsAt, $endsAt);
            $appointment = InterviewAppointment::create([
                ...collect($data)->except(['starts_at', 'duration'])->all(), 'calendar_uid' => (string) Str::uuid(),
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            InterviewAppointmentEvent::create(['interview_appointment_id' => $appointment->id, 'event_type' => 'scheduled', 'new_starts_at' => $startsAt, 'new_ends_at' => $endsAt, 'actor_id' => $actor->id]);
            $this->syncApplication($appointment, $actor, $startsAt);
            $audit->record($actor, 'recruitment', 'Interview scheduled', $appointment, metadata: ['application_id' => $appointment->job_application_id, 'interviewer_id' => $appointment->interviewer_id, 'starts_at' => $startsAt->toIso8601String()]);

            return $appointment;
        });
        $result = $invitations->send($appointment);
        $appointment->load('interviewer');
        $notifications->createForUser($appointment->interviewer, "interview:{$appointment->id}:scheduled:{$appointment->calendar_sequence}", 'interview', 'Interview assigned to you', 'A recruitment interview has been scheduled for '.$startsAt->timezone('Asia/Manila')->format('M d, Y g:i A').'.', '/management/interview-calendar');

        return back()->with('success', $this->emailMessage('Interview scheduled.', $result));
    }

    public function reschedule(Request $request, InterviewAppointment $appointment, InterviewInvitationService $invitations, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $this->manager($request);
        abort_unless(in_array($appointment->status, self::ACTIVE_STATUSES, true), 422);
        $data = $this->validated($request);
        [$startsAt, $endsAt] = $this->times($data);
        $appointment = DB::transaction(function () use ($actor, $appointment, $data, $startsAt, $endsAt, $audit) {
            $locked = InterviewAppointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $this->ensureAvailable((int) $data['interviewer_id'], $startsAt, $endsAt, $locked->id);
            $previousStart = $locked->starts_at;
            $previousEnd = $locked->ends_at;
            $locked->update([
                ...collect($data)->except(['starts_at', 'duration'])->all(), 'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'status' => 'rescheduled', 'calendar_sequence' => $locked->calendar_sequence + 1,
                'reschedule_count' => $locked->reschedule_count + 1, 'updated_by' => $actor->id,
                'applicant_invite_status' => 'pending', 'interviewer_invite_status' => 'pending',
            ]);
            InterviewAppointmentEvent::create(['interview_appointment_id' => $locked->id, 'event_type' => 'rescheduled', 'previous_starts_at' => $previousStart, 'previous_ends_at' => $previousEnd, 'new_starts_at' => $startsAt, 'new_ends_at' => $endsAt, 'notes' => $data['notes'] ?? null, 'actor_id' => $actor->id]);
            $this->syncApplication($locked, $actor, $startsAt);
            $audit->record($actor, 'recruitment', 'Interview rescheduled', $locked, metadata: ['previous_starts_at' => $previousStart?->toIso8601String(), 'new_starts_at' => $startsAt->toIso8601String(), 'reschedule_count' => $locked->reschedule_count]);

            return $locked;
        });
        $result = $invitations->send($appointment);
        $appointment->load('interviewer');
        $notifications->createForUser($appointment->interviewer, "interview:{$appointment->id}:rescheduled:{$appointment->calendar_sequence}", 'interview', 'Interview rescheduled', 'The interview is now scheduled for '.$startsAt->timezone('Asia/Manila')->format('M d, Y g:i A').'.', '/management/interview-calendar');

        return back()->with('success', $this->emailMessage('Interview rescheduled.', $result));
    }

    public function status(Request $request, InterviewAppointment $appointment, InterviewInvitationService $invitations, AuditLogService $audit)
    {
        $actor = $this->manager($request);
        $data = $request->validate(['status' => ['required', Rule::in(['completed', 'no_show', 'cancelled'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($appointment, $actor, $data, $audit) {
            $locked = InterviewAppointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $locked->update(['status' => $data['status'], 'notes' => $data['notes'] ?? $locked->notes, 'calendar_sequence' => $locked->calendar_sequence + ($data['status'] === 'cancelled' ? 1 : 0), 'updated_by' => $actor->id]);
            InterviewAppointmentEvent::create(['interview_appointment_id' => $locked->id, 'event_type' => $data['status'], 'previous_starts_at' => $locked->starts_at, 'previous_ends_at' => $locked->ends_at, 'notes' => $data['notes'] ?? null, 'actor_id' => $actor->id]);
            if (in_array($data['status'], ['no_show', 'cancelled'], true)) {
                $application = JobApplication::query()->lockForUpdate()->find($locked->job_application_id);
                if ($application?->scheduled_start_at?->getTimestamp() === $locked->starts_at->getTimestamp()) {
                    $application->forceFill(['scheduled_start_at' => null, 'applicant_update' => $data['status'] === 'no_show' ? 'Interview marked as no-show. Please contact recruitment for next steps.' : 'The scheduled interview was cancelled. Recruitment will contact you regarding next steps.', 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
                }
            }
            $audit->record($actor, 'recruitment', 'Interview status changed', $locked, metadata: ['status' => $data['status'], 'notes' => $data['notes'] ?? null]);
        });
        if ($data['status'] === 'cancelled') {
            $invitations->send($appointment->fresh(), 'CANCEL');
        }

        return back()->with('success', 'Interview marked as '.str($data['status'])->replace('_', ' ').'.');
    }

    public function retry(Request $request, InterviewAppointment $appointment, InterviewInvitationService $invitations)
    {
        $this->manager($request);
        $result = $invitations->send($appointment, $appointment->status === 'cancelled' ? 'CANCEL' : 'REQUEST');

        return back()->with('success', $this->emailMessage('Calendar invitation retried.', $result));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'job_application_id' => ['required', 'exists:job_applications,id'], 'interviewer_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')->whereIn('role', ['admin', 'manager', 'qa_admin', 'team_leader']))],
            'interview_type' => ['required', Rule::in(['screening', 'final_interview'])], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration' => ['required', Rule::in([30, 45, 60, 90, 120])], 'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url:http,https', 'max:500'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function times(array $data): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], 'Asia/Manila')->startOfMinute();
        if ($start->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future interview time.']);
        }

        return [$start->utc(), $start->addMinutes((int) $data['duration'])->utc()];
    }

    private function ensureAvailable(int $interviewerId, CarbonImmutable $start, CarbonImmutable $end, ?int $exclude = null): void
    {
        $conflict = InterviewAppointment::query()->where('interviewer_id', $interviewerId)->whereIn('status', self::ACTIVE_STATUSES)
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['starts_at' => 'That interviewer already has an interview during this time. Choose another available slot.']);
        }
    }

    private function syncApplication(InterviewAppointment $appointment, User $actor, CarbonImmutable $startsAt): void
    {
        $application = JobApplication::query()->lockForUpdate()->findOrFail($appointment->job_application_id);
        $stage = $appointment->interview_type === 'screening' ? 'for_screening' : 'for_final_interview';
        $application->forceFill([
            'applicant_stage' => $stage, 'scheduled_start_at' => $startsAt, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
            'screening_status' => $appointment->interview_type === 'screening' ? 'pending' : 'passed',
            'interview_status' => 'scheduled', 'application_status' => $appointment->interview_type === 'screening' ? 'in_review' : 'for_interview',
            'applicant_update' => str($appointment->interview_type)->replace('_', ' ')->title().' interview scheduled. Please check your email for the calendar invitation.',
        ])->save();
    }

    private function row(InterviewAppointment $appointment): array
    {
        return [
            ...$appointment->only(['id', 'job_application_id', 'interviewer_id', 'interview_type', 'timezone', 'location', 'meeting_url', 'status', 'calendar_sequence', 'reschedule_count', 'notes', 'applicant_invite_status', 'interviewer_invite_status', 'invited_at', 'last_invite_attempt_at']),
            'starts_at' => $appointment->starts_at->toIso8601String(), 'ends_at' => $appointment->ends_at->toIso8601String(),
            'application' => $appointment->application, 'interviewer' => $appointment->interviewer,
            'events' => $appointment->events->map(fn ($event) => [...$event->only(['id', 'event_type', 'previous_starts_at', 'previous_ends_at', 'new_starts_at', 'new_ends_at', 'notes', 'created_at']), 'actor_name' => $event->actor?->name]),
        ];
    }

    private function emailMessage(string $prefix, array $result): string
    {
        return $prefix.' '.($result['applicant'] && $result['interviewer'] ? 'Calendar invitations were sent to the applicant and interviewer.' : 'The schedule was saved, but one or more calendar emails need to be retried.');
    }

    private function manager(Request $request): User
    {
        $actor = $request->user()->fresh();
        abort_unless($actor?->hasPermission('recruitment.manage'), 403);

        return $actor;
    }
}
