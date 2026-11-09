import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock3,
    Download,
    FileCheck2,
    FileText,
    Inbox,
    Mail,
    Plus,
    Search,
    Upload,
    X,
    XCircle,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import Link from '@/components/page-link';

type Item = {
    id: number;
    user_id: number;
    document_type: string;
    other_document_type: string | null;
    purpose: string;
    period_start: string | null;
    period_end: string | null;
    delivery_method: string;
    status: string;
    employee_notes: string | null;
    reviewer_notes: string | null;
    original_filename: string | null;
    file_size: number | null;
    email_error: string | null;
    employee: {
        id: number;
        name: string;
        username: string;
        role: string;
        email: string;
    };
    handler: string | null;
    submitted_at: string;
    completed_at: string | null;
    released_at: string | null;
    email_sent_at: string | null;
    download_available: boolean;
};
type Props = {
    canManage: boolean;
    scope: string;
    currentUserId: number;
    filters: { search: string; type: string; status: string };
    summary: {
        submitted: number;
        in_review: number;
        ready: number;
        released: number;
    };
    requests: {
        data: Item[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
};

const types = [
    ['certificate_of_employment', 'Certificate of employment'],
    ['payslip_copy', 'Payslip copy'],
    ['employment_verification', 'Employment verification'],
    ['other', 'Other HR document'],
];
const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: value.includes('T') ? 'short' : undefined,
              timeZone: 'Asia/Manila',
          }).format(
              new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value),
          )
        : '—';
const bytes = (value: number | null) =>
    !value
        ? ''
        : value >= 1024 * 1024
          ? `${(value / 1024 / 1024).toFixed(1)} MB`
          : `${Math.max(1, Math.round(value / 1024))} KB`;

