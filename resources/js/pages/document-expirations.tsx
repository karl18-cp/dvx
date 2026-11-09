import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    BellRing,
    CalendarClock,
    Download,
    FileCheck2,
    FileWarning,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Users,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import type { LucideIcon } from 'lucide-react';

type RecordRow = {
    id: number;
    source_type: 'document' | 'contract';
    subject_type: 'employee' | 'applicant';
    user_id: number | null;
    job_application_id: number | null;
    document_type: string;
    title: string;
    issuer: string | null;
    document_number: string | null;
    issued_at: string | null;
    expires_at: string;
    remind_days_before: number;
    status: string;
    notes: string | null;
    original_filename: string | null;
    subject_name: string;
    subject_detail: string | null;
    days_remaining: number;
    expiry_state: 'current' | 'due_soon' | 'expired' | 'waived';
    download_available: boolean;
};

type Employee = {
    id: number;
    name: string;
    username: string;
    role: string;
    status: string;
};

type Applicant = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    position: string;
    applicant_stage: string | null;
};

type Props = {
    records: RecordRow[];
    employees: Employee[];
    applicants: Applicant[];
    filters: {
        search: string;
        type: string;
        state: string;
        subject_type: string;
    };
    summary: {
        total: number;
        expired: number;
        due_soon: number;
        applicant_requirements: number;
    };
    documentTypes: string[];
    today: string;
};

const pretty = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeZone: 'Asia/Manila',
          }).format(new Date(`${value.slice(0, 10)}T00:00:00+08:00`))
        : 'Not recorded';

const emptyForm = {
    subject_type: 'employee',
    user_id: '',
    job_application_id: '',
    document_type: 'government_id',
    title: '',
    issuer: '',
    document_number: '',
    issued_at: '',
    expires_at: '',
    remind_days_before: '30',
    status: 'active',
    notes: '',
    document: null as File | null,
};

