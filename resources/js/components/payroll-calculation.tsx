import { Alert, Button, Chip, MenuItem, TextField } from '@mui/material';
import { useEffect, useState } from 'react';
import { money } from '@/components/training-allowance-details';

export const payrollDefaults = {
    calculation: 'workbook',
    pay_month: '',
    payout: 'month_end',
    regular_rate: '',
    basic_rate: '',
    basic_hours: '0',
    allowance_hours: '0',
    night_hours: '0',
    overtime_hours: '0',
    rest_hours: '0',
    holiday_hours: '0',
    regular_holidays: '',
    special_holidays: '',
    fixed_allowances: '0',
    sss: '0',
    philhealth: '0',
    pagibig: '0',
    other_deductions: '0',
};
type Fields = typeof payrollDefaults;
export type PayrollSnapshot = {
    attendance?: AttendancePreview;
    pay_date: string;
    period_start: string;
    period_end: string;
    earnings: {
        label: string;
        rate: string | null;
        hours: string | null;
        amount_cents: number;
    }[];
    deductions: { label: string; amount_cents: number }[];
};
const hoursFields = [
    ['basic_hours', 'Basic pay hours'],
    ['allowance_hours', 'Variable allowance hours'],
    ['night_hours', 'Night differential hours'],
    ['overtime_hours', 'Overtime hours'],
    ['rest_hours', 'RD / SNWH hours'],
    ['holiday_hours', 'Regular holiday hours'],
] as const;
const deductions = [
    ['sss', 'SSS'],
    ['philhealth', 'PhilHealth'],
    ['pagibig', 'Pag-IBIG'],
    ['other_deductions', 'Other deductions'],
] as const;