export default function EmployeeDocumentRequests({
    canManage,
    scope,
    currentUserId,
    filters,
    summary,
    requests,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [processing, setProcessing] = useState<Item | null>(null);
    const create = useForm({
        request_key: crypto.randomUUID(),
        document_type: 'certificate_of_employment',
        other_document_type: '',
        purpose: '',
        period_start: '',
        period_end: '',
        delivery_method: 'portal',
        employee_notes: '',
    });
    const process = useForm({
        status: 'in_review',
        reviewer_notes: '',
        document: null as File | null,
    });
    const visit = (
        next: Partial<{
            scope: string;
            search: string;
            type: string;
            status: string;
        }>,
    ) =>
        router.get(
            '/document-requests',
            {
                scope: next.scope ?? scope,
                search: next.search ?? search,
                type: next.type ?? filters.type,
                status: next.status ?? filters.status,
            },
            { preserveState: true, preserveScroll: true },
        );
    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        create.post('/document-requests', {
            preserveScroll: true,
            onSuccess: () => {
                create.reset();
                create.setData('request_key', crypto.randomUUID());
                setCreateOpen(false);
            },
        });
    };
    const openProcess = (item: Item) => {
        process.setData({
            status: item.status === 'submitted' ? 'in_review' : item.status,
            reviewer_notes: item.reviewer_notes ?? '',
            document: null,
        });
        setProcessing(item);
    };
    const submitProcess = (event: FormEvent) => {
        event.preventDefault();
        if (!processing) return;
        process.post(`/document-requests/${processing.id}/process`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setProcessing(null),
        });
    };

    return (
        <>
            <Head title="Document Requests" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-6">
                    <header className="relative overflow-hidden rounded-3xl bg-[linear-gradient(125deg,#181321_0%,#431923_56%,#9f1d24_100%)] px-6 py-7 text-white shadow-lg sm:px-8">
                        <div className="absolute -top-24 -right-14 size-72 rounded-full border-[40px] border-white/[0.06]" />
                        <div className="relative flex flex-wrap items-end justify-between gap-5">
                            <div>
                                <p className="text-xs font-bold tracking-[.22em] text-red-200 uppercase">
                                    Employee self-service
                                </p>
                                <h1 className="mt-3 text-2xl font-bold sm:text-3xl">
                                    Employee Document Requests
                                </h1>
                                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-200">
                                    Request certificates, payslip copies,
                                    employment verification, and other HR
                                    documents, then track delivery in one secure
                                    workspace.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCreateOpen(true)}
                                className="inline-flex h-11 items-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-red-800 shadow"
                            >
                                <Plus className="size-4" /> New request
                            </button>
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            [
                                'Submitted',
                                summary.submitted,
                                Inbox,
                                'bg-amber-50 text-amber-700',
                            ],
                            [
                                'In review',
                                summary.in_review,
                                Clock3,
                                'bg-sky-50 text-sky-700',
                            ],
                            [
                                'Ready',
                                summary.ready,
                                FileCheck2,
                                'bg-indigo-50 text-indigo-700',
                            ],
                            [
                                'Released',
                                summary.released,
                                CheckCircle2,
                                'bg-emerald-50 text-emerald-700',
                            ],
                        ].map((card) => {
                            const Icon = card[2] as typeof Inbox;
                            return (
                                <article
                                    key={String(card[0])}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div
                                        className={`flex size-10 items-center justify-center rounded-xl ${String(card[3])}`}
                                    >
                                        <Icon className="size-5" />
                                    </div>
                                    <p className="mt-4 text-sm font-medium text-slate-500">
                                        {String(card[0])}
                                    </p>
                                    <p className="mt-1 text-3xl font-bold text-slate-950">
                                        {Number(card[1])}
                                    </p>
                                </article>
                            );
                        })}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                visit({ search });
                            }}
                            className="grid gap-3 lg:grid-cols-[1fr_auto_auto_auto_auto]"
                        >
                            {canManage && (
                                <select
                                    value={scope}
                                    onChange={(e) =>
                                        visit({ scope: e.target.value })
                                    }
                                    className="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm"
                                >
                                    <option value="all">
                                        All employee requests
                                    </option>
                                    <option value="mine">My requests</option>
                                </select>
                            )}
                            <div className="relative min-w-60">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="h-11 w-full rounded-xl border border-slate-300 pr-4 pl-10 text-sm outline-none focus:border-red-500"
                                    placeholder="Search requests"
                                />
                            </div>
                            <select
                                value={filters.type}
                                onChange={(e) =>
                                    visit({ type: e.target.value })
                                }
                                className="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm"
                            >
                                <option value="">All document types</option>
                                {types.map(([value, title]) => (
                                    <option key={value} value={value}>
                                        {title}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={filters.status}
                                onChange={(e) =>
                                    visit({ status: e.target.value })
                                }
                                className="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm"
                            >
                                <option value="">All statuses</option>
                                {[
                                    'submitted',
                                    'in_review',
                                    'ready',
                                    'released',
                                    'rejected',
                                    'cancelled',
                                ].map((status) => (
                                    <option key={status} value={status}>
                                        {label(status)}
                                    </option>
                                ))}
                            </select>
                            <button className="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white">
                                Search
                            </button>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="divide-y divide-slate-100">
                            {requests.data.map((item) => (
                                <article key={item.id} className="p-5 sm:p-6">
                                    <div className="grid gap-5 xl:grid-cols-[1.2fr_1fr_1fr_auto] xl:items-center">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="font-bold text-slate-950">
                                                    {item.document_type ===
                                                    'other'
                                                        ? item.other_document_type
                                                        : label(
                                                              item.document_type,
                                                          )}
                                                </h2>
                                                <Status value={item.status} />
                                            </div>
                                            {canManage && scope === 'all' && (
                                                <p className="mt-1 text-sm font-semibold text-slate-700">
                                                    {item.employee.name} ·{' '}
                                                    {item.employee.username}
                                                </p>
                                            )}
                                            <p className="mt-2 text-sm leading-6 text-slate-500">
                                                {item.purpose}
                                            </p>
                                            {item.employee_notes && (
                                                <p className="mt-1 text-xs text-slate-400">
                                                    Employee note:{' '}
                                                    {item.employee_notes}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="eyebrow">
                                                Request details
                                            </p>
                                            <p className="mt-1 text-sm text-slate-700">
                                                Submitted{' '}
                                                {date(item.submitted_at)}
                                            </p>
                                            {item.period_start && (
                                                <p className="mt-1 text-xs text-slate-500">
                                                    Period:{' '}
                                                    {date(item.period_start)} –{' '}
                                                    {date(item.period_end)}
                                                </p>
                                            )}
                                            <p className="mt-1 text-xs text-slate-500">
                                                Delivery:{' '}
                                                {label(item.delivery_method)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="eyebrow">
                                                Processing
                                            </p>
                                            <p className="mt-1 text-sm text-slate-700">
                                                {item.handler
                                                    ? `Handled by ${item.handler}`
                                                    : 'Awaiting reviewer'}
                                            </p>
                                            {item.reviewer_notes && (
                                                <p className="mt-1 text-xs leading-5 text-slate-500">
                                                    {item.reviewer_notes}
                                                </p>
                                            )}
                                            {item.original_filename && (
                                                <p className="mt-1 text-xs font-medium text-slate-600">
                                                    {item.original_filename} ·{' '}
                                                    {bytes(item.file_size)}
                                                </p>
                                            )}
                                            {item.email_error && (
                                                <p className="mt-1 text-xs font-semibold text-red-700">
                                                    Email delivery needs retry
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-2 xl:justify-end">
                                            {item.download_available && (
                                                <a
                                                    href={`/document-requests/${item.id}/download`}
                                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-emerald-300 px-4 text-sm font-bold text-emerald-700"
                                                >
                                                    <Download className="size-4" />{' '}
                                                    Download
                                                </a>
                                            )}
                                            {canManage && item.email_error && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `/document-requests/${item.id}/email`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-sky-300 px-4 text-sm font-bold text-sky-700"
                                                >
                                                    <Mail className="size-4" />{' '}
                                                    Retry email
                                                </button>
                                            )}
                                            {canManage &&
                                                ![
                                                    'released',
                                                    'cancelled',
                                                ].includes(item.status) && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openProcess(item)
                                                        }
                                                        className="inline-flex h-10 items-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-bold text-white"
                                                    >
                                                        <Upload className="size-4" />{' '}
                                                        Process
                                                    </button>
                                                )}
                                            {item.user_id === currentUserId &&
                                                item.status === 'submitted' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/document-requests/${item.id}/cancel`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700"
                                                    >
                                                        <XCircle className="size-4" />{' '}
                                                        Cancel
                                                    </button>
                                                )}
                                        </div>
                                    </div>
                                </article>
                            ))}
                            {!requests.data.length && (
                                <div className="flex min-h-64 flex-col items-center justify-center p-8 text-center">
                                    <FileText className="size-11 text-slate-300" />
                                    <p className="mt-3 font-bold text-slate-700">
                                        No document requests found
                                    </p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Start a new request or adjust the
                                        filters.
                                    </p>
                                </div>
                            )}
                        </div>
                        {requests.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-sm text-slate-500">
                                <span>
                                    Page {requests.current_page} of{' '}
                                    {requests.last_page}
                                </span>
                                <div className="flex gap-2">
                                    {requests.prev_page_url && (
                                        <Link
                                            href={requests.prev_page_url}
                                            className="rounded-lg border px-3 py-2 font-bold"
                                        >
                                            Previous
                                        </Link>
                                    )}
                                    {requests.next_page_url && (
                                        <Link
                                            href={requests.next_page_url}
                                            className="rounded-lg border px-3 py-2 font-bold"
                                        >
                                            Next
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </main>

            {createOpen && (
                <Modal
                    title="Request an HR document"
                    subtitle="Tell HR what you need and how it should be delivered."
                    onClose={() => setCreateOpen(false)}
                >
                    <form onSubmit={submitCreate} className="space-y-4">
                        <Field title="Document type">
                            <select
                                value={create.data.document_type}
                                onChange={(e) =>
                                    create.setData(
                                        'document_type',
                                        e.target.value,
                                    )
                                }
                                className="input"
                            >
                                {types.map(([value, title]) => (
                                    <option key={value} value={value}>
                                        {title}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {create.data.document_type === 'other' && (
                            <Field title="Document name">
                                <input
                                    required
                                    value={create.data.other_document_type}
                                    onChange={(e) =>
                                        create.setData(
                                            'other_document_type',
                                            e.target.value,
                                        )
                                    }
                                    className="input"
                                />
                            </Field>
                        )}
                        {create.data.document_type === 'payslip_copy' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field title="Period start">
                                    <input
                                        type="date"
                                        required
                                        value={create.data.period_start}
                                        onChange={(e) =>
                                            create.setData(
                                                'period_start',
                                                e.target.value,
                                            )
                                        }
                                        className="input"
                                    />
                                </Field>
                                <Field title="Period end">
                                    <input
                                        type="date"
                                        required
                                        min={create.data.period_start}
                                        value={create.data.period_end}
                                        onChange={(e) =>
                                            create.setData(
                                                'period_end',
                                                e.target.value,
                                            )
                                        }
                                        className="input"
                                    />
                                </Field>
                            </div>
                        )}
                        <Field title="Purpose">
                            <textarea
                                required
                                minLength={5}
                                rows={3}
                                value={create.data.purpose}
                                onChange={(e) =>
                                    create.setData('purpose', e.target.value)
                                }
                                className="input h-auto py-3"
                                placeholder="Example: Visa application, bank requirement, or personal records"
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field title="Delivery preference">
                                <select
                                    value={create.data.delivery_method}
                                    onChange={(e) =>
                                        create.setData(
                                            'delivery_method',
                                            e.target.value,
                                        )
                                    }
                                    className="input"
                                >
                                    <option value="portal">
                                        Portal download
                                    </option>
                                    <option value="email">
                                        Email attachment
                                    </option>
                                    <option value="both">
                                        Portal and email
                                    </option>
                                </select>
                            </Field>
                            <Field title="Additional notes">
                                <input
                                    value={create.data.employee_notes}
                                    onChange={(e) =>
                                        create.setData(
                                            'employee_notes',
                                            e.target.value,
                                        )
                                    }
                                    className="input"
                                    placeholder="Optional details"
                                />
                            </Field>
                        </div>
                        <Errors errors={create.errors} />
                        <Actions
                            processing={create.processing}
                            label="Submit request"
                            onCancel={() => setCreateOpen(false)}
                        />
                    </form>
                </Modal>
            )}

            {processing && (
                <Modal
                    title="Process document request"
                    subtitle={`${processing.employee.name} · ${label(processing.document_type)}`}
                    onClose={() => setProcessing(null)}
                >
                    <form onSubmit={submitProcess} className="space-y-4">
                        <Field title="Status">
                            <select
                                value={process.data.status}
                                onChange={(e) =>
                                    process.setData('status', e.target.value)
                                }
                                className="input"
                            >
                                <option value="in_review">In review</option>
                                <option value="ready">
                                    Ready for download
                                </option>
                                <option value="released">Released</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </Field>
                        <Field title="Completed document">
                            <label className="flex min-h-24 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                                <Upload className="size-6 text-slate-500" />
                                <span className="mt-2 text-sm font-bold text-slate-700">
                                    {process.data.document?.name ??
                                        processing.original_filename ??
                                        'Choose PDF, DOCX, or image'}
                                </span>
                                <span className="mt-1 text-xs text-slate-500">
                                    Maximum 10 MB
                                </span>
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                    className="sr-only"
                                    onChange={(e) =>
                                        process.setData(
                                            'document',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </label>
                        </Field>
                        <Field title="Reviewer notes">
                            <textarea
                                rows={4}
                                value={process.data.reviewer_notes}
                                onChange={(e) =>
                                    process.setData(
                                        'reviewer_notes',
                                        e.target.value,
                                    )
                                }
                                className="input h-auto py-3"
                                placeholder="Required when rejecting; shown to the employee."
                            />
                        </Field>
                        {['email', 'both'].includes(
                            processing.delivery_method,
                        ) &&
                            process.data.status === 'released' && (
                                <div className="flex gap-3 rounded-2xl bg-sky-50 p-4 text-sm leading-6 text-sky-800">
                                    <Mail className="mt-0.5 size-5 shrink-0" />{' '}
                                    Releasing will email the attached document
                                    to {processing.employee.email}.
                                </div>
                            )}
                        <Errors errors={process.errors} />
                        <Actions
                            processing={process.processing}
                            label="Update request"
                            onCancel={() => setProcessing(null)}
                        />
                    </form>
                </Modal>
            )}
        </>
    );
}

function Status({ value }: { value: string }) {
    const tone =
        value === 'released'
            ? 'bg-emerald-100 text-emerald-700'
            : value === 'ready'
              ? 'bg-indigo-100 text-indigo-700'
              : value === 'rejected' || value === 'cancelled'
                ? 'bg-red-100 text-red-700'
                : value === 'in_review'
                  ? 'bg-sky-100 text-sky-700'
                  : 'bg-amber-100 text-amber-800';
    return (
        <span className={`rounded-full px-3 py-1 text-xs font-bold ${tone}`}>
            {label(value)}
        </span>
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
    children: ReactNode;
}) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            onMouseDown={onClose}
        >
            <div
                className="w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl"
                onMouseDown={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b px-6 py-5">
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
                        className="rounded-full p-2 hover:bg-slate-100"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="max-h-[78vh] overflow-y-auto p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}
function Field({ title, children }: { title: string; children: ReactNode }) {
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
    processing,
    label: title,
    onCancel,
}: {
    processing: boolean;
    label: string;
    onCancel: () => void;
}) {
    return (
        <div className="flex justify-end gap-3 border-t pt-4">
            <button
                type="button"
                onClick={onCancel}
                className="h-11 rounded-xl border px-5 text-sm font-bold text-slate-700"
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
