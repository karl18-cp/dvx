import { Alert, Chip } from '@mui/material';

export type Allowance = {
    trainee: {
        id: number;
        name: string;
        username: string;
        training_status: string;
    };
    rows: {
        day: number;
        date: string;
        phase: string;
        rate_cents: number;
        worked_minutes: number;
        eligible: boolean;
        amount_cents: number;
        first_allowance: boolean;
        attendance: string;
    }[];
    qualified_days: number;
    earned_cents: number;
    first_allowance_cents: number;
    first_allowance_ready: boolean;
    first_allowance_date: string | null;
    projected_first_cents: number;
    projected_first_date: string;
    projected_total_cents: number;
};
export const money = (cents: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cents / 100);

export default function TrainingAllowanceDetails({
    allowance,
    threshold,
}: {
    allowance: Allowance;
    threshold: number;
}) {
    return (
        <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-3">
                {[
                    [
                        'Attended training days',
                        String(allowance.qualified_days),
                    ],
                    [
                        'First allowance accrued',
                        money(allowance.first_allowance_cents),
                    ],
                    ['Total allowance accrued', money(allowance.earned_cents)],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-2xl border border-red-100 bg-red-50/50 p-5"
                    >
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className="mt-2 text-2xl font-bold text-red-900">
                            {value}
                        </p>
                    </div>
                ))}
            </div>
            <Alert
                severity={allowance.first_allowance_ready ? 'success' : 'info'}
            >
                {allowance.first_allowance_ready
                    ? `First allowance eligible for release: ${money(allowance.first_allowance_cents)}. Threshold reached on ${allowance.first_allowance_date}.`
                    : `${Math.min(allowance.qualified_days, threshold)} of ${threshold} attended days completed before the first allowance is eligible for release.`}{' '}
                Eligibility does not mean payment has been sent. Your
                administrator handles the actual release.
            </Alert>
            <p className="text-sm text-slate-500">
                Estimated first allowance with full attendance:{' '}
                {money(allowance.projected_first_cents)} on{' '}
                {allowance.projected_first_date}. Absences can delay this date
                and change the phase rates included. A completed time-in and
                time-out with positive worked hours counts as one attended day;
                the daily rate is not prorated.
            </p>
            <div className="max-h-[50vh] overflow-auto rounded-xl border border-slate-200">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="sticky top-0 bg-red-50 text-red-900">
                        <tr>
                            {[
                                'Day',
                                'Date',
                                'Phase',
                                'Attendance',
                                'Daily rate',
                                'Earned',
                                'First allowance',
                            ].map((h) => (
                                <th key={h} className="p-3">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {allowance.rows.map((row) => (
                            <tr
                                key={row.day}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">{row.day}</td>
                                <td className="p-3 whitespace-nowrap">
                                    {row.date}
                                </td>
                                <td className="p-3">{row.phase}</td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        color={
                                            row.eligible ? 'success' : 'default'
                                        }
                                        label={row.attendance}
                                    />
                                </td>
                                <td className="p-3">{money(row.rate_cents)}</td>
                                <td className="p-3 font-semibold">
                                    {money(row.amount_cents)}
                                </td>
                                <td className="p-3">
                                    {row.first_allowance ? 'Included' : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
