<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #171717; font-family: DejaVu Sans, sans-serif; font-size: 10px; background: #fff; }
    .page { min-height: 1122px; padding: 38px 48px 34px; position: relative; }
    .top-accent { position: absolute; top: 0; right: 0; width: 275px; height: 55px; background: #a9131d; }
    .top-accent:before { content: ''; position: absolute; left: -56px; border-top: 55px solid #a9131d; border-left: 56px solid transparent; }
    .logo { width: 154px; height: auto; }
    .company-row { margin-top: 22px; padding-bottom: 10px; border-bottom: 4px solid #a9131d; }
    .company { font-size: 16px; font-weight: 700; }
    .website { float: right; color: #606060; font-size: 10px; padding-top: 4px; }
    h1 { font-size: 31px; margin: 38px 0 28px; }
    .employee-card { width: 100%; background: #bd2029; color: #fff; padding: 17px 20px; margin-bottom: 20px; }
    .employee-card table { width: 100%; border-collapse: collapse; }
    .employee-card td { padding: 3px 0; }
    .employee-card .label { width: 112px; color: #ffe8ea; }
    .employee-card .value { font-weight: 700; }
    .employee-card .pay-label { width: 62px; color: #ffe8ea; }
    .section-title { background: #171717; color: white; font-size: 18px; font-weight: 700; padding: 9px 12px; letter-spacing: .7px; }
    table.breakdown { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .breakdown th, .breakdown td { border: 1px solid #4b4b4b; padding: 8px 9px; }
    .breakdown th { text-align: center; background: #fafafa; }
    .breakdown td:first-child { font-weight: 700; }
    .number { text-align: right; }
    .center { text-align: center; }
    .total-row { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .total-row td { background: #c90009; color: white; padding: 10px 12px; font-size: 12px; font-weight: 700; }
    .total-row td:last-child { text-align: right; }
    .summary { margin-top: 24px; width: 100%; border-collapse: collapse; }
    .summary td { padding: 5px 0; }
    .summary .label { color: #666; width: 140px; }
    .summary .net { color: #a9131d; font-size: 18px; font-weight: 700; }
    .footer { position: absolute; bottom: 28px; left: 48px; right: 48px; border-top: 3px solid #a9131d; padding-top: 11px; color: #5f5f5f; }
    .footer .right { float: right; }
    .authorized { margin-top: 34px; text-align: right; }
    .authorized span { display: inline-block; min-width: 235px; border-top: 1px solid #555; padding-top: 6px; text-align: center; font-weight: 700; }
</style>
</head>
<body>
<div class="page">
    <div class="top-accent"></div>
    @if($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="Divertex">@endif
    <div class="company-row"><span class="company">{{ $companyName }}</span><span class="website">www.divertexcorp.com</span></div>
    <h1>Employee Payslip</h1>
    <div class="employee-card">
        <table>
            <tr><td class="label">Employee Name</td><td class="value">{{ $employeeName }}</td><td class="pay-label">Pay Date</td><td class="value">{{ $payDate }}</td></tr>
            <tr><td class="label">Employee ID</td><td class="value">{{ $employeeId }}</td><td class="pay-label">Position</td><td class="value">{{ $position }}</td></tr>
        </table>
    </div>
    <div class="section-title">EARNINGS</div>
    <table class="breakdown">
        <thead><tr><th style="width:40%">Description</th><th style="width:20%">Hourly Rate</th><th style="width:18%">Hours</th><th style="width:22%">Amount</th></tr></thead>
        <tbody>
        @foreach($earnings as $line)
            <tr><td>{{ $line['label'] }}</td><td class="number">{{ $line['rate'] === null ? '—' : 'PHP '.number_format((float) $line['rate'], 2) }}</td><td class="center">{{ $line['hours'] === null ? '—' : $line['hours'] }}</td><td class="number">PHP {{ number_format($line['amount_cents'] / 100, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="total-row"><tr><td>Total earnings</td><td>PHP {{ number_format($grossCents / 100, 2) }}</td></tr></table>
    <div class="section-title">DEDUCTIONS</div>
    <table class="breakdown">
        <thead><tr><th>Description</th><th style="width:30%">Amount</th></tr></thead>
        <tbody>
        @foreach($deductions as $line)
            <tr><td>{{ $line['label'] }}</td><td class="number">PHP {{ number_format($line['amount_cents'] / 100, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="total-row"><tr><td>Total deductions</td><td>PHP {{ number_format($deductionCents / 100, 2) }}</td></tr></table>
    <table class="summary">
        <tr><td class="label">Payroll period</td><td>{{ $periodStart }} – {{ $periodEnd }}</td><td class="label">Net pay</td><td class="net">PHP {{ number_format($netCents / 100, 2) }}</td></tr>
        <tr><td class="label">Reference number</td><td>{{ $referenceNumber }}</td><td></td><td></td></tr>
    </table>
    <div class="authorized"><span>Authorized Payroll<br><small>{{ $companyName }}</small></span></div>
    <div class="footer"><strong>{{ $companyName }}</strong><span class="right">divertexcorp@gmail.com &nbsp; | &nbsp; www.divertexcorp.com</span></div>
</div>
</body>
</html>