export default function PayrollCalculation({
    data,
    onChange,
    onReady,
}: {
    data: Fields & { user_id: string };
    onReady: (ready: boolean) => void;
    onChange: (next: Partial<Fields>) => void;
}) {
    const [attendance, setAttendance] = useState<AttendancePreview | null>(
        null,
    );
    const [error, setError] = useState('');
    const { user_id, pay_month, payout, regular_holidays, special_holidays } =
        data;
    useEffect(() => {
        const controller = new AbortController();
        onReady(false);

        if (!user_id || !pay_month) {
            return () => controller.abort();
        }

        const timer = setTimeout(async () => {
            try {
                const query = new URLSearchParams({
                    user_id,
                    pay_month,
                    payout,
                    regular_holidays,
                    special_holidays,
                });
                const response = await fetch(
                    '/accounting/payroll-attendance?' + query,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(
                        result.message || 'Unable to load attendance.',
                    );
                }

                if (!controller.signal.aborted) {
                    setAttendance(result);
                    onReady(true);
                }
            } catch (error) {
                if (!controller.signal.aborted) {
                    setError(
                        error instanceof Error
                            ? error.message
                            : 'Unable to load attendance.',
                    );
                }
            }
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        user_id,
        pay_month,
        payout,
        regular_holidays,
        special_holidays,
        onReady,
    ]);
    const monthEnd = data.payout === 'month_end';
    const cents = (value: string) => Math.round(Number(value || 0) * 100);
    const basic = cents(data.basic_rate);
    const variable = cents(data.regular_rate) * 100 - basic * 110;
    const rates = [
        basic * 100,
        variable,
        basic * 10,
        basic * 125,
        basic * 30,
        basic * 100,
    ];
    const gross = hoursFields.reduce(
        (total, [key], index) =>
            total +
            Math.round((rates[index] * (attendance?.minutes[key] ?? 0)) / 6000),
        cents(data.fixed_allowances),
    );
    const deduction = monthEnd
        ? deductions.reduce((total, [key]) => total + cents(data[key]), 0)
        : 0;
    let period = '';

    if (/^\d{4}-\d{2}$/.test(data.pay_month)) {
        const [year, month] = data.pay_month.split('-').map(Number);
        const previous = new Date(Date.UTC(year, month - 2, 26))
            .toISOString()
            .slice(0, 10);
        const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
        period = monthEnd
            ? `${data.pay_month}-11 to ${data.pay_month}-25 · Payday: ${data.pay_month}-${lastDay}`
            : `${previous} to ${data.pay_month}-10 · Payday: ${data.pay_month}-15`;
    }

    const field = (key: keyof Fields, label: string, disabled = false) => (
        <TextField
            key={key}
            fullWidth
            size="small"
            label={label}
            value={data[key]}
            disabled={disabled}
            onChange={(e) => onChange({ [key]: e.target.value })}
            slotProps={{ htmlInput: { inputMode: 'decimal' } }}
        />
    );

    return (
        <>
            <div className="grid gap-5 sm:grid-cols-2">
                <TextField
                    size="small"
                    type="month"
                    label="Payout month"
                    value={data.pay_month}
                    onChange={(e) => onChange({ pay_month: e.target.value })}
                    slotProps={{ inputLabel: { shrink: true } }}
                />
                <TextField
                    size="small"
                    select
                    label="Payout"
                    value={data.payout}
                    onChange={(e) =>
                        onChange({
                            payout: e.target.value,
                            ...(e.target.value === 'mid_month'
                                ? {
                                      sss: '0',
                                      philhealth: '0',
                                      pagibig: '0',
                                      other_deductions: '0',
                                  }
                                : {}),
                        })
                    }
                >
                    <MenuItem value="mid_month">
                        15th payout · 26th–10th
                    </MenuItem>
                    <MenuItem value="month_end">
                        Month-end payout · 11th–25th
                    </MenuItem>
                </TextField>
            </div>
            {period && <Alert severity="info">{period}</Alert>}
            <div className="rounded-2xl border p-5">
                <h3 className="mb-4 font-semibold">Hourly rates (PHP)</h3>
                <div className="grid gap-5 sm:grid-cols-2">
                    {field('regular_rate', 'Regular rate')}
                    {field('basic_rate', 'Basic pay rate')}
                </div>
                <p className="mt-3 text-sm text-slate-500">
                    Variable allowance = regular rate − basic rate − 10% of
                    basic rate. ND: 10% · OT: 125% · RD/SNWH: 30% · Regular
                    holiday: 100% of basic rate.
                </p>
                {variable < 0 && (
                    <Alert severity="error" sx={{ mt: 2 }}>
                        Regular rate must cover basic pay plus its 10% night
                        differential.
                    </Alert>
                )}
            </div>
            <div className="rounded-2xl border p-5">
                <h3 className="mb-3 font-semibold">Holiday dates</h3>
                <p className="mb-4 text-sm text-slate-500">
                    Select dates in this cutoff. Premium hours come from worked
                    attendance on those shift dates.
                </p>
                <HolidayDates
                    label="Regular holidays"
                    value={data.regular_holidays}
                    onChange={(value) => onChange({ regular_holidays: value })}
                />
                <HolidayDates
                    label="Special non-working holidays"
                    value={data.special_holidays}
                    onChange={(value) => onChange({ special_holidays: value })}
                />
            </div>
            {error && <Alert severity="error">{error}</Alert>}
            {!attendance && !error && (
                <Alert severity="info">
                    {user_id && pay_month
                        ? 'Loading attendance...'
                        : 'Select an employee and payout month to load attendance.'}
                </Alert>
            )}
            {attendance?.warnings.map((warning) => (
                <Alert severity="warning" key={warning}>
                    {warning}
                </Alert>
            ))}
            <div className="rounded-2xl border p-5">
                <h3 className="mb-4 font-semibold">
                    Attendance hours and allowances
                </h3>
                <p className="mb-5 text-sm text-slate-500">
                    Hours come from credited attendance and approved paid leave.
                    Unpaid breaks and undertime reduce credit; approved overtime
                    is separate from basic hours. Night differential uses worked
                    time from 10 PM to 6 AM.
                </p>
                <div className="grid gap-5 sm:grid-cols-2">
                    {hoursFields.map(([key, label]) => (
                        <TextField
                            key={key}
                            size="small"
                            label={label}
                            value={attendance?.hours[key] ?? '...'}
                            slotProps={{ input: { readOnly: true } }}
                        />
                    ))}
                    {field('fixed_allowances', 'Fixed allowances (PHP)')}
                </div>
            </div>
            <div className="rounded-2xl border p-5">
                <h3 className="mb-2 font-semibold">
                    Month-end deductions (PHP)
                </h3>
                <p className="mb-5 text-sm text-slate-500">
                    {monthEnd
                        ? 'Enter the employee’s contribution amounts for this month.'
                        : 'No deductions apply to the 15th payout.'}
                </p>
                <div className="grid gap-5 sm:grid-cols-2">
                    {deductions.map(([key, label]) =>
                        field(key, label, !monthEnd),
                    )}
                </div>
            </div>
            <div className="grid grid-cols-3 gap-3 rounded-2xl bg-red-50 p-4 text-sm">
                {[
                    ['Gross', gross],
                    ['Deductions', deduction],
                    ['Net pay', gross - deduction],
                ].map(([label, value]) => (
                    <div key={label}>
                        <p>{label}</p>
                        <strong>
                            {attendance ? money(Number(value)) : '...'}
                        </strong>
                    </div>
                ))}
            </div>
        </>
    );
}

type AttendancePreview = {
    hours: Record<string, string>;
    minutes: Record<string, number>;
    warnings: string[];
    rows: {
        date: string;
        status: string;
        worked_minutes: number;
        leave_minutes: number;
        total_minutes: number;
    }[];
};
function HolidayDates({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const [date, setDate] = useState('');
    const dates = value.split(',').filter(Boolean);

    return (
        <div className="mb-4 flex flex-wrap items-center gap-2">
            <TextField
                size="small"
                type="date"
                label={label}
                value={date}
                onChange={(e) => setDate(e.target.value)}
                slotProps={{ inputLabel: { shrink: true } }}
            />
            <Button
                disabled={!date}
                onClick={() => {
                    onChange([...new Set([...dates, date])].sort().join(','));
                    setDate('');
                }}
            >
                Add date
            </Button>
            {dates.map((item) => (
                <Chip
                    key={item}
                    label={item}
                    onDelete={() =>
                        onChange(dates.filter((d) => d !== item).join(','))
                    }
                />
            ))}
        </div>
    );
}
export function PayrollBreakdown({ data }: { data: PayrollSnapshot }) {
    return (
        <div className="space-y-3 rounded-2xl border p-4">
            <p className="font-semibold">
                Payroll calculation · Scheduled payday: {data.pay_date}
            </p>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr>
                            <th className="p-2 text-left">Earnings</th>
                            <th>Rate (PHP)</th>
                            <th>Hours</th>
                            <th className="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.earnings.map((line) => (
                            <tr key={line.label}>
                                <td className="p-2">{line.label}</td>
                                <td className="text-center">
                                    {line.rate ?? '—'}
                                </td>
                                <td className="text-center">
                                    {line.hours ?? '—'}
                                </td>
                                <td className="text-right">
                                    {money(line.amount_cents)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {data.attendance && (
                <div className="overflow-x-auto">
                    <h4 className="mb-2 font-semibold">Attendance source</h4>
                    <table className="w-full text-sm">
                        <thead>
                            <tr>
                                <th className="text-left">Shift date</th>
                                <th>Status</th>
                                <th>Worked hours</th>
                                <th>Paid leave hours</th>
                                <th>Credited hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.attendance.rows.map((row) => (
                                <tr key={row.date}>
                                    <td className="py-2">{row.date}</td>
                                    <td className="text-center">
                                        {row.status.replaceAll('_', ' ')}
                                    </td>
                                    <td className="text-center">
                                        {(row.worked_minutes / 60).toFixed(2)}
                                    </td>
                                    <td className="text-center">
                                        {(row.leave_minutes / 60).toFixed(2)}
                                    </td>
                                    <td className="text-center">
                                        {(row.total_minutes / 60).toFixed(2)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <p className="font-semibold">Deductions</p>
            {data.deductions.map((line) => (
                <div
                    key={line.label}
                    className="flex justify-between gap-3 text-sm"
                >
                    <span>{line.label}</span>
                    <span>{money(line.amount_cents)}</span>
                </div>
            ))}
        </div>
    );
}
