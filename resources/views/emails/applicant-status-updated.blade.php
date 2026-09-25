<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">
<h1 style="color:#b72229">Your Divertex application update</h1>
<p>Hello {{ $applicantName }},</p>
<p>We have updated your application for <strong>{{ $position }}</strong>.</p>
<p><strong>Current status: {{ $statusLabel }}</strong></p>
@if ($scheduleLabel && $scheduledStart)
<p><strong>{{ $scheduleLabel }} starts:</strong> {{ $scheduledStart }}</p>
@endif
@if ($publicUpdate)
<h2 style="font-size:18px">Message from recruitment</h2>
<div style="white-space:pre-wrap">{{ $publicUpdate }}</div>
@endif
<p><a href="{{ $portalUrl }}" style="color:#b72229;font-weight:bold">View your application progress</a></p>
<p>Use the email address and mobile number from your application to check your progress.</p>
<p>Thank you for your interest in Divertex.</p>
<p>Divertex Recruitment Team</p>
</body></html>
