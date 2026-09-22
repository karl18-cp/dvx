import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
} from '@mui/material';
import { useState } from 'react';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    position: string;
    location?: string;
    years_experience: number;
    message?: string;
    resume_path?: string;
    resume_original_name?: string;
    applicant_stage: string;
    internal_notes?: string;
    applicant_update?: string;
    created_at: string;
};
type Page = {
    data: Application[];
    current_page: number;
    last_page: number;
    prev_page_url?: string;
    next_page_url?: string;
};
const stages = ['for_screening', 'for_final_interview', 'passed', 'failed'];
export default function Applicants({
    applications,
    filters,
    summary,
}: {
    applications: Page;
    filters: Record<string, string>;
    summary: Record<string, number>;
}) {
    const [selected, setSelected] = useState<Application | null>(null);
    const form = useForm({
        applicant_stage: 'for_screening',
        internal_notes: '',
        applicant_update: '',
    });
    const open = (a: Application) => {
        setSelected(a);
        form.setData({
            applicant_stage: a.applicant_stage,
            internal_notes: a.internal_notes || '',
            applicant_update: a.applicant_update || '',
        });
    };
    const filter = (key: string, value: string) =>
        router.get(
            '/management/applicants',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-slate-900">
            <Head title="Applicants" />
            <div className="mx-auto max-w-7xl space-y-5">
                <header>
                    <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                        Recruitment
                    </p>
                    <h1>Applicant Management</h1>
                    <p>
                        Review applications and record screening and interview
                        outcomes.
                    </p>
                </header>
                <section className="grid gap-3 sm:grid-cols-4">
                    {[
                        ['Total Applicants', summary.total],
                        ['For Screening', summary.for_screening],
                        ['For Final Interview', summary.for_final_interview],
                        ['Passed', summary.passed],
                    ].map(([l, v]) => (
                        <div
                            className="rounded-xl border bg-white p-4"
                            key={String(l)}
                        >
                            <p className="text-xs font-bold text-slate-500 uppercase">
                                {l}
                            </p>
                            <b className="mt-1 block text-xl">{v}</b>
                        </div>
                    ))}
                </section>
                <section className="grid gap-2 rounded-xl border bg-white p-3 md:grid-cols-3">
                    <input
                        className="rounded-lg border p-2"
                        placeholder="Search applicants"
                        defaultValue={filters.search || ''}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                filter('search', e.currentTarget.value);
                            }
                        }}
                    />
                    <Filter
                        label="Status"
                        value={filters.stage}
                        options={stages}
                        onChange={(v) => filter('stage', v)}
                    />
                    <button
                        className="rounded-lg border border-red-300 font-bold text-red-700"
                        onClick={() => router.get('/management/applicants')}
                    >
                        Clear Filters
                    </button>
                </section>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="w-full min-w-[1050px] text-left">
                        <thead>
                            <tr className="bg-slate-50">
                                {[
                                    'Applicant',
                                    'Position',
                                    'Contact',
                                    'Experience',
                                    'Status',
                                    'Applied',
                                    'Action',
                                ].map((h) => (
                                    <th className="p-3" key={h}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {applications.data.map((a) => (
                                <tr className="border-t" key={a.id}>
                                    <td className="p-3">
                                        <b>
                                            {a.first_name} {a.last_name}
                                        </b>
                                        <p className="text-slate-500">
                                            {a.location ||
                                                'Location not provided'}
                                        </p>
                                    </td>
                                    <td className="p-3">{a.position}</td>
                                    <td className="p-3">
                                        {a.email}
                                        <br />
                                        {a.phone}
                                    </td>
                                    <td className="p-3">
                                        {a.years_experience} year(s)
                                    </td>
                                    <td className="p-3">
                                        <Badge value={a.applicant_stage} />
                                    </td>
                                    <td className="p-3">
                                        {new Date(
                                            a.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                    <td className="p-3">
                                        <button
                                            className="font-bold text-red-700"
                                            onClick={() => open(a)}
                                        >
                                            Review
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!applications.data.length && (
                        <p className="p-10 text-center text-slate-500">
                            No applicants found.
                        </p>
                    )}
                </section>
                {applications.last_page > 1 && (
                    <div className="flex gap-2">
                        {applications.prev_page_url && (
                            <Link
                                className="rounded border px-3 py-2"
                                href={applications.prev_page_url}
                            >
                                Previous
                            </Link>
                        )}
                        <span className="rounded bg-red-700 px-3 py-2 text-white">
                            {applications.current_page}
                        </span>
                        {applications.next_page_url && (
                            <Link
                                className="rounded border px-3 py-2"
                                href={applications.next_page_url}
                            >
                                Next
                            </Link>
                        )}
                    </div>
                )}
                <Dialog
                    open={!!selected}
                    onClose={() => setSelected(null)}
                    fullWidth
                    maxWidth="sm"
                >
                    <DialogTitle>Review Applicant</DialogTitle>
                    <DialogContent>
                        {selected && (
                            <div className="mt-2 space-y-4">
                                <div className="rounded-xl bg-slate-50 p-4">
                                    <b>
                                        {selected.first_name}{' '}
                                        {selected.last_name}
                                    </b>
                                    <p>
                                        {selected.position} · {selected.email} ·{' '}
                                        {selected.phone}
                                    </p>
                                    {selected.message && (
                                        <p className="mt-3">
                                            {selected.message}
                                        </p>
                                    )}
                                    {selected.resume_path && (
                                        <a
                                            className="mt-3 inline-block font-bold text-red-700"
                                            href={`/management/applicants/${selected.id}/resume`}
                                        >
                                            Download Résumé —{' '}
                                            {selected.resume_original_name}
                                        </a>
                                    )}
                                </div>
                                <Filter
                                    label="Application Status"
                                    value={form.data.applicant_stage}
                                    options={stages}
                                    onChange={(v) =>
                                        form.setData('applicant_stage', v)
                                    }
                                />
                                <label className="block font-bold">
                                    Update Visible to Applicant
                                    <textarea
                                        className="mt-1 min-h-24 w-full rounded-lg border p-3"
                                        value={form.data.applicant_update}
                                        onChange={(e) =>
                                            form.setData(
                                                'applicant_update',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Example: You passed the initial screening. Our recruitment team will contact you to schedule an interview."
                                    />
                                    <span className="mt-1 block text-xs font-normal text-slate-500">
                                        Applicants can see this message after
                                        matching their phone number and email.
                                    </span>
                                </label>
                                <label className="block font-bold">
                                    Internal Notes
                                    <textarea
                                        className="mt-1 min-h-28 w-full rounded-lg border p-3"
                                        value={form.data.internal_notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'internal_notes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <button
                            className="px-4 py-2"
                            onClick={() => setSelected(null)}
                        >
                            Cancel
                        </button>
                        <button
                            className="rounded-lg bg-red-700 px-4 py-2 font-bold text-white"
                            disabled={form.processing}
                            onClick={() =>
                                selected &&
                                form.put(
                                    `/management/applicants/${selected.id}`,
                                    { onSuccess: () => setSelected(null) },
                                )
                            }
                        >
                            Save Review
                        </button>
                    </DialogActions>
                </Dialog>
            </div>
        </main>
    );
}
function Filter({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value?: string;
    options: string[];
    onChange: (v: string) => void;
}) {
    return (
        <select
            aria-label={label}
            className="w-full rounded-lg border bg-white p-2"
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="">All {label}</option>
            {options.map((x) => (
                <option value={x} key={x}>
                    {x
                        .split('_')
                        .map((v) => v[0].toUpperCase() + v.slice(1))
                        .join(' ')}
                </option>
            ))}
        </select>
    );
}
function Badge({ value }: { value: string }) {
    const color =
        value === 'passed' || value === 'hired'
            ? 'bg-green-100 text-green-800'
            : value === 'failed' || value === 'rejected'
              ? 'bg-red-100 text-red-800'
              : 'bg-amber-100 text-amber-800';

    return (
        <span className={`rounded-full px-3 py-1 font-bold ${color}`}>
            {value
                .split('_')
                .map((x) => x[0].toUpperCase() + x.slice(1))
                .join(' ')}
        </span>
    );
}
Applicants.layout = {
    breadcrumbs: [
        { title: 'Management', href: '/management/applicants' },
        { title: 'Applicants', href: '/management/applicants' },
    ],
};
