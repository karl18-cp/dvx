<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f8f3f4;font-family:Arial,sans-serif;color:#172033">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#fff;border:1px solid #eadde0;border-radius:18px;overflow:hidden">
<tr><td style="padding:22px 28px;background:#7d1720;color:#fff;font-size:21px;font-weight:700">DIVERTEX</td></tr>
<tr><td style="padding:30px">
<p style="margin-top:0">Hello {{ $employeeName }},</p>
<h1 style="font-size:23px;line-height:1.35;margin:18px 0 10px">Your payslip is attached</h1>
<p style="line-height:1.7;color:#566176">{{ $companyName }} has sent your payroll payslip as a PDF attachment. You can also review the same paid record in My Payslips after signing in.</p>
<p style="margin-top:26px"><a href="{{ rtrim(config('app.url'), '/') }}/my-payslips?entry={{ $entry->id }}" style="display:inline-block;padding:13px 20px;background:#bd2029;color:#fff;text-decoration:none;border-radius:10px;font-weight:700">View My Payslips</a></p>
<p style="font-size:12px;color:#758094;line-height:1.6;margin-bottom:0">This document contains private payroll information. Please keep it secure.</p>
</td></tr></table>
</td></tr></table>
</body>
</html>
