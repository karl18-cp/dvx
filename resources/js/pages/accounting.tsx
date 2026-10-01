import { Head, Link, router, useForm } from '@inertiajs/react';
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
    Tab,
    Tabs,
    TextField,
} from '@mui/material';
import { BadgeDollarSign, Mail, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import AccountingDocuments from '@/components/accounting-documents';
import AccountingExpenses from '@/components/accounting-expenses';
import EmployeeBankInformation from '@/components/employee-bank-information';
import EmployeeContracts from '@/components/employee-contracts';
import EmployeeBackpays from '@/components/employee-backpays';
import ThirteenthMonthPay from '@/components/thirteenth-month-pay';
import PayrollCalculation, {
    payrollDefaults,
    PayrollBreakdown,
} from '@/components/payroll-calculation';
import type { PayrollSnapshot } from '@/components/payroll-calculation';
import { money } from '@/components/training-allowance-details';
import type { Allowance } from '@/components/training-allowance-details';
import {
    accountingSections,
    accountingSectionUrl,
} from '@/lib/accounting-sections';

type Entry = {
    id: number;
    kind: string;
    description: string;
    period_start: string;
    period_end: string;
    gross_cents: number;
    deduction_cents: number;
    net_cents: number;
    status: string;
    paid_at: string | null;
    employee: { id: number; name: string; username: string; email?: string };
    notes?: string;
    payment_method?: string;
    payment_reference?: string;
    void_reason?: string;
    creator?: { name: string };
    approver?: { name: string };
    payer?: { name: string };
    source_snapshot?: {
        employee_name: string;
        employee_id: string;
        plan_name?: string;
        basis?: string;
        payroll?: PayrollSnapshot;
        rows?: Allowance['rows'];
    };
};
type Enrollment = {
    user_id: number;
    name: string;
    username: string;
    plan_id: number;
    plan_name: string;
};
type Preview = Allowance & {
    claim_rows: Allowance['rows'];
    claim_cents: number;
    can_prepare: boolean;
    first_release: boolean;
    pending_first: boolean;
    paid_cents: number;
    reserved_cents: number;
    cutoff: string;
};
type Props = {
    section: string | null;
    entries: {
        data: Entry[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    summary: Record<'draft' | 'approved' | 'paid', number>;
    employees: { id: number; name: string; username: string; status: string }[];
    contractEmployees: {
        id: number;
        name: string;
        username: string;
        role: string;
        status: string;
    }[];
    enrollments: Enrollment[];
    filters: { status?: string; kind?: string; search?: string };
    statusMessage?: string;
};
const date = (value?: string | null) => (value ? value.slice(0, 10) : '—');
const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());

export default function Accounting({
    section,
    entries,
    summary,
    employees,
    contractEmployees,
    enrollments,
    filters,
    statusMessage,
}: Props) {
    const [adminTab, setTab] = useState(0);
    const tab = section
        ? Math.max(
              0,
              accountingSections.findIndex((item) => item.slug === section),
          )
        : adminTab;
    const currentSection = section ? accountingSections[tab] : null;
    const [payrollOpen, setPayrollOpen] = useState(false);
    const [attendanceReady, setAttendanceReady] = useState(false);
    const payroll = useForm({
        request_key: '',
        user_id: '',
        description: 'Payroll',
        ...payrollDefaults,
        notes: '',
    });
    const search = useForm({
        search: filters.search || '',
        status: filters.status || '',
        kind: filters.kind || '',
    });
    const [detail, setDetail] = useState<Entry | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [action, setAction] = useState<'approve' | 'pay' | 'void' | ''>('');
    const update = useForm({
        action: '',
        paid_at: today(),
        payment_method: 'bank_transfer',
        payment_reference: '',
        void_reason: '',
    });
    const emailPayslip = useForm({});
    const [enrollment, setEnrollment] = useState('');
    const [cutoff, setCutoff] = useState(today());
    const [preview, setPreview] = useState<Preview | null>(null);
    const [previewEnrollment, setPreviewEnrollment] =
        useState<Enrollment | null>(null);
    const [preparing, setPreparing] = useState(false);
    const [allowanceKey, setAllowanceKey] = useState('');
    const title = (text: string, close: () => void) => (
        <DialogTitle className="flex items-center justify-between gap-3">
            {text}
            <IconButton
                aria-label="Close modal"
                onClick={close}
                disabled={
                    update.processing ||
                    payroll.processing ||
                    emailPayslip.processing ||
                    preparing
                }
            >
                <X />
            </IconButton>
        </DialogTitle>
    );
    const show = async (id: number) => {
        setLoading(true);
        setError('');
        setAction('');
        update.clearErrors();
        emailPayslip.clearErrors();

        try {
            const response = await fetch(`/accounting/entries/${id}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Unable to load this entry.');
            }

            setDetail(await response.json());
        } catch (error) {
            setError(
                error instanceof Error
                    ? error.message
                    : 'Unable to load entry.',
            );
        } finally {
            setLoading(false);
        }
    };
    const loadAllowance = async () => {
        const selected = enrollments.find(
            (row) => `${row.plan_id}:${row.user_id}` === enrollment,
        );

        if (!selected) {
            return;
        }

        setLoading(true);
        setError('');
        setPreview(null);

        try {
            const response = await fetch(
                `/accounting/allowances/${selected.plan_id}/${selected.user_id}?cutoff=${cutoff}`,
                { headers: { Accept: 'application/json' } },
            );
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Unable to load allowance.');
            }

            setPreview(data);
            setPreviewEnrollment(selected);
            setAllowanceKey(crypto.randomUUID());
        } catch (error) {
            setError(
                error instanceof Error
                    ? error.message
                    : 'Unable to load allowance.',
            );
        } finally {
            setLoading(false);
        }
    };
    const prepareAllowance = () => {
        if (!previewEnrollment || !preview) {
            return;
        }

        setPreparing(true);
        setError('');
        router.post(
            `/accounting/allowances/${previewEnrollment.plan_id}/${previewEnrollment.user_id}`,
            { request_key: allowanceKey, cutoff: preview.cutoff },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPreview(null);
                    setTab(0);
                },
                onError: (errors) => setError(Object.values(errors).join(' ')),
                onFinish: () => setPreparing(false),
            },
        );
    };

    return (
        <main className="space-y-6 p-4 md:p-8">
            <Head title="Divertex" />
            <header className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-sm font-bold tracking-widest text-red-700 uppercase">
                        Finance workspace
                    </p>
                    <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                        <BadgeDollarSign className="text-red-700" />
                        {currentSection?.title || 'Accounting'}
                    </h1>
                    <p className="mt-2 text-slate-500">
                        {currentSection?.description ||
                            'Review payroll, prepare training allowances, and keep payment records.'}
                    </p>
                </div>
                {(!section || tab === 0) && (
                    <Button
                        variant="contained"
                        startIcon={<Plus size={18} />}
                        onClick={() => {
                            payroll.reset();
                            payroll.setData('pay_month', today().slice(0, 7));
                            payroll.clearErrors();
                            payroll.setData('request_key', crypto.randomUUID());
                            setPayrollOpen(true);
                        }}
                    >
                        New payroll entry
                    </Button>
                )}
                {section && tab === 1 && (
                    <Button
                        onClick={() =>
                            router.visit(
                                accountingSectionUrl('payment-register'),
                            )
                        }
                        variant="outlined"
                    >
                        View payment register
                    </Button>
                )}
            </header>
            {statusMessage && <Alert severity="success">{statusMessage}</Alert>}
            {error && (
                <Alert severity="error" onClose={() => setError('')}>
                    {error}
                </Alert>
            )}
            {(!section || tab === 0) && (
                <div className="grid gap-4 sm:grid-cols-3">
                    {(
                        [
                            ['draft', 'Drafts awaiting review'],
                            ['approved', 'Approved, awaiting payment'],
                            ['paid', 'Payments recorded · all time'],
                        ] as const
                    ).map(([key, label]) => (
                        <div
                            key={key}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <p className="text-sm text-slate-500">{label}</p>
                            <p className="mt-2 text-2xl font-bold">
                                {money(summary[key])}
                            </p>
                        </div>
                    ))}
                </div>
            )}
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                {!section && (
                    <Tabs
                        variant="scrollable"
                        scrollButtons="auto"
                        value={tab}
                        onChange={(_, value: number) => {
                            setTab(value);
                            setError('');
                        }}
                    >
                        <Tab label="Payment register" />
                        <Tab label="Training allowances" />
                        <Tab label="Expenses" />
                        <Tab label="Bank information" />
                        <Tab label="Employee contracts" />
                        <Tab label="Invoices" />
                        <Tab label="Receivables" />
                        <Tab label="Payables" />
                        <Tab label="Backpay" />
                        <Tab label="13th month pay" />
                    </Tabs>
                )}
                <div className="p-5">
                    {tab === 9 ? (
                        <ThirteenthMonthPay />
                    ) : tab === 8 ? (
                        <EmployeeBackpays />
                    ) : tab >= 5 && tab <= 7 ? (
                        <AccountingDocuments
                            key={tab}
                            view={
                                tab === 5
                                    ? 'invoices'
                                    : tab === 6
                                      ? 'receivable'
                                      : 'payable'
                            }
                        />
                    ) : tab === 4 ? (
                        <EmployeeContracts employees={contractEmployees} />
                    ) : tab === 3 ? (
                        <EmployeeBankInformation />
                    ) : tab === 2 ? (
                        <AccountingExpenses />
                    ) : tab === 0 ? (
                        <>
                            <form
                                className="mb-5 grid gap-3 md:grid-cols-[1fr_180px_200px_auto]"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    search.get(
                                        section
                                            ? accountingSectionUrl(
                                                  'payment-register',
                                              )
                                            : '/accounting',
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <TextField
                                    size="small"
                                    label="Search name or ID"
                                    value={search.data.search}
                                    onChange={(e) =>
                                        search.setData('search', e.target.value)
                                    }
                                />
                                <TextField
                                    size="small"
                                    select
                                    label="Status"
                                    value={search.data.status}
                                    onChange={(e) =>
                                        search.setData('status', e.target.value)
                                    }
                                >
                                    <MenuItem value="">All statuses</MenuItem>
                                    {['draft', 'approved', 'paid', 'void'].map(
                                        (value) => (
                                            <MenuItem key={value} value={value}>
                                                {value}
                                            </MenuItem>
                                        ),
                                    )}
                                </TextField>
                                <TextField
                                    size="small"
                                    select
                                    label="Type"
                                    value={search.data.kind}
                                    onChange={(e) =>
                                        search.setData('kind', e.target.value)
                                    }
                                >
                                    <MenuItem value="">All types</MenuItem>
                                    <MenuItem value="payroll">Payroll</MenuItem>
                                    <MenuItem value="training_allowance">
                                        Training allowance
                                    </MenuItem>
                                </TextField>
                                <Button
                                    type="submit"
                                    variant="outlined"
                                    startIcon={<Search size={18} />}
                                >
                                    Filter
                                </Button>
                            </form>
                            <div className="max-h-[60vh] overflow-auto">
                                <table className="w-full min-w-[800px] text-left text-sm">
                                    <thead className="sticky top-0 bg-red-50 text-red-800">
                                        <tr>
                                            {[
                                                'Entry / employee',
                                                'Type / period',
                                                'Gross',
                                                'Deductions',
                                                'Net amount',
                                                'Status',
                                                '',
                                            ].map((label, i) => (
                                                <th key={i} className="p-3">
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {entries.data.map((entry) => (
                                            <tr
                                                key={entry.id}
                                                className="border-b border-slate-100"
                                            >
                                                <td className="p-3">
                                                    <p className="font-bold">
                                                        ACC-
                                                        {String(
                                                            entry.id,
                                                        ).padStart(6, '0')}{' '}
                                                        · {entry.employee.name}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {
                                                            entry.employee
                                                                .username
                                                        }{' '}
                                                        · {entry.description}
                                                    </p>
                                                </td>
                                                <td className="p-3">
                                                    <p className="capitalize">
                                                        {entry.kind.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {date(
                                                            entry.period_start,
                                                        )}{' '}
                                                        –{' '}
                                                        {date(entry.period_end)}
                                                    </p>
                                                </td>
                                                <td className="p-3">
                                                    {money(entry.gross_cents)}
                                                </td>
                                                <td className="p-3">
                                                    {money(
                                                        entry.deduction_cents,
                                                    )}
                                                </td>
                                                <td className="p-3 font-bold">
                                                    {money(entry.net_cents)}
                                                </td>
                                                <td className="p-3">
                                                    <Chip
                                                        size="small"
                                                        label={entry.status}
                                                        color={
                                                            entry.status ===
                                                            'paid'
                                                                ? 'success'
                                                                : entry.status ===
                                                                    'approved'
                                                                  ? 'info'
                                                                  : 'default'
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    <Button
                                                        onClick={() =>
                                                            show(entry.id)
                                                        }
                                                        disabled={loading}
                                                    >
                                                        Review
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!entries.data.length && (
                                    <p className="p-10 text-center text-slate-500">
                                        No accounting entries match this view.
                                        Add a payroll entry or prepare an
                                        eligible training allowance.
                                    </p>
                                )}
                            </div>
                            <div className="mt-4 flex justify-end gap-4 text-sm">
                                <span>
                                    Page {entries.current_page} of{' '}
                                    {entries.last_page}
                                </span>
                                {entries.prev_page_url && (
                                    <Link
                                        preserveScroll
                                        preserveState
                                        href={entries.prev_page_url}
                                    >
                                        Previous
                                    </Link>
                                )}
                                {entries.next_page_url && (
                                    <Link
                                        preserveScroll
                                        preserveState
                                        href={entries.next_page_url}
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="grid gap-5">
                            <Alert severity="info">
                                Allowances come from completed training
                                attendance. The first payout covers the
                                configured number of attended days. Later
                                payouts include eligible days that have not
                                already been reserved or paid.
                            </Alert>
                            <div className="grid gap-4 md:grid-cols-[1fr_220px_auto]">
                                <TextField
                                    select
                                    label="Trainee and training plan"
                                    value={enrollment}
                                    onChange={(e) => {
                                        setEnrollment(e.target.value);
                                        setPreview(null);
                                    }}
                                >
                                    {enrollments.map((row) => (
                                        <MenuItem
                                            key={`${row.plan_id}:${row.user_id}`}
                                            value={`${row.plan_id}:${row.user_id}`}
                                        >
                                            {row.name} · {row.username} ·{' '}
                                            {row.plan_name}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    type="date"
                                    label="Attendance cutoff"
                                    value={cutoff}
                                    onChange={(e) => {
                                        setCutoff(e.target.value);
                                        setPreview(null);
                                    }}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <Button
                                    variant="outlined"
                                    disabled={!enrollment || !cutoff || loading}
                                    onClick={loadAllowance}
                                >
                                    {loading ? 'Loading…' : 'Review allowance'}
                                </Button>
                            </div>
                            {!enrollments.length && (
                                <p className="text-slate-500">
                                    Admins or QA admins must enroll trainees in
                                    training plans first.
                                </p>
                            )}
                            {preview && (
                                <div className="space-y-5 rounded-2xl border border-red-100 bg-red-50/30 p-5">
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        {[
                                            ['Earned', preview.earned_cents],
                                            [
                                                'Already paid',
                                                preview.paid_cents,
                                            ],
                                            [
                                                'Reserved in drafts / approvals',
                                                preview.reserved_cents,
                                            ],
                                        ].map(([label, value]) => (
                                            <div key={label}>
                                                <p className="text-sm text-slate-500">
                                                    {label}
                                                </p>
                                                <p className="text-xl font-bold">
                                                    {money(Number(value))}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                    {!preview.can_prepare && (
                                        <Alert severity="info">
                                            {preview.pending_first
                                                ? 'Review and record payment for the pending first allowance before preparing another payout.'
                                                : 'No new allowance is ready for this cutoff. Check attendance, the first payout threshold, or existing entries.'}
                                        </Alert>
                                    )}
                                    <div className="max-h-80 overflow-auto">
                                        <table className="w-full text-left text-sm">
                                            <thead>
                                                <tr>
                                                    {[
                                                        'Eligible day',
                                                        'Phase',
                                                        'Amount',
                                                    ].map((label) => (
                                                        <th
                                                            key={label}
                                                            className="p-2"
                                                        >
                                                            {label}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {preview.claim_rows.map(
                                                    (row) => (
                                                        <tr
                                                            key={row.date}
                                                            className="border-t border-red-100"
                                                        >
                                                            <td className="p-2">
                                                                {row.date}
                                                            </td>
                                                            <td className="p-2">
                                                                {row.phase}
                                                            </td>
                                                            <td className="p-2">
                                                                {money(
                                                                    row.amount_cents,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-4">
                                        <p className="font-bold">
                                            {preview.first_release
                                                ? 'First allowance'
                                                : 'Next allowance'}
                                            : {money(preview.claim_cents)}
                                        </p>
                                        <Button
                                            variant="contained"
                                            disabled={
                                                !preview.can_prepare ||
                                                preparing
                                            }
                                            onClick={prepareAllowance}
                                        >
                                            Prepare allowance draft
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </section>
            <Dialog
                open={payrollOpen}
                onClose={() => !payroll.processing && setPayrollOpen(false)}
                fullWidth
                maxWidth="md"
            >
                {title('New payroll entry', () => setPayrollOpen(false))}
                <DialogContent dividers>
                    <div className="grid gap-5 py-2">
                        <Alert severity="info">
                            Salary follows the payslip workbook formulas. Review
                            the hours, rates and month-end deductions before
                            saving a draft.
                        </Alert>
                        {Object.values(payroll.errors).map((message, i) => (
                            <Alert key={i} severity="error">
                                {message}
                            </Alert>
                        ))}
                        <TextField
                            select
                            fullWidth
                            label="Employee"
                            value={payroll.data.user_id}
                            onChange={(e) =>
                                payroll.setData('user_id', e.target.value)
                            }
                        >
                            {employees.map((employee) => (
                                <MenuItem
                                    key={employee.id}
                                    value={String(employee.id)}
                                >
                                    {employee.name} · {employee.username} (
                                    {employee.status})
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            fullWidth
                            label="Description"
                            value={payroll.data.description}
                            onChange={(e) =>
                                payroll.setData('description', e.target.value)
                            }
                        />
                        <PayrollCalculation
                            key={[
                                payroll.data.user_id,
                                payroll.data.pay_month,
                                payroll.data.payout,
                                payroll.data.regular_holidays,
                                payroll.data.special_holidays,
                            ].join('|')}
                            data={payroll.data}
                            onReady={setAttendanceReady}
                            onChange={(next) =>
                                payroll.setData((current) => ({
                                    ...current,
                                    ...next,
                                }))
                            }
                        />
                        <TextField
                            multiline
                            minRows={3}
                            label="Payroll notes / deduction explanation"
                            value={payroll.data.notes}
                            onChange={(e) =>
                                payroll.setData('notes', e.target.value)
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={payroll.processing || !attendanceReady}
                        onClick={() =>
                            payroll.post('/accounting/payroll', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setPayrollOpen(false);
                                    setTab(0);
                                },
                            })
                        }
                    >
                        Save draft
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!detail}
                onClose={() => !update.processing && setDetail(null)}
                fullWidth
                maxWidth="md"
            >
                {title(
                    detail
                        ? `ACC-${String(detail.id).padStart(6, '0')} · ${detail.description}`
                        : 'Entry',
                    () => setDetail(null),
                )}
                <DialogContent dividers>
                    {detail && (
                        <div className="grid gap-5">
                            <div>
                                <h3 className="text-xl font-bold">
                                    {detail.source_snapshot?.employee_name ||
                                        detail.employee.name}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    {detail.source_snapshot?.employee_id ||
                                        detail.employee.username}{' '}
                                    · {date(detail.period_start)} –{' '}
                                    {date(detail.period_end)}
                                </p>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                {[
                                    ['Gross', detail.gross_cents],
                                    ['Deductions', detail.deduction_cents],
                                    ['Net pay', detail.net_cents],
                                ].map(([label, amount]) => (
                                    <div
                                        key={label}
                                        className="rounded-xl bg-slate-50 p-3"
                                    >
                                        <p className="text-xs text-slate-500">
                                            {label}
                                        </p>
                                        <p className="font-bold">
                                            {money(Number(amount))}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <p className="text-sm text-slate-500">
                                Created by {detail.creator?.name}
                                {detail.approver &&
                                    ` · Approved by ${detail.approver.name}`}
                            </p>
                            {detail.notes && (
                                <p className="text-sm whitespace-pre-wrap">
                                    {detail.notes}
                                </p>
                            )}
                            {detail.source_snapshot?.payroll && (
                                <PayrollBreakdown
                                    data={detail.source_snapshot.payroll}
                                />
                            )}
                            {detail.source_snapshot?.rows && (
                                <div className="max-h-64 overflow-auto rounded-xl border border-slate-200">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr>
                                                <th className="p-3">
                                                    Training date
                                                </th>
                                                <th className="p-3">Phase</th>
                                                <th className="p-3">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detail.source_snapshot.rows.map(
                                                (row) => (
                                                    <tr
                                                        key={row.date}
                                                        className="border-t border-slate-100"
                                                    >
                                                        <td className="p-3">
                                                            {row.date}
                                                        </td>
                                                        <td className="p-3">
                                                            {row.phase}
                                                        </td>
                                                        <td className="p-3">
                                                            {money(
                                                                row.amount_cents,
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {detail.status === 'paid' && (
                                <Alert severity="success">
                                    Payment recorded on {date(detail.paid_at)}{' '}
                                    by {detail.payer?.name}. Reference:{' '}
                                    {detail.payment_reference}. Recorded
                                    payments are read-only.
                                </Alert>
                            )}
                            {detail.status === 'void' && (
                                <Alert severity="warning">
                                    Voided: {detail.void_reason}
                                </Alert>
                            )}
                            {Object.values(update.errors).map((message, i) => (
                                <Alert key={i} severity="error">
                                    {message}
                                </Alert>
                            ))}
                            {Object.values(emailPayslip.errors).map(
                                (message, i) => (
                                    <Alert key={`email-${i}`} severity="error">
                                        {String(message)}
                                    </Alert>
                                ),
                            )}
                            {action === 'approve' && (
                                <Alert severity="info">
                                    Confirm that you have reviewed the
                                    recipient, period and amounts before
                                    approving this entry.
                                </Alert>
                            )}
                            {action === 'pay' && (
                                <>
                                    <Alert severity="info">
                                        Use the date and reference of the
                                        payment already made.
                                    </Alert>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <TextField
                                            type="date"
                                            label="Payment date"
                                            value={update.data.paid_at}
                                            onChange={(e) =>
                                                update.setData(
                                                    'paid_at',
                                                    e.target.value,
                                                )
                                            }
                                            slotProps={{
                                                inputLabel: { shrink: true },
                                            }}
                                        />
                                        <TextField
                                            select
                                            label="Payment method"
                                            value={update.data.payment_method}
                                            onChange={(e) =>
                                                update.setData(
                                                    'payment_method',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {[
                                                'bank_transfer',
                                                'cash',
                                                'check',
                                                'e_wallet',
                                            ].map((value) => (
                                                <MenuItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {value.replaceAll('_', ' ')}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                    </div>
                                    <TextField
                                        label="Payment / voucher reference"
                                        value={update.data.payment_reference}
                                        onChange={(e) =>
                                            update.setData(
                                                'payment_reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </>
                            )}
                            {action === 'void' && (
                                <TextField
                                    multiline
                                    minRows={3}
                                    label="Reason for voiding this unpaid entry"
                                    value={update.data.void_reason}
                                    onChange={(e) =>
                                        update.setData(
                                            'void_reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            )}
                        </div>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3, gap: 1, flexWrap: 'wrap' }}>
                    {detail && !action && (
                        <>
                            {detail.kind === 'payroll' &&
                                detail.status === 'paid' && (
                                    <Button
                                        variant="contained"
                                        startIcon={<Mail size={18} />}
                                        disabled={emailPayslip.processing}
                                        onClick={() =>
                                            emailPayslip.post(
                                                `/accounting/entries/${detail.id}/email-payslip`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {emailPayslip.processing
                                            ? 'Sending PDF…'
                                            : 'Email PDF payslip'}
                                    </Button>
                                )}
                            {detail.status === 'draft' && (
                                <Button
                                    variant="contained"
                                    onClick={() => {
                                        setAction('approve');
                                        update.setData('action', 'approve');
                                    }}
                                >
                                    Approve entry
                                </Button>
                            )}
                            {detail.status === 'approved' && (
                                <Button
                                    variant="contained"
                                    onClick={() => {
                                        setAction('pay');
                                        update.setData({
                                            action: 'pay',
                                            paid_at: today(),
                                            payment_method: 'bank_transfer',
                                            payment_reference: '',
                                            void_reason: '',
                                        });
                                    }}
                                >
                                    Record payment
                                </Button>
                            )}
                            {['draft', 'approved'].includes(detail.status) && (
                                <Button
                                    color="error"
                                    onClick={() => {
                                        setAction('void');
                                        update.setData('action', 'void');
                                    }}
                                >
                                    Void entry
                                </Button>
                            )}
                        </>
                    )}
                    {detail && action && (
                        <Button
                            variant="contained"
                            disabled={update.processing}
                            onClick={() =>
                                update.patch(
                                    `/accounting/entries/${detail.id}`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setDetail(null);
                                            setAction('');
                                        },
                                    },
                                )
                            }
                        >
                            {action === 'approve'
                                ? 'Confirm approval'
                                : action === 'pay'
                                  ? 'Save payment record'
                                  : 'Confirm void'}
                        </Button>
                    )}
                </DialogActions>
            </Dialog>
        </main>
    );
}
