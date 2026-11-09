import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import {
    Banknote,
    Calculator,
    CalendarDays,
    CheckCircle2,
    CircleDollarSign,
    Plus,
    Search,
    TrendingUp,
    WalletCards,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { money } from '@/components/training-allowance-details';

type Row = {
    employee: {
        id: number;
        name: string;
        username: string;
        role: string;
        status: string;
    };
    eligible_basic_cents: number;
    confirmed_basic_cents: number;
    pending_basic_cents: number;
    eligible_minutes: number;
    thirteenth_month_cents: number;
    daily_accrual_cents: number;
    pending_attendance_days: number;
    payroll_count: number;
    last_payroll_date: string | null;
    last_attendance_date: string | null;
    missing_payroll_count: number;
    missing_rate_days: number;
    data_complete: boolean;
};
type Month = {
    month: string;
    basic_cents: number;
    accrued_cents: number;
    funded_cents: number;
    cumulative_accrued_cents: number;
    cumulative_funded_cents: number;
    balance_cents: number;
    progress_percent: number;
};
type Contribution = {
    id: number;
    contribution_month: string;
    amount_cents: number;
    reference: string | null;
    notes: string | null;
    voided_at: string | null;
    void_reason: string | null;
    created_by: string | null;
    created_at: string;
};
type Result = {
    records: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
    };
    summary: {
        eligible_basic_cents: number;
        confirmed_basic_cents: number;
        pending_basic_cents: number;
        thirteenth_month_cents: number;
        daily_accrual_cents: number;
        employees_with_accrual: number;
        incomplete_records: number;
    };
    fund: {
        funded_cents: number;
        balance_cents: number;
        excess_cents: number;
        progress_percent: number;
        contribution_count: number;
    };
    monthly: Month[];
    contributions: Contribution[];
    year: number;
};

const currentYear = new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
}).format(new Date());
const currentMonth = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
}).format(new Date());
const monthLabel = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Manila',
    }).format(new Date(`${value}-01T00:00:00+08:00`));
const shortMonth = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        timeZone: 'Asia/Manila',
    }).format(new Date(`${value}-01T00:00:00+08:00`));

