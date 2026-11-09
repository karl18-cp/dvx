import { Head, router } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import {
    Activity,
    CalendarDays,
    Eye,
    Search,
    ShieldCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';

type Person = { id: number; name: string; username: string };
type AuditLog = {
    id: number;
    category: string;
    action: string;
    actor: Person | null;
    subject: Person | null;
    target: { type: string; id: number };
    metadata: Record<string, unknown>;
    ip_address: string | null;
    created_at: string;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const labels: Record<string, string> = {
    attendance: 'Attendance',
    payroll: 'Payroll & accounting',
    banking: 'Bank information',
    scheduling: 'Schedules & teams',
    account_status: 'Account status',
    offboarding: 'Offboarding',
    documents: 'Employee documents',
    contracts: 'Contracts',
    sanctions: 'Sanctions',
    approvals: 'Approvals & requests',
    permissions: 'Roles & permissions',
    backups: 'Backups & recovery',
    performance: 'Performance management',
    assessments: 'Assessments',
    training: 'Training & coaching',
    quality: 'Quality assurance',
    general: 'General',
};
const colors: Record<string, string> = {
    attendance: 'bg-blue-50 text-blue-700',
    payroll: 'bg-emerald-50 text-emerald-700',
    banking: 'bg-violet-50 text-violet-700',
    scheduling: 'bg-cyan-50 text-cyan-700',
    account_status: 'bg-amber-50 text-amber-800',
    offboarding: 'bg-rose-50 text-rose-800',
    documents: 'bg-indigo-50 text-indigo-800',
    contracts: 'bg-indigo-50 text-indigo-700',
    sanctions: 'bg-red-50 text-red-700',
    approvals: 'bg-orange-50 text-orange-700',
    permissions: 'bg-sky-50 text-sky-700',
    backups: 'bg-emerald-50 text-emerald-700',
    performance: 'bg-violet-50 text-violet-700',
    assessments: 'bg-fuchsia-50 text-fuchsia-700',
    training: 'bg-teal-50 text-teal-700',
    quality: 'bg-purple-50 text-purple-700',
    general: 'bg-slate-100 text-slate-700',
};

export default function AuditTrail({
    logs,
    actors,
    categories,
    filters,
    summary,
}: {
    logs: Paginated<AuditLog>;
    actors: Person[];
    categories: string[];
    filters: {
        search?: string;
        category?: string;
        actor?: number | string;
        from?: string;
        to?: string;
    };
    summary: { today: number; week: number; total: number };
}) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        category: filters.category ?? '',
        actor: String(filters.actor ?? ''),
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const [selected, setSelected] = useState<AuditLog | null>(null);
    const apply = () =>
        router.get(
            '/audit-trail',
            Object.fromEntries(
                Object.entries(form).filter(([, value]) => value),
            ),
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Audit Trail" />
            <main className="min-h-full bg-gradient-to-br from-rose-50/70 via-slate-50 to-white p-4 sm:p-6 lg:p-8">
                <header className="mb-6">
                    <p className="text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                        Security & accountability
                    </p>
                    <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold text-slate-950">
                        <ShieldCheck className="text-red-700" /> Central Audit
                        Trail
                    </h1>
                    <p className="mt-2 text-slate-600">
                        Immutable history of important changes across the
                        workplace system.
                    </p>
                </header>
                <section className="mb-5 grid gap-3 sm:grid-cols-3">
                    {[
                        ['Last 24 hours', summary.today, Activity],
                        ['Last 7 days', summary.week, CalendarDays],
                        ['All recorded activity', summary.total, ShieldCheck],
                    ].map(([label, value, Icon]) => {
                        const CardIcon = Icon as typeof Activity;
                        return (
                            <div
                                key={String(label)}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <CardIcon
                                    className="mb-3 text-red-700"
                                    size={22}
                                />
                                <p className="text-sm text-slate-500">
                                    {String(label)}
                                </p>
                                <p className="mt-1 text-3xl font-bold text-slate-950">
                                    {Number(value).toLocaleString()}
                                </p>
                            </div>
                        );
                    })}
                </section>
                <section className="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                        <TextField
                            size="small"
                            label="Search action or person"
                            value={form.search}
                            onChange={(e) =>
                                setForm({ ...form, search: e.target.value })
                            }
                            className="xl:col-span-2"
                        />
                        <TextField
                            select
                            size="small"
                            label="Category"
                            value={form.category}
                            onChange={(e) =>
                                setForm({ ...form, category: e.target.value })
                            }
                        >
                            <MenuItem value="">All categories</MenuItem>
                            {categories.map((category) => (
                                <MenuItem key={category} value={category}>
                                    {labels[category] ?? category}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            size="small"
                            label="Changed by"
                            value={form.actor}
                            onChange={(e) =>
                                setForm({ ...form, actor: e.target.value })
                            }
                        >
                            <MenuItem value="">Anyone</MenuItem>
                            {actors.map((actor) => (
                                <MenuItem key={actor.id} value={actor.id}>
                                    {actor.name} · {actor.username}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            size="small"
                            label="From"
                            type="date"
                            value={form.from}
                            onChange={(e) =>
                                setForm({ ...form, from: e.target.value })
                            }
                            slotProps={{ inputLabel: { shrink: true } }}
                        />
                        <TextField
                            size="small"
                            label="To"
                            type="date"
                            value={form.to}
                            onChange={(e) =>
                                setForm({ ...form, to: e.target.value })
                            }
                            slotProps={{ inputLabel: { shrink: true } }}
                        />
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        <Button
                            onClick={() => {
                                const empty = {
                                    search: '',
                                    category: '',
                                    actor: '',
                                    from: '',
                                    to: '',
                                };
                                setForm(empty);
                                router.get('/audit-trail');
                            }}
                        >
                            Clear
                        </Button>
                        <Button
                            variant="contained"
                            startIcon={<Search size={17} />}
                            onClick={apply}
                        >
                            Apply filters
                        </Button>
                    </div>
                </section>
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[920px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                <tr>
                                    <th className="px-5 py-4">Date & time</th>
                                    <th className="px-5 py-4">Changed by</th>
                                    <th className="px-5 py-4">Category</th>
                                    <th className="px-5 py-4">Action</th>
                                    <th className="px-5 py-4">
                                        Employee / record
                                    </th>
                                    <th className="px-5 py-4 text-right">
                                        Details
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.data.map((log) => (
                                    <tr
                                        key={log.id}
                                        className="hover:bg-slate-50/70"
                                    >
                                        <td className="px-5 py-4 whitespace-nowrap text-slate-600">
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleString('en-PH', {
                                                timeZone: 'Asia/Manila',
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </td>
                                        <td className="px-5 py-4">
                                            <p className="font-semibold text-slate-900">
                                                {log.actor?.name ?? 'System'}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {log.actor?.username ??
                                                    'Automated action'}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-bold ${colors[log.category] ?? colors.general}`}
                                            >
                                                {labels[log.category] ??
                                                    log.category}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 font-medium text-slate-900">
                                            {log.action}
                                        </td>
                                        <td className="px-5 py-4">
                                            <p className="font-medium text-slate-800">
                                                {log.subject?.name ??
                                                    `${log.target.type} #${log.target.id}`}
                                            </p>
                                            {log.subject && (
                                                <p className="text-xs text-slate-500">
                                                    {log.subject.username}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <IconButton
                                                aria-label={`View details for ${log.action}`}
                                                onClick={() => setSelected(log)}
                                            >
                                                <Eye size={18} />
                                            </IconButton>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!logs.data.length && (
                        <div className="p-12 text-center text-slate-500">
                            No audit records match these filters.
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-500">
                        <span>
                            {logs.from
                                ? `Showing ${logs.from}–${logs.to} of ${logs.total}`
                                : 'No records'}
                        </span>
                        <div className="flex flex-wrap gap-1">
                            {logs.links.map((link, index) => (
                                <Button
                                    key={index}
                                    size="small"
                                    variant={link.active ? 'contained' : 'text'}
                                    disabled={!link.url}
                                    onClick={() =>
                                        link.url &&
                                        router.get(
                                            link.url,
                                            {},
                                            { preserveState: true },
                                        )
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                </section>
            </main>
            <Dialog
                open={Boolean(selected)}
                onClose={() => setSelected(null)}
                fullWidth
                maxWidth="md"
                slotProps={{ paper: { sx: { borderRadius: 3 } } }}
            >
                <DialogTitle sx={{ fontWeight: 800, pr: 7 }}>
                    Audit record #{selected?.id}
                </DialogTitle>
                <IconButton
                    aria-label="Close audit details"
                    onClick={() => setSelected(null)}
                    sx={{ position: 'absolute', right: 12, top: 12 }}
                >
                    <X />
                </IconButton>
                <DialogContent dividers>
                    {selected && (
                        <div className="space-y-5">
                            <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-bold text-slate-500 uppercase">
                                        Action
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {selected.action}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-500 uppercase">
                                        Actor
                                    </p>
                                    <p className="mt-1 font-semibold">
                                        {selected.actor?.name ?? 'System'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-500 uppercase">
                                        Target
                                    </p>
                                    <p className="mt-1">
                                        {selected.target.type} #
                                        {selected.target.id}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-slate-500 uppercase">
                                        Source address
                                    </p>
                                    <p className="mt-1">
                                        {selected.ip_address ?? 'Not recorded'}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <h3 className="mb-2 font-bold text-slate-900">
                                    Recorded changes
                                </h3>
                                <pre className="max-h-[48dvh] overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">
                                    {JSON.stringify(selected.metadata, null, 2)}
                                </pre>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

AuditTrail.layout = {
    breadcrumbs: [{ title: 'Audit Trail', href: '/audit-trail' }],
};
