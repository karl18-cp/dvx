import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    BriefcaseBusiness,
    CalendarCheck,
    Check,
    ChevronDown,
    CircleDashed,
    FileSignature,
    LogOut,
    MessageSquareText,
    Plus,
    Search,
    ShieldCheck,
    UserRoundX,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Link from '@/components/page-link';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

type Clearance = {
    id: number;
    separation_date: string;
    separation_type: string;
    status: string;
    property_status: string;
    property_items: Array<{
        name: string;
        status: string;
        notes?: string;
    }> | null;
    property_notes: string | null;
    final_attendance_status: string;
    final_attendance_notes: string | null;
    outstanding_status: string;
    outstanding_amount_cents: number;
    outstanding_notes: string | null;
    quitclaim_status: string;
    quitclaim_notes: string | null;
    exit_interview_status: string;
    exit_interview_at: string | null;
    exit_interview_notes: string | null;
    approved_at: string | null;
    approver: string | null;
    approval_notes: string | null;
};

type Row = {
    employee: {
        id: number;
        name: string;
        username: string;
        role: string;
        status: string;
        avatar: string;
    };
    display_status: string;
    clearance: Clearance | null;
    backpay: {
        id: number;
        status: string;
        total_cents: number;
        separation_date: string;
    } | null;
    quitclaim: {
        id: number;
        title: string;
        status: string;
        original_filename: string;
        effective_date: string;
    } | null;
    last_attendance: {
        id: number;
        date: string;
        status: string;
        total_minutes: number;
    } | null;
    checks: Record<string, boolean>;
    completed: number;
    total: number;
    ready: boolean;
};

type Props = {
    filters: { search: string; status: string };
    rows: Row[];
    canApprove: boolean;
    summary: {
        employees: number;
        in_progress: number;
        ready: number;
        approved: number;
    };
};

const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
}).format(new Date());
const money = (cents: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(cents / 100);
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeZone: 'Asia/Manila',
          }).format(
              new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value),
          )
        : 'Not recorded';
const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('');
const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const steps = [
    ['property', 'Company property', BriefcaseBusiness],
    ['attendance', 'Final attendance', CalendarCheck],
    ['outstanding', 'Loans & deductions', Banknote],
    ['backpay', 'Backpay', Banknote],
    ['quitclaim', 'Quitclaim', FileSignature],
    ['interview', 'Exit interview', MessageSquareText],
    ['account', 'Account deactivation', LogOut],
] as const;

