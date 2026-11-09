import {
    Alert,
    Button,
    Chip,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
    LinearProgress,
    MenuItem,
    TextField,
} from '@mui/material';
import {
    CalendarCheck,
    CircleDollarSign,
    Clock3,
    Search,
    UserRoundCheck,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import TrainingAllowanceDetails, {
    money,
} from '@/components/training-allowance-details';
import type { Allowance } from '@/components/training-allowance-details';

type TrackerRow = {
    trainee: {
        id: number;
        name: string;
        username: string;
        training_status: string;
    };
    plan: {
        id: number;
        name: string;
        campaign: string | null;
        start_date: string;
        first_allowance_day: number;
    };
    attendance: {
        attended_days: number;
        expected_days: number;
        missed_days: number;
        upcoming_days: number;
        total_days: number;
        worked_minutes: number;
        rate_percent: number;
        progress_percent: number;
    };
    salary: {
        earned_cents: number;
        paid_cents: number;
        reserved_cents: number;
        outstanding_cents: number;
        available_cents: number;
        projected_total_cents: number;
        first_allowance_cents: number;
        first_allowance_ready: boolean;
        first_allowance_date: string | null;
        payout_status: string;
    };
};

type TrackerResult = {
    records: {
        data: TrackerRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    summary: {
        trainees: number;
        attended_days: number;
        earned_cents: number;
        paid_cents: number;
        reserved_cents: number;
        outstanding_cents: number;
        available_cents: number;
    };
};

type Detail = {
    allowance: Allowance;
    plan: {
        id: number;
        name: string;
        first_allowance_day: number;
    };
    salary: {
        paid_cents: number;
        reserved_cents: number;
        outstanding_cents: number;
    };
};

const statusLabel: Record<string, string> = {
    in_training: 'Active trainee',
    graduated: 'Passed',
    rejected: 'Failed',
};

export default function TraineeAttendanceSalaryTracker() {
    const [filters, setFilters] = useState({
        search: '',
        status: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [result, setResult] = useState<TrackerResult | null>(null);
    const [detail, setDetail] = useState<Detail | null>(null);
    const [loading, setLoading] = useState(true);
    const [detailLoading, setDetailLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const controller = new AbortController();

        fetch(`/accounting/trainee-tracker?${new URLSearchParams(applied)}`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message || 'Unable to load the trainee tracker.',
                    );
                }

                return data as TrackerResult;
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
    }, [applied]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setApplied({ ...filters, page: '1' });
    };

    const changePage = (page: number) => {
        setLoading(true);
        setFilters((current) => ({ ...current, page: String(page) }));
        setApplied((current) => ({ ...current, page: String(page) }));
    };

    const showDetail = async (row: TrackerRow) => {
        setDetailLoading(true);
        setError('');

        try {
            const response = await fetch(
                `/accounting/trainee-tracker/${row.plan.id}/${row.trainee.id}`,
                { headers: { Accept: 'application/json' }, cache: 'no-store' },
            );
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Unable to load trainee details.',
                );
            }

            setDetail(data);
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to load trainee details.',
            );
        } finally {
            setDetailLoading(false);
        }
    };

    const summary = result?.summary;

    return (
        <div className="space-y-5">
            <Alert severity="info">
                Trainee salary follows completed attendance and the daily rate
                configured for each training phase. A day earns its full rate
                after a completed time-in and time-out. The first release stays
                locked until the configured attended-day threshold is reached.
            </Alert>

            {error && (
                <Alert severity="error" onClose={() => setError('')}>
                    {error}
                </Alert>
            )}

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    {
                        label: 'Enrolled trainees',
                        value: String(summary?.trainees ?? 0),
                        Icon: UserRoundCheck,
                        tone: 'bg-red-50 text-red-700',
                    },
                    {
                        label: 'Attended training days',
                        value: String(summary?.attended_days ?? 0),
                        Icon: CalendarCheck,
                        tone: 'bg-sky-50 text-sky-700',
                    },
                    {
                        label: 'Salary accrued',
                        value: money(summary?.earned_cents ?? 0),
                        Icon: CircleDollarSign,
                        tone: 'bg-emerald-50 text-emerald-700',
                    },
                    {
                        label: 'Available for payout',
                        value: money(summary?.available_cents ?? 0),
                        Icon: Clock3,
                        tone: 'bg-amber-50 text-amber-700',
                    },
                ].map(({ label, value, Icon, tone }) => (
                    <div
                        key={label}
                        className="rounded-2xl border border-slate-200 p-4"
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

            <form
                onSubmit={submit}
                className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 md:grid-cols-[1fr_220px_auto]"
            >
                <TextField
                    size="small"
                    label="Search trainee name or ID"
                    value={filters.search}
                    onChange={(event) =>
                        setFilters((current) => ({
                            ...current,
                            search: event.target.value,
                        }))
                    }
                />
                <TextField
                    size="small"
                    select
                    label="Training status"
                    value={filters.status}
                    onChange={(event) =>
                        setFilters((current) => ({
                            ...current,
                            status: event.target.value,
                        }))
                    }
                >
                    <MenuItem value="">All statuses</MenuItem>
                    <MenuItem value="in_training">Active trainee</MenuItem>
                    <MenuItem value="graduated">Passed</MenuItem>
                    <MenuItem value="rejected">Failed</MenuItem>
                </TextField>
                <Button
                    type="submit"
                    variant="outlined"
                    startIcon={<Search size={17} />}
                    disabled={loading}
                >
                    Filter
                </Button>
            </form>

            <div className="overflow-auto rounded-2xl border border-slate-200">
                <table className="w-full min-w-[1120px] text-left text-sm">
                    <thead className="bg-red-50 text-red-900">
                        <tr>
                            {[
                                'Trainee / plan',
                                'Status',
                                'Attendance',
                                'Worked time',
                                'Accrued salary',
                                'Paid / reserved',
                                'Outstanding',
                                'Payout status',
                                '',
                            ].map((label) => (
                                <th key={label} className="p-3">
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {result?.records.data.map((row) => (
                            <tr
                                key={`${row.plan.id}:${row.trainee.id}`}
                                className="border-t border-slate-100 align-top"
                            >
                                <td className="p-3">
                                    <p className="font-bold">
                                        {row.trainee.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.trainee.username} · {row.plan.name}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {row.plan.campaign || 'No campaign'}
                                    </p>
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        color={
                                            row.trainee.training_status ===
                                            'in_training'
                                                ? 'success'
                                                : row.trainee
                                                        .training_status ===
                                                    'graduated'
                                                  ? 'info'
                                                  : 'default'
                                        }
                                        label={
                                            statusLabel[
                                                row.trainee.training_status
                                            ] || row.trainee.training_status
                                        }
                                    />
                                </td>
                                <td className="p-3">
                                    <p className="font-semibold">
                                        {row.attendance.attended_days} /{' '}
                                        {row.attendance.total_days} days
                                    </p>
                                    <LinearProgress
                                        className="mt-2"
                                        variant="determinate"
                                        value={Math.min(
                                            100,
                                            row.attendance.progress_percent,
                                        )}
                                        color="error"
                                    />
                                    <p className="mt-2 text-xs text-slate-500">
                                        {row.attendance.rate_percent}%
                                        attendance ·{' '}
                                        {row.attendance.missed_days} missed
                                    </p>
                                </td>
                                <td className="p-3">
                                    {Math.floor(
                                        row.attendance.worked_minutes / 60,
                                    )}
                                    h {row.attendance.worked_minutes % 60}m
                                </td>
                                <td className="p-3">
                                    <p className="font-bold">
                                        {money(row.salary.earned_cents)}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        of{' '}
                                        {money(
                                            row.salary.projected_total_cents,
                                        )}{' '}
                                        projected
                                    </p>
                                </td>
                                <td className="p-3">
                                    <p>{money(row.salary.paid_cents)} paid</p>
                                    <p className="text-xs text-slate-500">
                                        {money(row.salary.reserved_cents)} in
                                        draft / approval
                                    </p>
                                </td>
                                <td className="p-3">
                                    <p className="font-bold text-red-700">
                                        {money(row.salary.outstanding_cents)}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {money(row.salary.available_cents)}{' '}
                                        ready
                                    </p>
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        color={
                                            row.salary.payout_status ===
                                            'Ready for payout'
                                                ? 'warning'
                                                : row.salary.payout_status ===
                                                    'Up to date'
                                                  ? 'success'
                                                  : 'default'
                                        }
                                        label={row.salary.payout_status}
                                    />
                                </td>
                                <td className="p-3">
                                    <Button
                                        size="small"
                                        onClick={() => showDetail(row)}
                                        disabled={detailLoading}
                                    >
                                        Details
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {!loading && !result?.records.data.length && (
                    <p className="p-10 text-center text-slate-500">
                        No enrolled trainees match these filters.
                    </p>
                )}
                {loading && (
                    <p className="p-10 text-center text-slate-500">
                        Loading trainee attendance and salary…
                    </p>
                )}
            </div>

            {(result?.records.last_page ?? 1) > 1 && (
                <div className="flex items-center justify-end gap-3 text-sm">
                    <Button
                        disabled={(result?.records.current_page ?? 1) <= 1}
                        onClick={() =>
                            changePage((result?.records.current_page ?? 1) - 1)
                        }
                    >
                        Previous
                    </Button>
                    <span>
                        Page {result?.records.current_page} of{' '}
                        {result?.records.last_page}
                    </span>
                    <Button
                        disabled={
                            (result?.records.current_page ?? 1) >=
                            (result?.records.last_page ?? 1)
                        }
                        onClick={() =>
                            changePage((result?.records.current_page ?? 1) + 1)
                        }
                    >
                        Next
                    </Button>
                </div>
            )}

            <Dialog
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                fullWidth
                maxWidth="lg"
            >
                <DialogTitle className="flex items-center justify-between gap-3">
                    <span>
                        {detail?.allowance.trainee.name} · Attendance and salary
                    </span>
                    <IconButton
                        aria-label="Close trainee details"
                        onClick={() => setDetail(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    {detail && (
                        <div className="space-y-5">
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    ['Paid', detail.salary.paid_cents],
                                    [
                                        'Draft / approved',
                                        detail.salary.reserved_cents,
                                    ],
                                    [
                                        'Outstanding',
                                        detail.salary.outstanding_cents,
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        key={String(label)}
                                        className="rounded-xl border border-slate-200 p-4"
                                    >
                                        <p className="text-xs text-slate-500">
                                            {label}
                                        </p>
                                        <p className="mt-1 text-lg font-bold">
                                            {money(Number(value))}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <TrainingAllowanceDetails
                                allowance={detail.allowance}
                                threshold={detail.plan.first_allowance_day}
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
