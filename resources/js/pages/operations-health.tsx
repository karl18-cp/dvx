import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    Database,
    HardDrive,
    HeartPulse,
    Mail,
    RefreshCw,
    ServerCog,
    ShieldAlert,
    TimerReset,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

type Status = 'healthy' | 'warning' | 'critical';
type Service = {
    status: Status;
    label: string;
    message: string;
    metrics: Record<string, string | number | null>;
};
type FailedJob = {
    id: number;
    uuid: string;
    connection: string;
    queue: string;
    job: string;
    failed_at: string;
    error_type: string;
};

const serviceIcons: Record<string, LucideIcon> = {
    email: Mail,
    scheduler: Clock3,
    database: Database,
    storage: HardDrive,
    failedJobs: ShieldAlert,
    backup: ServerCog,
    uptime: TimerReset,
};
const statusStyles: Record<Status, string> = {
    healthy: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    critical: 'border-red-200 bg-red-50 text-red-700',
};
const statusIcon = {
    healthy: CheckCircle2,
    warning: AlertTriangle,
    critical: XCircle,
};

const bytes = (value: number) => {
    if (!value) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(
        Math.floor(Math.log(value) / Math.log(1024)),
        units.length - 1,
    );
    return `${(value / 1024 ** index).toFixed(index ? 1 : 0)} ${units[index]}`;
};
const duration = (value: number) => {
    const days = Math.floor(value / 86400);
    const hours = Math.floor((value % 86400) / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    return [days ? `${days}d` : '', hours ? `${hours}h` : '', `${minutes}m`]
        .filter(Boolean)
        .join(' ');
};
const date = (value: string | null | undefined) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: 'Asia/Manila',
          }).format(new Date(value))
        : 'Not recorded';

