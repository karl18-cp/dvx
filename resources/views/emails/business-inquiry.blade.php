<h2>New business inquiry #{{ $inquiry->id }}</h2>
<p><strong>Contact:</strong> {{ $inquiry->name }} — {{ $inquiry->company }}</p>
<p><strong>Email:</strong> {{ $inquiry->email }}</p>
<p><strong>Phone:</strong> {{ $inquiry->phone ?: 'Not provided' }}</p>
<p><strong>Service:</strong> {{ $inquiry->service }}</p>
<p><strong>Requested meeting:</strong> {{ $inquiry->meeting_at->setTimezone($inquiry->timezone)->format('F j, Y g:i A') }} ({{ $inquiry->timezone }})</p>
<p><strong>Philippine time:</strong> {{ $inquiry->meeting_at->setTimezone('Asia/Manila')->format('F j, Y g:i A') }}</p>
<p>This is a meeting request, not a confirmed booking. Reply to the client to arrange the meeting.</p>
<p style="white-space: pre-wrap">{{ $inquiry->message }}</p>
<p><a href="{{ url('/business-inquiries') }}">Review in Divertex</a></p>
@foreach ($inquiry->custom_answers ?? [] as $answer)
<p><strong>{{ $answer['label'] }}:</strong> {{ is_bool($answer['value']) ? ($answer['value'] ? 'Yes' : 'No') : ($answer['value'] ?? 'Not provided') }}</p>
@endforeach
