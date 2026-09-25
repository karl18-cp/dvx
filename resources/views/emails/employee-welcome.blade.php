<!doctype html>
<html lang="en"><body style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">
<h1 style="color:#b72229">Welcome to Divertex</h1>
<p>Hello {{ $employeeName }},</p>
<p>Your Divertex account has been created. Use these credentials to sign in:</p>
<p><strong>Employee ID:</strong> {{ $employeeId }}<br>
<strong>Temporary password:</strong> <code>{{ $temporaryPassword }}</code></p>
<p><a href="{{ $loginUrl }}" style="color:#b72229;font-weight:bold">Sign in to Divertex</a></p>
<p>After signing in, open Settings → Password &amp; security and change your temporary password. Keep your credentials private.</p>
<p>If you forget your password later, use “Forgot your password?” on the login screen with the email address associated with your account.</p>
<p>If the login link is unavailable from your device, contact your administrator for access.</p>
<p>Divertex</p>
</body></html>
