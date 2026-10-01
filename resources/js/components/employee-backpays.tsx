import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { ScrollText, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { money } from '@/components/training-allowance-details';

type Employee = {
    id: number;
    name: string;
    username: string;
    role: string;
    status: 'resigned' | 'terminated';
};
type Accrual = {
    eligible_basic_cents: number;
    eligible_minutes: number;
    thirteenth_month_cents: number;
    payroll_count: number;
    data_complete: boolean;
};
type Backpay = {
    id: number;
    user_id: number;
    separation_date: string;
    separation_status: string;
    calendar_year: number;
    eligible_basic_cents: number;
    thirteenth_month_cents: number;
    last_payroll_cents: number;
    additions_cents: number;
    deductions_cents: number;
    total_cents: number;
    status: string;
    claimed_at: string | null;
    notes: string | null;
    employee: Employee;
};
type Row = { employee: Employee; accrual: Accrual; backpay: Backpay | null };
type Result = {
    rows: Row[];
    summary: {
        eligible_employees: number;
        pending: number;
        ready: number;
        claimed: number;
        total_cents: number;
    };
    year: number;
};
const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
const currentYear = today().slice(0, 4);
const amount = (cents: number) => (cents / 100).toFixed(2);

export default function EmployeeBackpays() {
    const [filters, setFilters] = useState({
        year: currentYear,
        search: '',
        status: '',
    });
    const [applied, setApplied] = useState(filters);
    const [result, setResult] = useState<Result | null>(null);
    const [selected, setSelected] = useState<Row | null>(null);
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const form = useForm({
        request_key: '',
        user_id: '',
        separation_date: today(),
        last_payroll: '0.00',
        additions: '0.00',
        deductions: '0.00',
        status: 'pending_clearance',
        claimed_at: '',
        notes: '',
    });

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        fetch(`/accounting/backpays?${new URLSearchParams(applied)}`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok)
                    throw new Error(
                        data.message || 'Unable to load backpay records.',
                    );
                return data as Result;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setResult(data);
                    setError('');
                }
            })
            .catch((reason: Error) => {
                if (!controller.signal.aborted) setError(reason.message);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [applied, revision]);

    const open = (row: Row) => {
        const record = row.backpay;
        setSelected(row);
        form.clearErrors();
        form.setData({
            request_key: record ? '' : crypto.randomUUID(),
            user_id: String(row.employee.id),
            separation_date: record?.separation_date || today(),
            last_payroll: amount(record?.last_payroll_cents ?? 0),
            additions: amount(record?.additions_cents ?? 0),
            deductions: amount(record?.deductions_cents ?? 0),
            status: record?.status || 'pending_clearance',
            claimed_at: record?.claimed_at || '',
            notes: record?.notes || '',
        });
    };
    const submit = () => {
        if (!selected) return;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setSelected(null);
                setNotice(
                    selected.backpay
                        ? 'Backpay record updated.'
                        : 'Backpay record prepared.',
                );
                setRevision((value) => value + 1);
            },
        };
        if (selected.backpay)
            form.patch(`/accounting/backpays/${selected.backpay.id}`, options);
        else form.post('/accounting/backpays', options);
    };
    const computed = selected
        ? selected.accrual.thirteenth_month_cents +
          Math.round(Number(form.data.last_payroll || 0) * 100) +
          Math.round(Number(form.data.additions || 0) * 100) -
          Math.round(Number(form.data.deductions || 0) * 100)
        : 0;
    const readonly = selected?.backpay?.status === 'claimed';

    return (
        <div className="space-y-5">
            <div>
                <h2 className="flex items-center gap-2 text-xl font-bold">
                    <ScrollText className="text-red-700" size={22} />
                    Employee backpay register
                </h2>
                <p className="text-sm text-slate-500">
                    Final pay records are available only for resigned and
                    terminated employees.
                </p>
            </div>
            <Alert severity="info">
                The 13th-month portion refreshes from paid basic payroll when
                you save. Last payroll, additions, deductions, clearance, and
                claim details remain under accounting control.
            </Alert>
            {notice && (
                <Alert severity="success" onClose={() => setNotice('')}>
                    {notice}
                </Alert>
            )}
            {error && (
                <Alert severity="error" onClose={() => setError('')}>
                    {error}
                </Alert>
            )}
            <div className="grid gap-3 sm:grid-cols-5">
                {[
                    [
                        'Eligible employees',
                        result?.summary.eligible_employees ?? 0,
                    ],
                    ['Pending clearance', result?.summary.pending ?? 0],
                    ['Ready to claim', result?.summary.ready ?? 0],
                    ['Claimed', result?.summary.claimed ?? 0],
                    ['Recorded total', money(result?.summary.total_cents ?? 0)],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-xl border border-slate-200 p-4"
                    >
                        <p className="text-xs text-slate-500">{label}</p>
                        <p className="mt-1 text-xl font-bold">{value}</p>
                    </div>
                ))}
            </div>
            <form
                className="grid gap-3 md:grid-cols-[150px_1fr_190px_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setApplied(filters);
                }}
            >
                <TextField
                    size="small"
                    type="number"
                    label="Year"
                    value={filters.year}
                    inputProps={{ min: 2020, max: Number(currentYear) }}
                    onChange={(event) =>
                        setFilters({ ...filters, year: event.target.value })
                    }
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
                    label="Backpay status"
                    value={filters.status}
                    onChange={(event) =>
                        setFilters({ ...filters, status: event.target.value })
                    }
                >
                    <MenuItem value="">All statuses</MenuItem>
                    {['pending_clearance', 'ready', 'claimed'].map((status) => (
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
                <table className="w-full min-w-[980px] text-left text-sm">
                    <thead className="bg-red-50 text-red-800">
                        <tr>
                            <th className="p-3">Employee</th>
                            <th className="p-3">Separation</th>
                            <th className="p-3">13th-month accrual</th>
                            <th className="p-3">Last payroll</th>
                            <th className="p-3">Additions / deductions</th>
                            <th className="p-3">Backpay total</th>
                            <th className="p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {result?.rows.map((row) => (
                            <tr
                                key={row.employee.id}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">
                                    <p className="font-bold">
                                        {row.employee.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.employee.username}
                                    </p>
                                </td>
                                <td className="p-3 capitalize">
                                    {row.employee.status}
                                    <p className="text-xs text-slate-500">
                                        {row.backpay?.separation_date ||
                                            'Date not recorded'}
                                    </p>
                                </td>
                                <td className="p-3 font-semibold">
                                    {money(
                                        row.backpay?.thirteenth_month_cents ??
                                            row.accrual.thirteenth_month_cents,
                                    )}
                                </td>
                                <td className="p-3">
                                    {money(
                                        row.backpay?.last_payroll_cents ?? 0,
                                    )}
                                </td>
                                <td className="p-3">
                                    <span className="text-emerald-700">
                                        +
                                        {money(
                                            row.backpay?.additions_cents ?? 0,
                                        )}
                                    </span>
                                    <br />
                                    <span className="text-red-700">
                                        −
                                        {money(
                                            row.backpay?.deductions_cents ?? 0,
                                        )}
                                    </span>
                                </td>
                                <td className="p-3 font-bold">
                                    {row.backpay
                                        ? money(row.backpay.total_cents)
                                        : '—'}
                                </td>
                                <td className="p-3">
                                    {row.backpay ? (
                                        <Chip
                                            size="small"
                                            color={
                                                row.backpay.status === 'claimed'
                                                    ? 'success'
                                                    : row.backpay.status ===
                                                        'ready'
                                                      ? 'primary'
                                                      : 'default'
                                            }
                                            label={row.backpay.status.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        />
                                    ) : (
                                        <Chip
                                            size="small"
                                            variant="outlined"
                                            label="Not prepared"
                                        />
                                    )}
                                </td>
                                <td className="p-3">
                                    <Button
                                        size="small"
                                        variant="outlined"
                                        onClick={() => open(row)}
                                    >
                                        {row.backpay ? 'Review' : 'Prepare'}
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {!loading && !result?.rows.length && (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="p-10 text-center text-slate-500"
                                >
                                    No resigned or terminated employees match
                                    these filters.
                                </td>
                            </tr>
                        )}
                        {loading && (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="p-10 text-center text-slate-500"
                                >
                                    Loading backpay records…
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <Dialog
                open={Boolean(selected)}
                onClose={() => !form.processing && setSelected(null)}
                fullWidth
                maxWidth="md"
                PaperProps={{ sx: { borderRadius: 4 } }}
            >
                <DialogTitle className="flex items-center justify-between">
                    <span>
                        {selected?.backpay
                            ? 'Backpay record'
                            : 'Prepare backpay'}
                    </span>
                    <IconButton
                        aria-label="Close modal"
                        onClick={() => setSelected(null)}
                        disabled={form.processing}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers className="space-y-4">
                    <div className="rounded-2xl bg-red-50 p-4">
                        <p className="font-bold">{selected?.employee.name}</p>
                        <p className="text-sm text-slate-600">
                            {selected?.employee.username} ·{' '}
                            {selected?.employee.status}
                        </p>
                        <p className="mt-3 text-sm">
                            Running 13th-month accrual
                        </p>
                        <p className="text-2xl font-bold text-red-700">
                            {money(
                                selected?.accrual.thirteenth_month_cents ?? 0,
                            )}
                        </p>
                        <p className="text-xs text-slate-500">
                            From {selected?.accrual.payroll_count ?? 0} paid
                            payroll record(s).
                        </p>
                    </div>
                    {Object.values(form.errors).map((message, index) => (
                        <Alert key={index} severity="error">
                            {message}
                        </Alert>
                    ))}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            disabled={readonly}
                            type="date"
                            label="Separation date"
                            value={form.data.separation_date}
                            onChange={(event) =>
                                form.setData(
                                    'separation_date',
                                    event.target.value,
                                )
                            }
                            InputLabelProps={{ shrink: true }}
                        />
                        <TextField
                            disabled={readonly}
                            select
                            label="Backpay status"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            {['pending_clearance', 'ready', 'claimed'].map(
                                (status) => (
                                    <MenuItem key={status} value={status}>
                                        {status.replaceAll('_', ' ')}
                                    </MenuItem>
                                ),
                            )}
                        </TextField>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <TextField
                            disabled={readonly}
                            label="Last payroll (PHP)"
                            value={form.data.last_payroll}
                            onChange={(event) =>
                                form.setData('last_payroll', event.target.value)
                            }
                        />
                        <TextField
                            disabled={readonly}
                            label="Other additions (PHP)"
                            value={form.data.additions}
                            onChange={(event) =>
                                form.setData('additions', event.target.value)
                            }
                        />
                        <TextField
                            disabled={readonly}
                            label="Deductions (PHP)"
                            value={form.data.deductions}
                            onChange={(event) =>
                                form.setData('deductions', event.target.value)
                            }
                        />
                    </div>
                    {form.data.status === 'claimed' && (
                        <TextField
                            disabled={readonly}
                            fullWidth
                            type="date"
                            label="Date claimed"
                            value={form.data.claimed_at}
                            onChange={(event) =>
                                form.setData('claimed_at', event.target.value)
                            }
                            InputLabelProps={{ shrink: true }}
                        />
                    )}
                    <TextField
                        disabled={readonly}
                        fullWidth
                        multiline
                        minRows={3}
                        label="Remarks"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                    <div className="rounded-2xl border border-slate-200 p-4">
                        <p className="text-sm text-slate-500">
                            Calculated backpay total
                        </p>
                        <p className="text-3xl font-bold">
                            {money(Math.max(0, computed))}
                        </p>
                        <p className="text-xs text-slate-500">
                            13th-month accrual + last payroll + additions −
                            deductions
                        </p>
                    </div>
                </DialogContent>
                <DialogActions className="p-4">
                    {!readonly && (
                        <Button
                            variant="contained"
                            onClick={submit}
                            disabled={form.processing}
                        >
                            {selected?.backpay
                                ? 'Save changes'
                                : 'Prepare backpay'}
                        </Button>
                    )}
                </DialogActions>
            </Dialog>
        </div>
    );
}
