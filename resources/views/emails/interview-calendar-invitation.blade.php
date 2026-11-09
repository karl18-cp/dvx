<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">
<h1 style="color:#b72229">{{ $method === 'CANCEL' ? 'Interview cancelled' : 'Your Divertex interview' }}</h1>
<p>Hello {{ $recipientName }},</p>
@if($method === 'CANCEL')
<p>The interview below has been cancelled. The attached calendar update will remove it from supported calendar applications.</p>
@else
<p>An interview has been scheduled for <strong>{{ $appointment->application->first_name }} {{ $appointment->application->last_name }}</strong>.</p>
@endif
<table style="border-collapse:collapse;width:100%;max-width:640px">
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#667085">Interview</td><td style="padding:8px;border-bottom:1px solid #eee"><strong>{{ str($appointment->interview_type)->replace('_', ' ')->title() }}</strong></td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#667085">Date and time</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $appointment->starts_at->timezone($appointment->timezone)->format('F j, Y, g:i A') }}–{{ $appointment->ends_at->timezone($appointment->timezone)->format('g:i A') }} ({{ $appointment->timezone }})</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#667085">Interviewer</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $appointment->interviewer->name }}</td></tr>
@if($appointment->location)<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#667085">Location</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $appointment->location }}</td></tr>@endif
@if($appointment->meeting_url)<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#667085">Meeting link</td><td style="padding:8px;border-bottom:1px solid #eee"><a href="{{ $appointment->meeting_url }}">Join interview</a></td></tr>@endif
</table>
@if($method !== 'CANCEL')<p>The attached calendar invitation can be opened in Google Calendar, Outlook, Apple Calendar, and other compatible applications.</p>@endif
<p>Divertex Recruitment Team</p>
</body></html>
