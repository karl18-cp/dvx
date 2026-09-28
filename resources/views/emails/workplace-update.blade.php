<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#faf4f5;font-family:Arial,sans-serif;color:#182032">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:white;border:1px solid #eadde0;border-radius:18px;overflow:hidden">
<tr><td style="padding:24px 28px;background:#711c25;color:white;font-weight:bold;font-size:22px">DIVERTEX</td></tr>
<tr><td style="padding:28px"><p>Hello {{ $recipientName }},</p><h1 style="font-size:22px;line-height:1.4">{{ $heading }}</h1>
<div style="white-space:pre-line;line-height:1.7;overflow-wrap:anywhere">{{ $body }}</div>
<p style="margin-top:28px"><a href="{{ rtrim(config('app.url'), '/') }}/login" style="display:inline-block;padding:13px 20px;background:#b51f2b;color:#fff;text-decoration:none;border-radius:9px">Open your Divertex portal</a></p>
<p style="font-size:12px;color:#697386;line-height:1.6">This update relates to your account or work you are authorized to review. Sign in to view the latest details.</p>
</td></tr></table></td></tr></table></body></html>