export default function EmployeeClearance({
    filters,
    rows,
    canApprove,
    summary,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [expanded, setExpanded] = useState<number | null>(
        rows[0]?.clearance?.id ?? null,
    );
    const [creating, setCreating] = useState<Row | null>(null);
    const [editing, setEditing] = useState<{ row: Row; step: string } | null>(
        null,
    );
    const [approving, setApproving] = useState<Row | null>(null);
    const createForm = useForm({ user_id: '', separation_date: today });
    const stepForm = useForm({
        status: '',
        notes: '',
        amount: '0.00',
        interview_at: '',
        items: '',
    });
    const approveForm = useForm({ approval_notes: '' });

    const apply = (status = filters.status) =>
        router.get(
            '/offboarding',
            { search, status },
            { preserveState: true, preserveScroll: true },
        );
    const openCreate = (row: Row) => {
        createForm.setData({
            user_id: String(row.employee.id),
            separation_date: today,
        });
        setCreating(row);
    };
    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post('/offboarding', {
            preserveScroll: true,
            onSuccess: () => setCreating(null),
        });
    };
    const openStep = (row: Row, step: string) => {
        const record = row.clearance!;
        const values =
            step === 'property'
                ? {
                      status: record.property_status,
                      notes: record.property_notes ?? '',
                      amount: '0.00',
                      interview_at: '',
                      items: (record.property_items ?? [])
                          .map(
                              (item) =>
                                  `${item.name} | ${item.status} | ${item.notes ?? ''}`,
                          )
                          .join('\n'),
                  }
                : step === 'attendance'
                  ? {
                        status: record.final_attendance_status,
                        notes: record.final_attendance_notes ?? '',
                        amount: '0.00',
                        interview_at: '',
                        items: '',
                    }
                  : step === 'outstanding'
                    ? {
                          status: record.outstanding_status,
                          notes: record.outstanding_notes ?? '',
                          amount: (
                              record.outstanding_amount_cents / 100
                          ).toFixed(2),
                          interview_at: '',
                          items: '',
                      }
                    : step === 'quitclaim'
                      ? {
                            status: record.quitclaim_status,
                            notes: record.quitclaim_notes ?? '',
                            amount: '0.00',
                            interview_at: '',
                            items: '',
                        }
                      : {
                            status: record.exit_interview_status,
                            notes: record.exit_interview_notes ?? '',
                            amount: '0.00',
                            interview_at:
                                record.exit_interview_at?.slice(0, 16) ?? '',
                            items: '',
                        };
        stepForm.setData(values);
        setEditing({ row, step });
    };
    const submitStep = (event: FormEvent) => {
        event.preventDefault();
        if (!editing?.row.clearance) return;
        const payload: Record<string, unknown> = {
            status: stepForm.data.status,
            notes: stepForm.data.notes,
        };
        if (editing.step === 'outstanding')
            payload.amount = stepForm.data.amount;
        if (editing.step === 'interview')
            payload.interview_at = stepForm.data.interview_at || null;
        if (editing.step === 'property') {
            payload.items = stepForm.data.items
                .split('\n')
                .filter((line) => line.trim())
                .map((line) => {
                    const [name, status = 'outstanding', notes = ''] = line
                        .split('|')
                        .map((part) => part.trim());
                    return { name, status, notes };
                });
        }
        stepForm.transform(() => payload);
        stepForm.patch(
            `/offboarding/${editing.row.clearance.id}/steps/${editing.step}`,
            {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
            },
        );
    };
    const submitApproval = (event: FormEvent) => {
        event.preventDefault();
        if (!approving?.clearance) return;
        approveForm.post(`/offboarding/${approving.clearance.id}/approve`, {
            preserveScroll: true,
            onSuccess: () => setApproving(null),
        });
    };

    return (
        <>
            <Head title="Employee Clearance" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1550px] space-y-6">
                    <header className="relative overflow-hidden rounded-3xl bg-[linear-gradient(125deg,#181321_0%,#431923_56%,#9f1d24_100%)] px-6 py-7 text-white shadow-lg sm:px-8">
                        <div className="absolute -top-24 -right-14 size-72 rounded-full border-[40px] border-white/[0.06]" />
                        <div className="relative flex flex-wrap items-end justify-between gap-5">
                            <div>
                                <p className="text-xs font-bold tracking-[.22em] text-red-200 uppercase">
                                    People operations
                                </p>
                                <h1 className="mt-3 text-2xl font-bold sm:text-3xl">
                                    Employee Clearance & Offboarding
                                </h1>
                                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-200">
                                    Complete every property, attendance,
                                    financial, document, interview, and
                                    access-control step before final clearance
                                    approval.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur">
                                <span className="font-bold">
                                    {summary.employees}
                                </span>{' '}
                                separated employee(s)
                            </div>
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            [
                                'Eligible employees',
                                summary.employees,
                                UserRoundX,
                                'bg-slate-100 text-slate-700',
                            ],
                            [
                                'In progress',
                                summary.in_progress,
                                CircleDashed,
                                'bg-amber-50 text-amber-700',
                            ],
                            [
                                'Ready for approval',
                                summary.ready,
                                ShieldCheck,
                                'bg-sky-50 text-sky-700',
                            ],
                            [
                                'Approved',
                                summary.approved,
                                BadgeCheck,
                                'bg-emerald-50 text-emerald-700',
                            ],
                        ].map((card) => {
                            const title = String(card[0]);
                            const value = Number(card[1]);
                            const Icon = card[2] as typeof UserRoundX;
                            const tone = String(card[3]);
                            return (
                                <article
                                    key={title}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div
                                        className={`flex size-10 items-center justify-center rounded-xl ${tone}`}
                                    >
                                        <Icon className="size-5" />
                                    </div>
                                    <p className="mt-4 text-sm font-medium text-slate-500">
                                        {title}
                                    </p>
                                    <p className="mt-1 text-3xl font-bold text-slate-950">
                                        {value}
                                    </p>
                                </article>
                            );
                        })}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                apply();
                            }}
                            className="flex flex-col gap-3 sm:flex-row"
                        >
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-300 pr-4 pl-10 text-sm outline-none focus:border-red-500"
                                    placeholder="Search employee or ID"
                                />
                            </div>
                            <select
                                value={filters.status}
                                onChange={(event) => apply(event.target.value)}
                                className="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm outline-none focus:border-red-500"
                            >
                                <option value="all">
                                    All clearance statuses
                                </option>
                                <option value="not_started">Not started</option>
                                <option value="in_progress">In progress</option>
                                <option value="ready">Ready</option>
                                <option value="approved">Approved</option>
                            </select>
                            <button className="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white hover:bg-red-800">
                                Search
                            </button>
                        </form>
                    </section>

                    <section className="space-y-4">
                        {rows.map((row) => {
                            const record = row.clearance;
                            const isOpen = record && expanded === record.id;
                            return (
                                <article
                                    key={row.employee.id}
                                    className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                                >
                                    <div className="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <Avatar className="size-12 border border-slate-200">
                                                <AvatarImage
                                                    src={row.employee.avatar}
                                                />
                                                <AvatarFallback className="bg-red-50 font-bold text-red-700">
                                                    {initials(
                                                        row.employee.name,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0">
                                                <p className="truncate font-bold text-slate-950">
                                                    {row.employee.name}
                                                </p>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    {row.employee.username} ·{' '}
                                                    {label(row.employee.role)} ·{' '}
                                                    <span className="font-bold text-red-700">
                                                        {label(
                                                            row.employee.status,
                                                        )}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                        {record ? (
                                            <div className="flex flex-1 items-center gap-4 lg:max-w-xl">
                                                <div className="flex-1">
                                                    <div className="flex justify-between text-xs font-semibold text-slate-500">
                                                        <span>
                                                            {row.completed} of{' '}
                                                            {row.total} steps
                                                            complete
                                                        </span>
                                                        <span>
                                                            {Math.round(
                                                                (row.completed /
                                                                    row.total) *
                                                                    100,
                                                            )}
                                                            %
                                                        </span>
                                                    </div>
                                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                                        <div
                                                            className={`h-full rounded-full ${record.status === 'approved' ? 'bg-emerald-500' : 'bg-red-600'}`}
                                                            style={{
                                                                width: `${(row.completed / row.total) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                                <span
                                                    className={`rounded-full px-3 py-1 text-xs font-bold ${record.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : record.status === 'ready' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-800'}`}
                                                >
                                                    {label(record.status)}
                                                </span>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-slate-500">
                                                No clearance case opened
                                            </p>
                                        )}
                                        <div className="flex gap-2">
                                            {!record ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openCreate(row)
                                                    }
                                                    className="inline-flex h-10 items-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-bold text-white"
                                                >
                                                    <Plus className="size-4" />{' '}
                                                    Open case
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setExpanded(
                                                            isOpen
                                                                ? null
                                                                : record.id,
                                                        )
                                                    }
                                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700"
                                                >
                                                    Details{' '}
                                                    <ChevronDown
                                                        className={`size-4 transition ${isOpen ? 'rotate-180' : ''}`}
                                                    />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    {record && isOpen && (
                                        <div className="border-t border-slate-100 bg-slate-50/60 p-5">
                                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                                <p className="text-sm text-slate-600">
                                                    Separation date{' '}
                                                    <strong>
                                                        {date(
                                                            record.separation_date,
                                                        )}
                                                    </strong>{' '}
                                                    ·{' '}
                                                    {label(
                                                        record.separation_type,
                                                    )}
                                                </p>
                                                {record.status ===
                                                    'approved' && (
                                                    <p className="text-sm font-semibold text-emerald-700">
                                                        Approved by{' '}
                                                        {record.approver} on{' '}
                                                        {date(
                                                            record.approved_at,
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                                {steps.map(
                                                    ([key, title, Icon]) => {
                                                        const complete =
                                                            row.checks[key];
                                                        const editable =
                                                            ![
                                                                'backpay',
                                                                'account',
                                                            ].includes(key) &&
                                                            record.status !==
                                                                'approved';
                                                        const detail =
                                                            key === 'property'
                                                                ? label(
                                                                      record.property_status,
                                                                  )
                                                                : key ===
                                                                    'attendance'
                                                                  ? `${label(record.final_attendance_status)}${row.last_attendance ? ` · ${date(row.last_attendance.date)}` : ''}`
                                                                  : key ===
                                                                      'outstanding'
                                                                    ? `${label(record.outstanding_status)} · ${money(record.outstanding_amount_cents)}`
                                                                    : key ===
                                                                        'backpay'
                                                                      ? row.backpay
                                                                          ? `${label(row.backpay.status)} · ${money(row.backpay.total_cents)}`
                                                                          : 'Backpay not prepared'
                                                                      : key ===
                                                                          'quitclaim'
                                                                        ? row.quitclaim
                                                                            ? `${label(record.quitclaim_status)} · ${row.quitclaim.original_filename}`
                                                                            : label(
                                                                                  record.quitclaim_status,
                                                                              )
                                                                        : key ===
                                                                            'interview'
                                                                          ? label(
                                                                                record.exit_interview_status,
                                                                            )
                                                                          : 'Access blocked by employment status';
                                                        return (
                                                            <div
                                                                key={key}
                                                                className={`rounded-2xl border p-4 ${complete ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-white'}`}
                                                            >
                                                                <div className="flex items-start justify-between gap-3">
                                                                    <div
                                                                        className={`flex size-9 items-center justify-center rounded-xl ${complete ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                                                    >
                                                                        <Icon className="size-4" />
                                                                    </div>
                                                                    {complete ? (
                                                                        <Check className="size-5 text-emerald-600" />
                                                                    ) : (
                                                                        <CircleDashed className="size-5 text-amber-500" />
                                                                    )}
                                                                </div>
                                                                <p className="mt-3 font-bold text-slate-900">
                                                                    {title}
                                                                </p>
                                                                <p className="mt-1 min-h-10 text-xs leading-5 text-slate-500">
                                                                    {detail}
                                                                </p>
                                                                {editable && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            openStep(
                                                                                row,
                                                                                key,
                                                                            )
                                                                        }
                                                                        className="mt-3 text-sm font-bold text-red-700 hover:text-red-800"
                                                                    >
                                                                        Update
                                                                        step
                                                                    </button>
                                                                )}
                                                                {key ===
                                                                    'backpay' && (
                                                                    <Link
                                                                        href="/accounting/workspace/backpay"
                                                                        className="mt-3 block text-sm font-bold text-red-700"
                                                                    >
                                                                        Open
                                                                        backpay
                                                                        register
                                                                    </Link>
                                                                )}
                                                                {key ===
                                                                    'quitclaim' && (
                                                                    <Link
                                                                        href="/accounting/workspace/contracts"
                                                                        className="mt-3 block text-sm font-bold text-red-700"
                                                                    >
                                                                        Open
                                                                        contracts
                                                                    </Link>
                                                                )}
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                            {record.status !== 'approved' && (
                                                <div className="mt-5 flex justify-end">
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            !row.ready ||
                                                            !canApprove
                                                        }
                                                        onClick={() => {
                                                            approveForm.reset();
                                                            setApproving(row);
                                                        }}
                                                        className="inline-flex h-11 items-center gap-2 rounded-xl bg-red-700 px-5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                                                    >
                                                        <ShieldCheck className="size-4" />{' '}
                                                        Final clearance approval
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                        {!rows.length && (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center text-slate-500">
                                No resigned or terminated employees match these
                                filters.
                            </div>
                        )}
                    </section>
                </div>
            </main>

            {creating && (
                <Modal
                    title="Open clearance case"
                    subtitle={creating.employee.name}
                    onClose={() => setCreating(null)}
                >
                    <form onSubmit={submitCreate} className="space-y-4">
                        <Field label="Separation date">
                            <input
                                type="date"
                                max={today}
                                required
                                value={createForm.data.separation_date}
                                onChange={(e) =>
                                    createForm.setData(
                                        'separation_date',
                                        e.target.value,
                                    )
                                }
                                className="input"
                            />
                        </Field>
                        <Errors errors={createForm.errors} />
                        <Actions
                            onCancel={() => setCreating(null)}
                            processing={createForm.processing}
                            label="Open clearance case"
                        />
                    </form>
                </Modal>
            )}

            {editing && (
                <Modal
                    title={`Update ${label(editing.step)}`}
                    subtitle={editing.row.employee.name}
                    onClose={() => setEditing(null)}
                >
                    <form onSubmit={submitStep} className="space-y-4">
                        <Field label="Status">
                            <select
                                value={stepForm.data.status}
                                onChange={(e) =>
                                    stepForm.setData('status', e.target.value)
                                }
                                className="input"
                            >
                                {(editing.step === 'property'
                                    ? [
                                          'pending',
                                          'partial',
                                          'returned',
                                          'waived',
                                      ]
                                    : editing.step === 'attendance'
                                      ? ['pending', 'verified']
                                      : editing.step === 'outstanding'
                                        ? ['pending', 'cleared', 'waived']
                                        : editing.step === 'quitclaim'
                                          ? ['pending', 'signed', 'waived']
                                          : [
                                                'pending',
                                                'completed',
                                                'declined',
                                                'waived',
                                            ]
                                ).map((status) => (
                                    <option key={status} value={status}>
                                        {label(status)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {editing.step === 'outstanding' && (
                            <Field label="Outstanding amount (PHP)">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={stepForm.data.amount}
                                    onChange={(e) =>
                                        stepForm.setData(
                                            'amount',
                                            e.target.value,
                                        )
                                    }
                                    className="input"
                                />
                            </Field>
                        )}
                        {editing.step === 'interview' && (
                            <Field label="Exit interview date and time">
                                <input
                                    type="datetime-local"
                                    value={stepForm.data.interview_at}
                                    onChange={(e) =>
                                        stepForm.setData(
                                            'interview_at',
                                            e.target.value,
                                        )
                                    }
                                    className="input"
                                />
                            </Field>
                        )}
                        {editing.step === 'property' && (
                            <Field label="Property items">
                                <textarea
                                    rows={5}
                                    value={stepForm.data.items}
                                    onChange={(e) =>
                                        stepForm.setData(
                                            'items',
                                            e.target.value,
                                        )
                                    }
                                    className="input h-auto py-3"
                                    placeholder={
                                        'One item per line:\nLaptop | returned | Good condition\nHeadset | outstanding | Not returned'
                                    }
                                />
                                <p className="mt-1 text-xs font-normal text-slate-500">
                                    Format: item | returned, outstanding,
                                    damaged, or waived | notes
                                </p>
                            </Field>
                        )}
                        <Field label="Notes">
                            <textarea
                                rows={4}
                                value={stepForm.data.notes}
                                onChange={(e) =>
                                    stepForm.setData('notes', e.target.value)
                                }
                                className="input h-auto py-3"
                                placeholder="Add supporting details for this clearance step."
                            />
                        </Field>
                        <Errors errors={stepForm.errors} />
                        <Actions
                            onCancel={() => setEditing(null)}
                            processing={stepForm.processing}
                            label="Save step"
                        />
                    </form>
                </Modal>
            )}

            {approving && (
                <Modal
                    title="Approve final clearance"
                    subtitle={approving.employee.name}
                    onClose={() => setApproving(null)}
                >
                    <form onSubmit={submitApproval} className="space-y-4">
                        <div className="rounded-2xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-800">
                            All seven clearance checks are complete. Approval
                            locks the case and records your identity and
                            timestamp.
                        </div>
                        <Field label="Final approval notes">
                            <textarea
                                required
                                minLength={5}
                                rows={4}
                                value={approveForm.data.approval_notes}
                                onChange={(e) =>
                                    approveForm.setData(
                                        'approval_notes',
                                        e.target.value,
                                    )
                                }
                                className="input h-auto py-3"
                            />
                        </Field>
                        <Errors errors={approveForm.errors} />
                        <Actions
                            onCancel={() => setApproving(null)}
                            processing={approveForm.processing}
                            label="Approve and lock"
                        />
                    </form>
                </Modal>
            )}
        </>
    );
}

function Modal({
    title,
    subtitle,
    onClose,
    children,
}: {
    title: string;
    subtitle: string;
    onClose: () => void;
    children: React.ReactNode;
}) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            onMouseDown={onClose}
        >
            <div
                className="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 className="text-xl font-bold text-slate-950">
                            {title}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {subtitle}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="max-h-[75vh] overflow-y-auto p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}
function Field({
    label: title,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block space-y-1.5 text-sm font-semibold text-slate-700">
            {title}
            {children}
        </label>
    );
}
function Errors({ errors }: { errors: Record<string, string> }) {
    const message = Object.values(errors)[0];
    return message ? (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {message}
        </p>
    ) : null;
}
function Actions({
    onCancel,
    processing,
    label: title,
}: {
    onCancel: () => void;
    processing: boolean;
    label: string;
}) {
    return (
        <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button
                type="button"
                onClick={onCancel}
                className="h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700"
            >
                Cancel
            </button>
            <button
                disabled={processing}
                className="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white disabled:opacity-50"
            >
                {processing ? 'Saving…' : title}
            </button>
        </div>
    );
}