export default function DocumentExpirations({
    records,
    employees,
    applicants,
    filters,
    summary,
    documentTypes,
}: Props) {
    const [modal, setModal] = useState<RecordRow | 'new' | null>(null);
    const [search, setSearch] = useState(filters.search);
    const form = useForm(emptyForm);
    const selectedSubject = useMemo(
        () =>
            form.data.subject_type === 'employee'
                ? employees.find(
                      (employee) => String(employee.id) === form.data.user_id,
                  )
                : applicants.find(
                      (applicant) =>
                          String(applicant.id) === form.data.job_application_id,
                  ),
        [applicants, employees, form.data],
    );
    const summaryCards: Array<{
        label: string;
        value: number;
        icon: LucideIcon;
        color: string;
    }> = [
        {
            label: 'Tracked records',
            value: summary.total,
            icon: FileCheck2,
            color: 'text-slate-700',
        },
        {
            label: 'Expired',
            value: summary.expired,
            icon: AlertTriangle,
            color: 'text-red-700',
        },
        {
            label: 'Due soon',
            value: summary.due_soon,
            icon: CalendarClock,
            color: 'text-amber-700',
        },
        {
            label: 'Applicant requirements',
            value: summary.applicant_requirements,
            icon: Users,
            color: 'text-blue-700',
        },
    ];

    const filter = (next: Partial<Props['filters']>) =>
        router.get(
            '/document-expirations',
            { ...filters, search, ...next },
            { preserveState: true, replace: true },
        );

    const open = (record: RecordRow | 'new') => {
        setModal(record);
        form.clearErrors();
        if (record === 'new') {
            form.setData(emptyForm);
            return;
        }
        form.setData({
            subject_type: record.subject_type,
            user_id: record.user_id ? String(record.user_id) : '',
            job_application_id: record.job_application_id
                ? String(record.job_application_id)
                : '',
            document_type: record.document_type,
            title: record.title,
            issuer: record.issuer ?? '',
            document_number: record.document_number ?? '',
            issued_at: record.issued_at?.slice(0, 10) ?? '',
            expires_at: record.expires_at.slice(0, 10),
            remind_days_before: String(record.remind_days_before),
            status: record.status === 'waived' ? 'waived' : 'active',
            notes: record.notes ?? '',
            document: null,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const url =
            modal === 'new'
                ? '/document-expirations'
                : `/document-expirations/${(modal as RecordRow).id}/update`;
        form.post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setModal(null),
        });
    };

    return (
        <>
            <Head title="Document Expirations" />
            <main className="min-h-full bg-[linear-gradient(135deg,#fff7f7_0%,#f8fafc_55%,#fff_100%)] px-6 py-8 lg:px-10">
                <div className="mx-auto max-w-[1600px] space-y-6">
                    <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                                Compliance & recruitment
                            </p>
                            <h1 className="flex items-center gap-3 text-3xl font-black text-slate-950">
                                <FileWarning className="size-8 text-red-700" />
                                Document Expiration Alerts
                            </h1>
                            <p className="mt-2 max-w-3xl text-slate-600">
                                Monitor employee, trainee, and applicant
                                requirements alongside contract end dates.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        '/document-expirations/scan',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                className="inline-flex h-11 items-center gap-2 rounded-xl border border-red-200 bg-white px-4 text-sm font-bold text-red-800 shadow-sm hover:bg-red-50"
                            >
                                <RefreshCw className="size-4" /> Run alert scan
                            </button>
                            <button
                                type="button"
                                onClick={() => open('new')}
                                className="inline-flex h-11 items-center gap-2 rounded-xl bg-red-700 px-5 text-sm font-bold text-white shadow-lg shadow-red-900/15 hover:bg-red-800"
                            >
                                <Plus className="size-4" /> Add requirement
                            </button>
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {summaryCards.map(
                            ({ label, value, icon: Icon, color }) => (
                                <article
                                    key={label}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-500">
                                                {label}
                                            </p>
                                            <p className="mt-1 text-3xl font-black text-slate-950">
                                                {value}
                                            </p>
                                        </div>
                                        <Icon className={`size-7 ${color}`} />
                                    </div>
                                </article>
                            ),
                        )}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="grid gap-3 lg:grid-cols-[1fr_220px_190px_190px_auto]">
                            <label className="relative">
                                <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        filter({ search })
                                    }
                                    placeholder="Search person, document, issuer, or number"
                                    className="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 pr-4 pl-11 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                                />
                            </label>
                            <FilterSelect
                                value={filters.type}
                                onChange={(value) => filter({ type: value })}
                                options={[
                                    ['', 'All document types'],
                                    ['contract', 'Contracts'],
                                    ...documentTypes.map((type) => [
                                        type,
                                        pretty(type),
                                    ]),
                                ]}
                            />
                            <FilterSelect
                                value={filters.state}
                                onChange={(value) => filter({ state: value })}
                                options={[
                                    ['', 'All expiration states'],
                                    ['expired', 'Expired'],
                                    ['due_soon', 'Due soon'],
                                    ['current', 'Current'],
                                    ['waived', 'Waived'],
                                ]}
                            />
                            <FilterSelect
                                value={filters.subject_type}
                                onChange={(value) =>
                                    filter({ subject_type: value })
                                }
                                options={[
                                    ['', 'Employees & applicants'],
                                    ['employee', 'Employees / trainees'],
                                    ['applicant', 'Applicants'],
                                ]}
                            />
                            <button
                                type="button"
                                onClick={() => filter({ search })}
                                className="h-12 rounded-xl border border-slate-300 px-5 font-bold text-slate-700 hover:bg-slate-50"
                            >
                                Filter
                            </button>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1100px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase">
                                    <tr>
                                        {[
                                            'Person',
                                            'Document',
                                            'Type',
                                            'Expiration',
                                            'Alert',
                                            'State',
                                            'Actions',
                                        ].map((heading) => (
                                            <th
                                                key={heading}
                                                className="px-5 py-4"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {records.map((record) => (
                                        <tr
                                            key={`${record.source_type}-${record.id}`}
                                            className="hover:bg-slate-50/70"
                                        >
                                            <td className="px-5 py-4">
                                                <p className="font-bold text-slate-900">
                                                    {record.subject_name}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {record.subject_detail} ·{' '}
                                                    {pretty(
                                                        record.subject_type,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-semibold text-slate-900">
                                                    {record.title}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {record.issuer ||
                                                        record.document_number ||
                                                        'No issuer recorded'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-slate-700">
                                                {pretty(record.document_type)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-semibold text-slate-900">
                                                    {formatDate(
                                                        record.expires_at,
                                                    )}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {record.days_remaining < 0
                                                        ? `${Math.abs(record.days_remaining)} day(s) overdue`
                                                        : `${record.days_remaining} day(s) left`}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-slate-600">
                                                {record.remind_days_before} days
                                                before
                                            </td>
                                            <td className="px-5 py-4">
                                                <StateBadge
                                                    state={record.expiry_state}
                                                />
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex gap-2">
                                                    {record.download_available && (
                                                        <a
                                                            href={
                                                                record.source_type ===
                                                                'contract'
                                                                    ? `/document-expirations/contracts/${record.id}/download`
                                                                    : `/document-expirations/${record.id}/download`
                                                            }
                                                            className="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100"
                                                            title="Download"
                                                        >
                                                            <Download className="size-4" />
                                                        </a>
                                                    )}
                                                    {record.source_type ===
                                                        'document' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                open(record)
                                                            }
                                                            className="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100"
                                                            title="Edit"
                                                        >
                                                            <Pencil className="size-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {records.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-6 py-16 text-center text-slate-500"
                                            >
                                                <FileCheck2 className="mx-auto mb-3 size-10 text-slate-300" />
                                                No expiration records match
                                                these filters.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>

            {modal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                >
                    <form
                        onSubmit={submit}
                        className="flex max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden rounded-[28px] bg-white shadow-2xl"
                    >
                        <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h2 className="text-xl font-black text-slate-950">
                                    {modal === 'new'
                                        ? 'Add expiring requirement'
                                        : 'Update requirement'}
                                </h2>
                                <p className="text-sm text-slate-500">
                                    Alerts are sent through both the system
                                    notification feed and email delivery
                                    workflow.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModal(null)}
                                className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                                aria-label="Close"
                            >
                                <X className="size-5" />
                            </button>
                        </header>
                        <div className="overflow-y-auto p-6">
                            <div className="grid gap-5 rounded-2xl border border-red-100 bg-red-50/40 p-5 md:grid-cols-2">
                                <Field
                                    label="Record belongs to"
                                    error={form.errors.subject_type}
                                >
                                    <select
                                        value={form.data.subject_type}
                                        onChange={(event) =>
                                            form.setData({
                                                ...form.data,
                                                subject_type:
                                                    event.target.value,
                                                user_id: '',
                                                job_application_id: '',
                                            })
                                        }
                                        className="input"
                                    >
                                        <option value="employee">
                                            Employee or trainee
                                        </option>
                                        <option value="applicant">
                                            Applicant / pre-employment
                                        </option>
                                    </select>
                                </Field>
                                <Field
                                    label={
                                        form.data.subject_type === 'employee'
                                            ? 'Employee / trainee'
                                            : 'Applicant'
                                    }
                                    error={
                                        form.data.subject_type === 'employee'
                                            ? form.errors.user_id
                                            : form.errors.job_application_id
                                    }
                                >
                                    <select
                                        value={
                                            form.data.subject_type ===
                                            'employee'
                                                ? form.data.user_id
                                                : form.data.job_application_id
                                        }
                                        onChange={(event) =>
                                            form.data.subject_type ===
                                            'employee'
                                                ? form.setData(
                                                      'user_id',
                                                      event.target.value,
                                                  )
                                                : form.setData(
                                                      'job_application_id',
                                                      event.target.value,
                                                  )
                                        }
                                        className="input"
                                    >
                                        <option value="">
                                            Select a person
                                        </option>
                                        {form.data.subject_type === 'employee'
                                            ? employees.map((employee) => (
                                                  <option
                                                      key={employee.id}
                                                      value={employee.id}
                                                  >
                                                      {employee.name} ·{' '}
                                                      {employee.username} ·{' '}
                                                      {pretty(employee.role)}
                                                  </option>
                                              ))
                                            : applicants.map((applicant) => (
                                                  <option
                                                      key={applicant.id}
                                                      value={applicant.id}
                                                  >
                                                      {applicant.first_name}{' '}
                                                      {applicant.last_name} ·{' '}
                                                      {applicant.email}
                                                  </option>
                                              ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Document type"
                                    error={form.errors.document_type}
                                >
                                    <select
                                        value={form.data.document_type}
                                        onChange={(event) =>
                                            form.setData(
                                                'document_type',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                    >
                                        {documentTypes.map((type) => (
                                            <option key={type} value={type}>
                                                {pretty(type)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Requirement / document name"
                                    error={form.errors.title}
                                >
                                    <input
                                        value={form.data.title}
                                        onChange={(event) =>
                                            form.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                        placeholder="e.g. NBI Clearance"
                                    />
                                </Field>
                                <Field
                                    label="Issuer"
                                    error={form.errors.issuer}
                                >
                                    <input
                                        value={form.data.issuer}
                                        onChange={(event) =>
                                            form.setData(
                                                'issuer',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                        placeholder="Issuing organization"
                                    />
                                </Field>
                                <Field
                                    label="Document number"
                                    error={form.errors.document_number}
                                >
                                    <input
                                        value={form.data.document_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'document_number',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                        placeholder="Optional reference"
                                    />
                                </Field>
                                <Field
                                    label="Issue date"
                                    error={form.errors.issued_at}
                                >
                                    <input
                                        type="date"
                                        value={form.data.issued_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'issued_at',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                    />
                                </Field>
                                <Field
                                    label="Expiration date"
                                    error={form.errors.expires_at}
                                >
                                    <input
                                        required
                                        type="date"
                                        value={form.data.expires_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'expires_at',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                    />
                                </Field>
                                <Field
                                    label="Alert administrators"
                                    error={form.errors.remind_days_before}
                                >
                                    <select
                                        value={form.data.remind_days_before}
                                        onChange={(event) =>
                                            form.setData(
                                                'remind_days_before',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                    >
                                        {[7, 14, 30, 45, 60, 90, 180].map(
                                            (days) => (
                                                <option key={days} value={days}>
                                                    {days} days before
                                                    expiration
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </Field>
                                <Field
                                    label="Tracking status"
                                    error={form.errors.status}
                                >
                                    <select
                                        value={form.data.status}
                                        onChange={(event) =>
                                            form.setData(
                                                'status',
                                                event.target.value,
                                            )
                                        }
                                        className="input"
                                    >
                                        <option value="active">
                                            Active monitoring
                                        </option>
                                        <option value="waived">
                                            Waived / not required
                                        </option>
                                    </select>
                                </Field>
                                <Field
                                    label={
                                        modal === 'new'
                                            ? 'Attach document (optional)'
                                            : 'Replace attachment (optional)'
                                    }
                                    error={form.errors.document}
                                >
                                    <input
                                        type="file"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                                        onChange={(event) =>
                                            form.setData(
                                                'document',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        className="input file:mr-3 file:rounded-lg file:border-0 file:bg-red-100 file:px-3 file:py-2 file:font-bold file:text-red-800"
                                    />
                                </Field>
                                <Field label="Notes" error={form.errors.notes}>
                                    <textarea
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        className="input min-h-24 py-3"
                                        placeholder="Renewal instructions or recruitment notes"
                                    />
                                </Field>
                            </div>
                            {selectedSubject && (
                                <p className="mt-3 text-xs text-slate-500">
                                    <BellRing className="mr-1 inline size-3" />{' '}
                                    Tracking requirement for{' '}
                                    {'name' in selectedSubject
                                        ? selectedSubject.name
                                        : `${selectedSubject.first_name} ${selectedSubject.last_name}`}
                                    .
                                </p>
                            )}
                        </div>
                        <footer className="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                            <button
                                type="button"
                                onClick={() => setModal(null)}
                                className="h-11 rounded-xl border border-slate-300 px-5 font-bold text-slate-700"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={form.processing}
                                className="h-11 rounded-xl bg-red-700 px-6 font-bold text-white disabled:opacity-50"
                            >
                                {form.processing
                                    ? 'Saving…'
                                    : 'Save requirement'}
                            </button>
                        </footer>
                    </form>
                </div>
            )}
            <style>{`.input{height:3rem;width:100%;border-radius:.85rem;border:1px solid #cbd5e1;background:white;padding:0 .9rem;color:#0f172a;outline:none}.input:focus{border-color:#dc2626;box-shadow:0 0 0 3px #fee2e2}`}</style>
        </>
    );
}

function FilterSelect({
    value,
    onChange,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    options: string[][];
}) {
    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="h-12 rounded-xl border border-slate-300 bg-slate-50 px-4 font-semibold text-slate-700 outline-none focus:border-red-500"
        >
            {options.map(([option, label]) => (
                <option key={option} value={option}>
                    {label}
                </option>
            ))}
        </select>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="space-y-1.5">
            <span className="text-sm font-bold text-slate-700">{label}</span>
            {children}
            {error && (
                <span className="block text-xs font-semibold text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function StateBadge({ state }: { state: RecordRow['expiry_state'] }) {
    const styles = {
        current: 'bg-emerald-100 text-emerald-800',
        due_soon: 'bg-amber-100 text-amber-800',
        expired: 'bg-red-100 text-red-800',
        waived: 'bg-slate-200 text-slate-700',
    };
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-black ${styles[state]}`}
        >
            {pretty(state)}
        </span>
    );
}

DocumentExpirations.layout = {
    breadcrumbs: [
        { title: 'Document Expirations', href: '/document-expirations' },
    ],
};
