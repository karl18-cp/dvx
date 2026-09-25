<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#172033">
<h1 style="color:#b72229">New Divertex application</h1>
<p><strong>Applicant:</strong> {{ $application->first_name }} {{ $application->last_name }}</p>
<p><strong>Email:</strong> {{ $application->email }}</p>
<p><strong>Phone:</strong> {{ $application->phone }}</p>
<p><strong>Position:</strong> {{ $application->position }}</p>
<p><strong>Location:</strong> {{ $application->location ?: 'Not provided' }}</p>
<p><strong>Relevant experience:</strong> {{ $application->years_experience }} year(s)</p>
<p><strong>Introduction:</strong></p>
<div style="white-space:pre-wrap">{{ $application->message ?: 'Not provided' }}</div>
<p>{{ $application->resume_path ? 'The applicant’s résumé is attached.' : 'The applicant did not attach a résumé.' }}</p>
<p>Application #{{ $application->id }} is saved in Divertex. Sign in and open Management → Applicants to review it and change its status.</p>
<p>Reply to this email to contact the applicant.</p>
@foreach ($application->custom_answers ?? [] as $answer)
<p><strong>{{ $answer['label'] }}:</strong> {{ is_bool($answer['value']) ? ($answer['value'] ? 'Yes' : 'No') : ($answer['value'] ?? 'Not provided') }}</p>
@endforeach
</body></html>