export default function OperationsHealth({
    services,
    summary,
    failed_job_items,
}: {
    services: Record<string, Service>;
    summary: {
        healthy: number;
        warning: number;
        critical: number;
        checked_at: string;
    };
    failed_job_items: FailedJob[];
}) {
    const [refreshing, setRefreshing] = useState(false);
    const refresh = () => {
        setRefreshing(true);
        router.reload({
            only: ['services', 'summary', 'failed_job_items'],
            onFinish: () => setRefreshing(false),
        });
    };

    useEffect(() => {
        const timer = window.setInterval(refresh, 30_000);
        return () => window.clearInterval(timer);
    }, []);

    const overall: Status = summary.critical
        ? 'critical'
        : summary.warning
          ? 'warning'
          : 'healthy';
    const OverallIcon = statusIcon[overall];

    return (
        <>
            <Head title="Operations Health" />
            <main className="min-h-full bg-gradient-to-br from-rose-50/70 via-slate-50 to-white p-4 sm:p-6 lg:p-8">
                <header className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                            System observability
                        </p>
                        <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold text-slate-950">
                            <HeartPulse className="text-red-700" /> Operations
                            Health
                        </h1>
                        <p className="mt-2 max-w-3xl text-slate-600">
                            Live operational status for the services that keep
                            Divertex running. This page refreshes every 30
                            seconds.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={refresh}
                        disabled={refreshing}
                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
                    >
                        <RefreshCw
                            size={18}
                            className={refreshing ? 'animate-spin' : ''}
                        />
                        Refresh status
                    </button>
                </header>

                <section
                    className={`mb-5 flex flex-col gap-4 rounded-2xl border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between ${statusStyles[overall]}`}
                >
                    <div className="flex items-start gap-3">
                        <OverallIcon className="mt-0.5 shrink-0" size={26} />
                        <div>
                            <h2 className="text-lg font-bold">
                                {overall === 'healthy'
                                    ? 'All monitored services are healthy'
                                    : overall === 'warning'
                                      ? 'Some services need attention'
                                      : 'Critical operations issue detected'}
                            </h2>
                            <p className="mt-1 text-sm opacity-80">
                                Last checked {date(summary.checked_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 text-sm font-semibold">
                        <span className="rounded-full bg-white/70 px-3 py-1.5">
                            {summary.healthy} healthy
                        </span>
                        <span className="rounded-full bg-white/70 px-3 py-1.5">
                            {summary.warning} warning
                        </span>
                        <span className="rounded-full bg-white/70 px-3 py-1.5">
                            {summary.critical} critical
                        </span>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {Object.entries(services).map(([key, service]) => (
                        <ServiceCard
                            key={key}
                            serviceKey={key}
                            service={service}
                        />
                    ))}
                </section>

                <section className="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 className="font-bold text-slate-950">
                                Recent failed jobs
                            </h2>
                            <p className="text-sm text-slate-500">
                                Queue failures requiring investigation or retry
                            </p>
                        </div>
                        <span
                            className={`rounded-full px-3 py-1 text-xs font-bold ${failed_job_items.length ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'}`}
                        >
                            {failed_job_items.length
                                ? `${failed_job_items.length} shown`
                                : 'Clear'}
                        </span>
                    </div>
                    {failed_job_items.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[800px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        <th className="px-5 py-3">Job</th>
                                        <th className="px-5 py-3">Queue</th>
                                        <th className="px-5 py-3">
                                            Error type
                                        </th>
                                        <th className="px-5 py-3">Failed at</th>
                                        <th className="px-5 py-3">Reference</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {failed_job_items.map((job) => (
                                        <tr key={job.id}>
                                            <td className="px-5 py-4 font-semibold text-slate-900">
                                                {job.job}
                                            </td>
                                            <td className="px-5 py-4 text-slate-600">
                                                {job.connection} / {job.queue}
                                            </td>
                                            <td className="px-5 py-4 text-red-700">
                                                {job.error_type}
                                            </td>
                                            <td className="px-5 py-4 text-slate-600">
                                                {date(job.failed_at)}
                                            </td>
                                            <td className="px-5 py-4 font-mono text-xs text-slate-500">
                                                {job.uuid.slice(0, 12)}…
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center px-5 py-12 text-center">
                            <CheckCircle2
                                className="text-emerald-600"
                                size={34}
                            />
                            <p className="mt-3 font-semibold text-slate-900">
                                No failed jobs
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                The queue failure register is clear.
                            </p>
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}

function ServiceCard({
    serviceKey,
    service,
}: {
    serviceKey: string;
    service: Service;
}) {
    const Icon = serviceIcons[serviceKey] ?? ServerCog;
    const StatusIcon = statusIcon[service.status];
    const metrics = metricRows(serviceKey, service.metrics);

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div
                    className={`flex size-11 items-center justify-center rounded-xl border ${statusStyles[service.status]}`}
                >
                    <Icon size={22} />
                </div>
                <span
                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusStyles[service.status]}`}
                >
                    <StatusIcon size={14} /> {service.status}
                </span>
            </div>
            <h2 className="mt-4 text-lg font-bold text-slate-950">
                {service.label}
            </h2>
            <p className="mt-1 min-h-10 text-sm text-slate-600">
                {service.message}
            </p>
            <dl className="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                {metrics.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-start justify-between gap-4"
                    >
                        <dt className="text-slate-500">{label}</dt>
                        <dd className="text-right font-semibold text-slate-800">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
        </article>
    );
}

function metricRows(
    key: string,
    metrics: Record<string, string | number | null>,
): [string, string][] {
    if (key === 'email') {
        return [
            ['Pending', String(metrics.pending_deliveries ?? 0)],
            ['Failed deliveries', String(metrics.failed_deliveries ?? 0)],
            ['Queued jobs', String(metrics.queued_jobs ?? 0)],
            ['Last sent', date(metrics.last_sent_at as string | null)],
        ];
    }
    if (key === 'scheduler') {
        return [
            [
                'Last heartbeat',
                date(metrics.last_heartbeat_at as string | null),
            ],
            [
                'Heartbeat age',
                `${Math.round(Number(metrics.age_seconds ?? 0))} sec`,
            ],
        ];
    }
    if (key === 'database') {
        return [
            ['Response time', `${metrics.latency_ms ?? '—'} ms`],
            ['Driver', String(metrics.driver ?? '—')],
        ];
    }
    if (key === 'storage') {
        return [
            ['Disk used', `${metrics.used_percent ?? '—'}%`],
            ['Available', bytes(Number(metrics.free_bytes ?? 0))],
            ['Private files', bytes(Number(metrics.private_files_bytes ?? 0))],
        ];
    }
    if (key === 'failedJobs') {
        return [
            ['Failed count', String(metrics.count ?? 0)],
            ['Latest failure', date(metrics.latest_failed_at as string | null)],
        ];
    }
    if (key === 'backup') {
        return [
            ['Backup', metrics.backup_id ? `#${metrics.backup_id}` : 'None'],
            ['Completed', date(metrics.completed_at as string | null)],
            [
                'Restore test',
                String(metrics.restore_test_status ?? 'Not tested'),
            ],
        ];
    }
    return [
        ['Available for', duration(Number(metrics.uptime_seconds ?? 0))],
        ['Healthy since', date(metrics.healthy_since as string | null)],
        ['PHP', String(metrics.php_version ?? '—')],
    ];
}
