<?php

namespace App\Services;

use App\Mail\InterviewCalendarInvitation;
use App\Models\InterviewAppointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InterviewInvitationService
{
    public function send(InterviewAppointment $appointment, string $method = 'REQUEST'): array
    {
        $appointment->loadMissing(['application', 'interviewer']);
        if (! app(RecruitmentEmailService::class)->configured()) {
            $appointment->update([
                'applicant_invite_status' => 'not_configured', 'interviewer_invite_status' => 'not_configured',
                'last_invite_attempt_at' => now(), 'invite_failure_reason' => 'mail_not_configured',
            ]);

            return ['applicant' => false, 'interviewer' => false];
        }

        $calendar = $this->calendar($appointment, $method);
        $applicantSent = $this->deliver(
            $appointment->application->email,
            trim($appointment->application->first_name.' '.$appointment->application->last_name),
            $appointment,
            $calendar,
            $method,
            'applicant',
        );
        $interviewerSent = $this->deliver(
            $appointment->interviewer->email,
            $appointment->interviewer->name,
            $appointment,
            $calendar,
            $method,
            'interviewer',
        );
        $appointment->update([
            'applicant_invite_status' => $applicantSent ? 'sent' : 'failed',
            'interviewer_invite_status' => $interviewerSent ? 'sent' : 'failed',
            'invited_at' => $applicantSent || $interviewerSent ? now() : $appointment->invited_at,
            'last_invite_attempt_at' => now(),
            'invite_failure_reason' => $applicantSent && $interviewerSent ? null : 'one_or_more_deliveries_failed',
        ]);

        return ['applicant' => $applicantSent, 'interviewer' => $interviewerSent];
    }

    public function calendar(InterviewAppointment $appointment, string $method = 'REQUEST'): string
    {
        $appointment->loadMissing(['application', 'interviewer']);
        $summary = 'Divertex '.str($appointment->interview_type)->replace('_', ' ')->title().' Interview';
        $description = 'Interview for '.$appointment->application->first_name.' '.$appointment->application->last_name
            .' ('.$appointment->application->position.') with '.$appointment->interviewer->name;
        if ($appointment->meeting_url) {
            $description .= "\nMeeting link: ".$appointment->meeting_url;
        }
        $organizer = config('mail.from.address') ?: config('recruitment.inbox');

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'PRODID:-//Divertex Global Strategies Corporation//Interview Calendar//EN',
            'VERSION:2.0', 'CALSCALE:GREGORIAN', 'METHOD:'.$method, 'BEGIN:VEVENT',
            'UID:'.$appointment->calendar_uid.'@divertexcorp.com',
            'SEQUENCE:'.$appointment->calendar_sequence,
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$appointment->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$appointment->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($summary), 'DESCRIPTION:'.$this->escape($description),
            'LOCATION:'.$this->escape($appointment->location ?: ($appointment->meeting_url ?: 'Divertex interview')),
            'ORGANIZER;CN=Divertex Recruitment:MAILTO:'.$organizer,
            'ATTENDEE;CN='.$this->escape(trim($appointment->application->first_name.' '.$appointment->application->last_name)).';RSVP=TRUE:MAILTO:'.$appointment->application->email,
            'ATTENDEE;CN='.$this->escape($appointment->interviewer->name).';RSVP=TRUE:MAILTO:'.$appointment->interviewer->email,
            'STATUS:'.($method === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED'),
            'BEGIN:VALARM', 'TRIGGER:-PT30M', 'ACTION:DISPLAY', 'DESCRIPTION:Divertex interview begins in 30 minutes', 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);
    }

    private function deliver(string $email, string $name, InterviewAppointment $appointment, string $calendar, string $method, string $audience): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            Mail::to($email)->send(new InterviewCalendarInvitation($appointment, $name, $calendar, $method));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Interview calendar invitation failed', ['appointment_id' => $appointment->id, 'audience' => $audience, 'exception_type' => $exception::class]);

            return false;
        }
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);
    }
}
