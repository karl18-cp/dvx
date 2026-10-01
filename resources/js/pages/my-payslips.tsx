import { Head } from '@inertiajs/react';
import {
    Alert,
    Button,
    CircularProgress,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
} from '@mui/material';
import { ReceiptText, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Link from '@/components/page-link';
import { money } from '@/components/training-allowance-details';

type Slip = {
    id: number;
    description: string;
    period_start: string;
    period_end: string;
    paid_at: string;
    gross_cents: number;
    deduction_cents: number;
    net_cents: number;
};
type Detail = Slip & {
    employee_name: string;
    employee_id: string;
    scheduled_pay_date: string | null;
    payment_method: string;
    payment_reference: string;
    earnings:
        | {
              label: string;
              rate: string | null;
              hours: string | null;
              amount_cents: number;
          }[]
        | null;
    deductions: { label: string; amount_cents: number }[] | null;
    attendance: {
        date: string;
        status: string;
        worked_minutes: number;
        leave_minutes: number;
        total_minutes: number;
    }[];
};
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              timeZone: 'UTC',
          }).format(new Date(value.slice(0, 10) + 'T00:00:00Z'))
        : '—';
function Totals({ slip }: { slip: Slip }) {
    return (
        <div className="grid gap-3 sm:grid-cols-3">
            {[
                ['Gross earnings', slip.gross_cents],
                ['Deductions', slip.deduction_cents],
                ['Net pay', slip.net_cents],
            ].map(([label, value]) => (
                <div
                    key={label}
                    className="rounded-xl border border-red-100 bg-red-50/60 p-4"
                >
                    <p className="text-sm text-slate-600">{label}</p>
                    <p className="mt-1 text-xl font-bold text-slate-900">
                        {money(Number(value))}
                    </p>
                </div>
            ))}
        </div>
    );
}
function PayslipDialog({ id, onClose }: { id: number; onClose: () => void }) {
    const [detail, setDetail] = useState<Detail | null>(null);
    const [error, setError] = useState('');
    useEffect(() => {
        const controller = new AbortController();
        fetch(`/my-payslips/${id}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
throw new Error(
                        response.status === 404
                            ? 'This payslip is not available.'
                            : 'Unable to load your payslip. Please try again.',
                    );
}

                const data = await response.json();

                if (!controller.signal.aborted) {
setDetail(data);
}
            })
            .catch((error) => {
                if (!controller.signal.aborted) {
setError(error.message);
}
            });

        return () => controller.abort();
    }, [id]);

    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="md">
            <DialogTitle
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 2,
                }}
            >
                My payslip
                <IconButton aria-label="Close payslip" onClick={onClose}>
                    <X size={20} />
                </IconButton>
            </DialogTitle>
            <DialogContent dividers>
                {error ? (
                    <Alert severity="error">{error}</Alert>
                ) : !detail ? (
                    <div className="flex justify-center p-10">
                        <CircularProgress aria-label="Loading payslip" />
                    </div>
                ) : (
                    <div className="space-y-6 py-2">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-sm font-bold tracking-widest text-red-700">
                                    DIVERTEX
                                </p>
                                <h2 className="mt-2 text-xl font-semibold">
                                    {detail.employee_name}
                                </h2>
                                <p className="text-sm text-slate-500">
                                    {detail.employee_id} · Payslip #{detail.id}
                                </p>
                            </div>
                            <div className="text-sm text-slate-600">
                                <p>
                                    Period: {date(detail.period_start)} –{' '}
                                    {date(detail.period_end)}
                                </p>
                                <p>Paid: {date(detail.paid_at)}</p>
                                {detail.scheduled_pay_date && (
                                    <p>
                                        Scheduled payday:{' '}
                                        {date(detail.scheduled_pay_date)}
                                    </p>
                                )}
                            </div>
                        </div>
                        <Totals slip={detail} />
                        <section>
                            <h3 className="mb-3 font-semibold">
                                Earnings breakdown
                            </h3>
                            {detail.earnings ? (
                                <div className="overflow-x-auto rounded-xl border">
                                    <table className="w-full min-w-[440px] text-sm">
                                        <thead className="bg-slate-50 text-left">
                                            <tr>
                                                <th className="p-3">
                                                    Earnings
                                                </th>
                                                <th className="p-3">
                                                    Rate (PHP)
                                                </th>
                                                <th className="p-3">Hours</th>
                                                <th className="p-3 text-right">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detail.earnings.map((line) => (
                                                <tr
                                                    key={line.label}
                                                    className="border-t"
                                                >
                                                    <td className="p-3">
                                                        {line.label}
                                                    </td>
                                                    <td className="p-3">
                                                        {line.rate ?? '—'}
                                                    </td>
                                                    <td className="p-3">
                                                        {line.hours ?? '—'}
                                                    </td>
                                                    <td className="p-3 text-right whitespace-nowrap">
                                                        {money(
                                                            line.amount_cents,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <Alert severity="info">
                                    This older payroll record contains totals
                                    only; an itemized earnings breakdown was not
                                    saved.
                                </Alert>
                            )}
                        </section>
                        <section>
                            <h3 className="mb-3 font-semibold">Deductions</h3>
                            {detail.deductions ? (
                                <div className="divide-y rounded-xl border px-4">
                                    {detail.deductions.map((line) => (
                                        <div
                                            key={line.label}
                                            className="flex justify-between gap-3 py-3 text-sm"
                                        >
                                            <span>{line.label}</span>
                                            <span>
                                                {money(line.amount_cents)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-slate-600">
                                    Recorded deductions:{' '}
                                    {money(detail.deduction_cents)}
                                </p>
                            )}
                        </section>
                        {detail.attendance.length > 0 && (
                            <details className="rounded-xl border p-4">
                                <summary className="cursor-pointer font-semibold">
                                    Attendance used for this payslip
                                </summary>
                                <div className="mt-3 overflow-x-auto">
                                    <table className="w-full min-w-[460px] text-sm">
                                        <thead className="text-left">
                                            <tr>
                                                <th className="p-2">
                                                    Shift date
                                                </th>
                                                <th className="p-2">Status</th>
                                                <th className="p-2">
                                                    Worked hours
                                                </th>
                                                <th className="p-2">
                                                    Paid leave hours
                                                </th>
                                                <th className="p-2">
                                                    Total hours
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detail.attendance.map((row) => (
                                                <tr
                                                    key={row.date}
                                                    className="border-t"
                                                >
                                                    <td className="p-2">
                                                        {date(row.date)}
                                                    </td>
                                                    <td className="p-2">
                                                        {row.status.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </td>
                                                    <td className="p-2">
                                                        {(
                                                            row.worked_minutes /
                                                            60
                                                        ).toFixed(2)}
                                                    </td>
                                                    <td className="p-2">
                                                        {(
                                                            row.leave_minutes /
                                                            60
                                                        ).toFixed(2)}
                                                    </td>
                                                    <td className="p-2">
                                                        {(
                                                            row.total_minutes /
                                                            60
                                                        ).toFixed(2)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        )}
                        <div className="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                            <p>
                                Payment method:{' '}
                                {detail.payment_method?.replaceAll('_', ' ') ||
                                    '—'}
                            </p>
                            <p className="break-words">
                                Reference: {detail.payment_reference || '—'}
                            </p>
                            <p className="mt-2">
                                These are the amounts recorded when your payroll
                                was paid. Contact accounting if you have
                                questions.
                            </p>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function MyPayslips({
    payslips,
    selectedEntry,
}: {
    payslips: {
        data: Slip[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selectedEntry: number | null;
}) {
    const [selected, setSelected] = useState<number | null>(selectedEntry);

    return (
        <>
            <Head title="My Payslips" />
            <main className="space-y-6 p-4 sm:p-8">
                <header>
                    <p className="text-xs font-semibold tracking-[.2em] text-red-700">
                        YOUR PAYROLL
                    </p>
                    <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                        <ReceiptText className="text-red-700" />
                        My Payslips
                    </h1>
                    <p className="mt-2 text-slate-500">
                        View your paid salary records and their earnings,
                        deductions, and attendance breakdown.
                    </p>
                </header>
                {payslips.data.length ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {payslips.data.map((slip) => (
                            <section
                                key={slip.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-lg font-semibold">
                                            {slip.description}
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {date(slip.period_start)} –{' '}
                                            {date(slip.period_end)}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        Paid
                                    </span>
                                </div>
                                <div className="mt-5 flex flex-wrap items-end justify-between gap-4">
                                    <div>
                                        <p className="text-sm text-slate-500">
                                            Net pay
                                        </p>
                                        <p className="text-2xl font-bold">
                                            {money(slip.net_cents)}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            Paid {date(slip.paid_at)}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outlined"
                                        onClick={() => setSelected(slip.id)}
                                    >
                                        View payslip
                                    </Button>
                                </div>
                            </section>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-2xl border bg-white px-6 py-14 text-center">
                        <ReceiptText
                            className="mx-auto mb-3 text-red-700"
                            size={32}
                        />
                        <h2 className="text-lg font-semibold">
                            No payslips yet
                        </h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Your payslips will appear here when accounting
                            records your payroll as paid.
                        </p>
                    </div>
                )}
                {payslips.last_page > 1 && (
                    <nav
                        aria-label="Payslip pages"
                        className="flex items-center justify-end gap-4 text-sm"
                    >
                        {payslips.prev_page_url && (
                            <Link
                                href={payslips.prev_page_url}
                                className="rounded-lg border bg-white px-3 py-2"
                            >
                                Previous
                            </Link>
                        )}
                        <span>
                            Page {payslips.current_page} of {payslips.last_page}
                        </span>
                        {payslips.next_page_url && (
                            <Link
                                href={payslips.next_page_url}
                                className="rounded-lg border bg-white px-3 py-2"
                            >
                                Next
                            </Link>
                        )}
                    </nav>
                )}
                {selected !== null && (
                    <PayslipDialog
                        key={selected}
                        id={selected}
                        onClose={() => setSelected(null)}
                    />
                )}
            </main>
        </>
    );
}

MyPayslips.layout = {
    breadcrumbs: [{ title: 'My Payslips', href: '/my-payslips' }],
};