export default function ThirteenthMonthPay() {
    const [filters, setFilters] = useState({
        year: currentYear,
        search: '',
        status: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [refresh, setRefresh] = useState(0);
    const [result, setResult] = useState<Result | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [adding, setAdding] = useState(false);
    const [voiding, setVoiding] = useState<Contribution | null>(null);
    const contribution = useForm({
        request_key: '',
        contribution_month: currentMonth,
        amount: '',
        reference: '',
        notes: '',
    });
    const voidForm = useForm({ void_reason: '' });

    useEffect(() => {
        const controller = new AbortController();
        fetch(`/accounting/thirteenth-month?${new URLSearchParams(applied)}`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message || 'Unable to load 13th-month accruals.',
                    );
                }

                return data as Result;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setResult(data);
                    setError('');
                }
            })
            .catch((reason: Error) => {
                if (!controller.signal.aborted) {
                    setError(reason.message);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [applied, refresh]);

    const maxMonthly = useMemo(
        () =>
            Math.max(
                1,
                ...(result?.monthly.map((month) =>
                    Math.max(month.accrued_cents, month.funded_cents),
                ) ?? [1]),
            ),
        [result],
    );
    const openContribution = () => {
        contribution.reset();
        contribution.clearErrors();
        contribution.setData({
            request_key: crypto.randomUUID(),
            contribution_month:
                `${filters.year}-${currentMonth.slice(5)}` <= currentMonth
                    ? `${filters.year}-${currentMonth.slice(5)}`
                    : currentMonth,
            amount: '',
            reference: '',
            notes: '',
        });
        setAdding(true);
    };
    const submitContribution = () =>
        contribution.post('/accounting/thirteenth-month/contributions', {
            preserveScroll: true,
            onSuccess: () => {
                setAdding(false);
                setSuccess('CEO fund contribution recorded.');
                setRefresh((value) => value + 1);
            },
        });
    const submitVoid = () => {
        if (!voiding) {
            return;
        }

        voidForm.patch(
            `/accounting/thirteenth-month/contributions/${voiding.id}/void`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setVoiding(null);
                    setSuccess(
                        'Contribution voided and removed from the active fund.',
                    );
                    setRefresh((value) => value + 1);
                },
            },
        );
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="flex items-center gap-2 text-xl font-bold">
                        <Calculator className="text-red-700" size={22} />
                        13th Month Fund Tracker
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Daily attendance accrual, confirmed payroll, and the
                        CEO-funded reserve in one view.
                    </p>
                </div>
                <Button
                    variant="contained"
                    startIcon={<Plus size={17} />}
                    onClick={openContribution}
                >
                    Record CEO contribution
                </Button>
            </div>
            <Alert severity="info">
                The obligation is eligible basic salary ÷ 12. Paid payroll is
                confirmed; newer attendance is estimated daily using the
                employee’s latest recorded basic rate. Overtime, allowances, and
                premiums are excluded.
            </Alert>
            {error && (
                <Alert severity="error" onClose={() => setError('')}>
                    {error}
                </Alert>
            )}
            {success && (
                <Alert severity="success" onClose={() => setSuccess('')}>
                    {success}
                </Alert>
            )}

            <section className="overflow-hidden rounded-2xl border border-red-200 bg-[linear-gradient(120deg,#fff_0%,#fff7f7_58%,#ffeded_100%)] p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-5">
                    <div>
                        <p className="text-xs font-bold tracking-[0.18em] text-red-700 uppercase">
                            {result?.year ?? filters.year} funding progress
                        </p>
                        <p className="mt-2 text-3xl font-bold text-slate-950">
                            {(result?.fund.progress_percent ?? 0).toFixed(2)}%
                        </p>
                        <p className="mt-1 text-sm text-slate-600">
                            {money(result?.fund.funded_cents ?? 0)} funded of{' '}
                            {money(result?.summary.thirteenth_month_cents ?? 0)}{' '}
                            accrued
                        </p>
                    </div>
                    <div
                        className={`rounded-2xl px-4 py-3 ${result?.fund.balance_cents ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'}`}
                    >
                        <p className="text-xs font-bold uppercase">
                            {result?.fund.balance_cents
                                ? 'Remaining balance'
                                : 'Fund status'}
                        </p>
                        <p className="mt-1 text-xl font-bold">
                            {result?.fund.balance_cents
                                ? money(result.fund.balance_cents)
                                : 'Fully funded'}
                        </p>
                        {Boolean(result?.fund.excess_cents) && (
                            <p className="mt-1 text-xs">
                                {money(result?.fund.excess_cents ?? 0)} carried
                                forward
                            </p>
                        )}
                    </div>
                </div>
                <div className="mt-5 h-4 overflow-hidden rounded-full bg-white shadow-inner">
                    <div
                        className="h-full rounded-full bg-[linear-gradient(90deg,#991b1b,#dc2626,#f87171)] transition-[width] duration-500"
                        style={{
                            width: `${result?.fund.progress_percent ?? 0}%`,
                        }}
                    />
                </div>
                <div className="mt-2 flex justify-between text-xs font-medium text-slate-500">
                    <span>₱0</span>
                    <span>
                        {result?.fund.contribution_count ?? 0} contribution
                        {result?.fund.contribution_count === 1 ? '' : 's'}
                    </span>
                    <span>
                        {money(result?.summary.thirteenth_month_cents ?? 0)}
                    </span>
                </div>
            </section>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    {
                        label: 'Total accrued',
                        value: money(
                            result?.summary.thirteenth_month_cents ?? 0,
                        ),
                        Icon: CircleDollarSign,
                        tone: 'bg-red-50 text-red-700',
                    },
                    {
                        label: 'CEO fund balance',
                        value: money(result?.fund.funded_cents ?? 0),
                        Icon: WalletCards,
                        tone: 'bg-emerald-50 text-emerald-700',
                    },
                    {
                        label: 'Confirmed from payroll',
                        value: money(
                            (result?.summary.confirmed_basic_cents ?? 0) / 12,
                        ),
                        Icon: CheckCircle2,
                        tone: 'bg-sky-50 text-sky-700',
                    },
                    {
                        label: 'Live attendance addition',
                        value: money(result?.summary.daily_accrual_cents ?? 0),
                        Icon: TrendingUp,
                        tone: 'bg-amber-50 text-amber-700',
                    },
                ].map(({ label, value, Icon, tone }) => (
                    <div
                        key={String(label)}
                        className="rounded-xl border border-slate-200 p-4"
                    >
                        <div
                            className={`flex size-9 items-center justify-center rounded-xl ${tone}`}
                        >
                            <Icon size={18} />
                        </div>
                        <p className="mt-3 text-xs text-slate-500">{label}</p>
                        <p className="mt-1 text-xl font-bold">{value}</p>
                    </div>
                ))}
            </div>

            <section className="rounded-2xl border border-slate-200 p-5">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h3 className="font-bold text-slate-950">
                            Monthly funding against accrual
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Red is the obligation earned that month; green is
                            the CEO contribution.
                        </p>
                    </div>
                    <CalendarDays className="text-red-700" />
                </div>
                <div className="mt-6 grid h-52 grid-cols-12 items-end gap-2">
                    {result?.monthly.map((month) => (
                        <div
                            key={month.month}
                            className="flex h-full min-w-0 flex-col justify-end gap-1"
                        >
                            <div className="flex flex-1 items-end justify-center gap-1">
                                <div
                                    title={`Accrued ${money(month.accrued_cents)}`}
                                    className="w-2.5 rounded-t bg-red-500"
                                    style={{
                                        height: `${Math.max(month.accrued_cents ? 3 : 0, (month.accrued_cents / maxMonthly) * 100)}%`,
                                    }}
                                />
                                <div
                                    title={`Funded ${money(month.funded_cents)}`}
                                    className="w-2.5 rounded-t bg-emerald-500"
                                    style={{
                                        height: `${Math.max(month.funded_cents ? 3 : 0, (month.funded_cents / maxMonthly) * 100)}%`,
                                    }}
                                />
                            </div>
                            <p className="truncate text-center text-[10px] font-semibold text-slate-500">
                                {shortMonth(month.month)}
                            </p>
                        </div>
                    ))}
                </div>
                <div className="mt-5 overflow-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="p-3">Month</th>
                                <th className="p-3">Accrued</th>
                                <th className="p-3">CEO contribution</th>
                                <th className="p-3">Cumulative obligation</th>
                                <th className="p-3">Cumulative fund</th>
                                <th className="p-3">Balance</th>
                                <th className="p-3">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result?.monthly.map((month) => (
                                <tr
                                    key={month.month}
                                    className="border-t border-slate-100"
                                >
                                    <td className="p-3 font-semibold">
                                        {monthLabel(month.month)}
                                    </td>
                                    <td className="p-3">
                                        {money(month.accrued_cents)}
                                    </td>
                                    <td className="p-3 text-emerald-700">
                                        {money(month.funded_cents)}
                                    </td>
                                    <td className="p-3">
                                        {money(month.cumulative_accrued_cents)}
                                    </td>
                                    <td className="p-3">
                                        {money(month.cumulative_funded_cents)}
                                    </td>
                                    <td className="p-3 font-semibold text-red-700">
                                        {money(month.balance_cents)}
                                    </td>
                                    <td className="p-3">
                                        {month.progress_percent.toFixed(1)}%
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="rounded-2xl border border-slate-200 p-5">
                <div className="mb-4 flex items-center gap-2">
                    <Banknote className="text-red-700" size={20} />
                    <div>
                        <h3 className="font-bold">CEO contribution history</h3>
                        <p className="text-xs text-slate-500">
                            Voided records remain visible for audit purposes.
                        </p>
                    </div>
                </div>
                <div className="overflow-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                            <tr>
                                <th className="p-3">Month</th>
                                <th className="p-3">Amount</th>
                                <th className="p-3">Reference</th>
                                <th className="p-3">Recorded by</th>
                                <th className="p-3">Notes</th>
                                <th className="p-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result?.contributions.map((item) => (
                                <tr
                                    key={item.id}
                                    className={`border-t border-slate-100 ${item.voided_at ? 'opacity-50' : ''}`}
                                >
                                    <td className="p-3 font-semibold">
                                        {monthLabel(item.contribution_month)}
                                    </td>
                                    <td className="p-3 font-bold text-emerald-700">
                                        {money(item.amount_cents)}
                                    </td>
                                    <td className="p-3">
                                        {item.reference || '—'}
                                    </td>
                                    <td className="p-3">
                                        {item.created_by || 'Former user'}
                                    </td>
                                    <td
                                        className="max-w-xs truncate p-3"
                                        title={
                                            item.notes || item.void_reason || ''
                                        }
                                    >
                                        {item.voided_at
                                            ? `Void: ${item.void_reason}`
                                            : item.notes || '—'}
                                    </td>
                                    <td className="p-3 text-right">
                                        {item.voided_at ? (
                                            <span className="text-xs font-bold text-slate-500">
                                                VOID
                                            </span>
                                        ) : (
                                            <Button
                                                size="small"
                                                color="error"
                                                onClick={() => {
                                                    voidForm.reset();
                                                    voidForm.clearErrors();
                                                    setVoiding(item);
                                                }}
                                            >
                                                Void
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!result?.contributions.length && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-slate-500"
                                    >
                                        No CEO fund contributions recorded for
                                        this year.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <form
                className="grid gap-3 md:grid-cols-[150px_1fr_190px_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setApplied({ ...filters, page: '1' });
                }}
            >
                <TextField
                    size="small"
                    type="number"
                    label="Year"
                    value={filters.year}
                    onChange={(event) =>
                        setFilters({ ...filters, year: event.target.value })
                    }
                    slotProps={{
                        htmlInput: { min: 2020, max: Number(currentYear) },
                    }}
                />
                <TextField
                    size="small"
                    label="Search employee or ID"
                    value={filters.search}
                    onChange={(event) =>
                        setFilters({ ...filters, search: event.target.value })
                    }
                />
                <TextField
                    size="small"
                    select
                    label="Account status"
                    value={filters.status}
                    onChange={(event) =>
                        setFilters({ ...filters, status: event.target.value })
                    }
                >
                    <MenuItem value="">All statuses</MenuItem>
                    {[
                        'active',
                        'floating',
                        'resigned',
                        'suspended',
                        'terminated',
                    ].map((status) => (
                        <MenuItem key={status} value={status}>
                            {status.replaceAll('_', ' ')}
                        </MenuItem>
                    ))}
                </TextField>
                <Button
                    type="submit"
                    variant="outlined"
                    startIcon={<Search size={17} />}
                >
                    Filter
                </Button>
            </form>

            <div className="overflow-auto rounded-xl border border-slate-200">
                <table className="w-full min-w-[1050px] text-left text-sm">
                    <thead className="bg-red-50 text-red-800">
                        <tr>
                            <th className="p-3">Employee</th>
                            <th className="p-3">Attendance hours</th>
                            <th className="p-3">Confirmed basic</th>
                            <th className="p-3">Live basic estimate</th>
                            <th className="p-3">Running 13th month</th>
                            <th className="p-3">Latest update</th>
                            <th className="p-3">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        {result?.records.data.map((row) => (
                            <tr
                                key={row.employee.id}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">
                                    <p className="font-bold">
                                        {row.employee.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.employee.username} ·{' '}
                                        {row.employee.status.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </p>
                                </td>
                                <td className="p-3">
                                    {(row.eligible_minutes / 60).toFixed(2)}
                                </td>
                                <td className="p-3">
                                    {money(row.confirmed_basic_cents)}
                                </td>
                                <td className="p-3">
                                    <p>{money(row.pending_basic_cents)}</p>
                                    <p className="text-xs text-slate-500">
                                        {row.pending_attendance_days}{' '}
                                        unprocessed day(s)
                                    </p>
                                </td>
                                <td className="p-3 font-bold text-red-700">
                                    {money(row.thirteenth_month_cents)}
                                </td>
                                <td className="p-3">
                                    {row.last_attendance_date ||
                                        row.last_payroll_date ||
                                        '—'}
                                </td>
                                <td className="p-3">
                                    {row.data_complete ? (
                                        <span className="text-xs font-bold text-emerald-700">
                                            Complete
                                        </span>
                                    ) : (
                                        <span className="text-xs text-amber-700">
                                            {row.missing_rate_days
                                                ? `${row.missing_rate_days} attendance day(s) need a payroll rate`
                                                : `${row.missing_payroll_count} payroll record(s) need review`}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {!loading && !result?.records.data.length && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="p-10 text-center text-slate-500"
                                >
                                    No employees match these filters.
                                </td>
                            </tr>
                        )}
                        {loading && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="p-10 text-center text-slate-500"
                                >
                                    Calculating from payroll and attendance…
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {(result?.records.last_page ?? 1) > 1 && (
                <div className="flex justify-end gap-2">
                    <Button
                        disabled={(result?.records.current_page ?? 1) <= 1}
                        onClick={() =>
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.records.current_page ?? 1) - 1,
                                ),
                            })
                        }
                    >
                        Previous
                    </Button>
                    <Button
                        disabled={
                            (result?.records.current_page ?? 1) >=
                            (result?.records.last_page ?? 1)
                        }
                        onClick={() =>
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.records.current_page ?? 1) + 1,
                                ),
                            })
                        }
                    >
                        Next
                    </Button>
                </div>
            )}

            <Dialog
                open={adding}
                onClose={() => !contribution.processing && setAdding(false)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Record CEO contribution
                    <IconButton
                        aria-label="Close"
                        onClick={() => setAdding(false)}
                        disabled={contribution.processing}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers className="space-y-4">
                    <Alert severity="info">
                        Record the amount allocated to the pooled 13th-month
                        fund for a specific month. Multiple deposits in one
                        month are allowed.
                    </Alert>
                    <TextField
                        fullWidth
                        type="month"
                        label="Contribution month"
                        value={contribution.data.contribution_month}
                        onChange={(event) =>
                            contribution.setData(
                                'contribution_month',
                                event.target.value,
                            )
                        }
                        error={!!contribution.errors.contribution_month}
                        helperText={contribution.errors.contribution_month}
                        slotProps={{ htmlInput: { max: currentMonth } }}
                    />
                    <TextField
                        fullWidth
                        label="Amount (PHP)"
                        placeholder="0.00"
                        value={contribution.data.amount}
                        onChange={(event) =>
                            contribution.setData('amount', event.target.value)
                        }
                        error={!!contribution.errors.amount}
                        helperText={contribution.errors.amount}
                    />
                    <TextField
                        fullWidth
                        label="Reference (optional)"
                        value={contribution.data.reference}
                        onChange={(event) =>
                            contribution.setData(
                                'reference',
                                event.target.value,
                            )
                        }
                        error={!!contribution.errors.reference}
                        helperText={contribution.errors.reference}
                    />
                    <TextField
                        fullWidth
                        multiline
                        minRows={3}
                        label="Notes (optional)"
                        value={contribution.data.notes}
                        onChange={(event) =>
                            contribution.setData('notes', event.target.value)
                        }
                        error={!!contribution.errors.notes}
                        helperText={contribution.errors.notes}
                    />
                </DialogContent>
                <DialogActions className="p-4">
                    <Button
                        onClick={() => setAdding(false)}
                        disabled={contribution.processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        onClick={submitContribution}
                        disabled={
                            contribution.processing || !contribution.data.amount
                        }
                    >
                        Save contribution
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={Boolean(voiding)}
                onClose={() => !voidForm.processing && setVoiding(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Void contribution
                    <IconButton
                        aria-label="Close"
                        onClick={() => setVoiding(null)}
                        disabled={voidForm.processing}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers className="space-y-4">
                    <Alert severity="warning">
                        This keeps the record in the audit history but removes{' '}
                        {money(voiding?.amount_cents ?? 0)} from the active fund
                        total.
                    </Alert>
                    <TextField
                        fullWidth
                        multiline
                        minRows={3}
                        label="Reason"
                        value={voidForm.data.void_reason}
                        onChange={(event) =>
                            voidForm.setData('void_reason', event.target.value)
                        }
                        error={!!voidForm.errors.void_reason}
                        helperText={voidForm.errors.void_reason}
                    />
                </DialogContent>
                <DialogActions className="p-4">
                    <Button
                        onClick={() => setVoiding(null)}
                        disabled={voidForm.processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        color="error"
                        variant="contained"
                        onClick={submitVoid}
                        disabled={
                            voidForm.processing ||
                            voidForm.data.void_reason.trim().length < 5
                        }
                    >
                        Void contribution
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
}
