import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    Database,
    Download,
    FileArchive,
    HardDriveDownload,
    PlayCircle,
    RefreshCw,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

type ContentGroup = { label: string; files: number; bytes: number };
type Backup = {
    id: number;
    status: string;
    trigger: string;
    requester: { id: number; name: string; username: string } | null;
    encrypted_size: number | null;
    encrypted_checksum: string | null;
    contents: Record<string, ContentGroup> | null;
    database_tables: number;
    database_rows: number;
    file_count: number;
    source_bytes: number;
    started_at: string | null;
    completed_at: string | null;
    restore_tested_at: string | null;
    restore_test_status: string | null;
    restore_test_message: string | null;
    failure_message: string | null;
    downloadable: boolean;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const bytes = (value: number | null) => {
    if (!value) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(
        Math.floor(Math.log(value) / Math.log(1024)),
        units.length - 1,
    );
    return `${(value / 1024 ** index).toFixed(index ? 1 : 0)} ${units[index]}`;
};
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: 'Asia/Manila',
          }).format(new Date(value))
        : 'Not yet';

const statusStyle: Record<string, string> = {
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    running: 'bg-blue-50 text-blue-700 ring-blue-200',
    failed: 'bg-red-50 text-red-700 ring-red-200',
    expired: 'bg-slate-100 text-slate-600 ring-slate-200',
};

export default function BackupRecovery({
    backups,
    health,
    statusMessage,
}: {
    backups: Paginated<Backup>;
    health: {
        latest_status: string;
        latest_completed_at: string | null;
        last_restore_test_at: string | null;
        retention_days: number;
        schedule: string;
        timezone: string;
        encryption: string;
    };
    statusMessage?: string;
}) {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [busy, setBusy] = useState<string | null>(null);
    const latest = backups.data[0];
    const runBackup = () => {
        setBusy('run');
        router.post(
            '/backup-recovery/run',
            {},
            { onFinish: () => setBusy(null), preserveScroll: true },
        );
    };
    const testRestore = (id: number) => {
        setBusy(`test-${id}`);
        router.post(
            `/backup-recovery/${id}/test`,
            {},
            { onFinish: () => setBusy(null), preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Backup & Recovery" />
            <main className="min-h-full bg-gradient-to-br from-rose-50/70 via-slate-50 to-white p-4 sm:p-6 lg:p-8">
                <header className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                            Resilience & recovery
                        </p>
                        <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold text-slate-950">
                            <HardDriveDownload className="text-red-700" />
                            Backup & Recovery
                        </h1>
                        <p className="mt-2 max-w-3xl text-slate-600">
                            Encrypted backups of the database and private
                            employee files, with checksum-based restore tests
                            and automatic failure alerts.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={runBackup}
                        disabled={busy !== null}
                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <RefreshCw
                            size={18}
                            className={busy === 'run' ? 'animate-spin' : ''}
                        />
                        {busy === 'run' ? 'Creating backup…' : 'Back up now'}
                    </button>
                </header>

                {(statusMessage || errors?.backup || errors?.restore) && (
                    <div
                        className={`mb-5 rounded-2xl border px-4 py-3 text-sm ${errors?.backup || errors?.restore ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}
                    >
                        {errors?.backup ?? errors?.restore ?? statusMessage}
                    </div>
                )}

                <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <HealthCard
                        icon={
                            health.latest_status === 'completed' ? (
                                <CheckCircle2 />
                            ) : (
                                <AlertTriangle />
                            )
                        }
                        label="Latest backup"
                        value={health.latest_status.replace('_', ' ')}
                        detail={date(health.latest_completed_at)}
                        healthy={health.latest_status === 'completed'}
                    />
                    <HealthCard
                        icon={<ShieldCheck />}
                        label="Last restore test"
                        value={
                            health.last_restore_test_at
                                ? 'Verified'
                                : 'Not tested'
                        }
                        detail={date(health.last_restore_test_at)}
                        healthy={Boolean(health.last_restore_test_at)}
                    />
                    <HealthCard
                        icon={<Clock3 />}
                        label="Automatic schedule"
                        value={`Daily at ${health.schedule}`}
                        detail={`${health.timezone} · ${health.retention_days}-day retention`}
                        healthy
                    />
                    <HealthCard
                        icon={<FileArchive />}
                        label="Protection"
                        value="Encrypted"
                        detail={health.encryption}
                        healthy
                    />
                </section>

                {latest?.contents && (
                    <section className="mb-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-3">
                            <Database className="text-red-700" size={22} />
                            <div>
                                <h2 className="font-bold text-slate-950">
                                    Latest protected contents
                                </h2>
                                <p className="text-sm text-slate-500">
                                    Each entry is authenticated and checksum
                                    verified during restore testing.
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {Object.entries(latest.contents).map(
                                ([key, content]) => (
                                    <div
                                        key={key}
                                        className="rounded-xl border border-slate-200 bg-slate-50 p-4"
                                    >
                                        <p className="font-semibold text-slate-900">
                                            {content.label}
                                        </p>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {content.files.toLocaleString()}{' '}
                                            {content.files === 1
                                                ? 'entry'
                                                : 'files'}{' '}
                                            · {bytes(content.bytes)}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </section>
                )}

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="font-bold text-slate-950">
                            Backup history
                        </h2>
                        <p className="text-sm text-slate-500">
                            {backups.total.toLocaleString()} recorded backup
                            runs
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1050px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                <tr>
                                    <th className="px-5 py-3">Backup</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">
                                        Protected data
                                    </th>
                                    <th className="px-5 py-3">
                                        Encrypted file
                                    </th>
                                    <th className="px-5 py-3">Restore test</th>
                                    <th className="px-5 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {backups.data.map((backup) => (
                                    <tr
                                        key={backup.id}
                                        className="align-top hover:bg-slate-50/60"
                                    >
                                        <td className="px-5 py-4">
                                            <p className="font-semibold text-slate-950">
                                                #{backup.id} ·{' '}
                                                {backup.trigger === 'manual'
                                                    ? 'Manual'
                                                    : 'Scheduled'}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {date(backup.started_at)}
                                            </p>
                                            {backup.requester && (
                                                <p className="mt-1 text-xs text-slate-500">
                                                    By {backup.requester.name}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold capitalize ring-1 ring-inset ${statusStyle[backup.status] ?? statusStyle.expired}`}
                                            >
                                                {backup.status}
                                            </span>
                                            {backup.failure_message && (
                                                <p className="mt-2 max-w-xs text-xs text-red-700">
                                                    {backup.failure_message}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-slate-700">
                                            <p>
                                                {backup.database_tables.toLocaleString()}{' '}
                                                tables ·{' '}
                                                {backup.database_rows.toLocaleString()}{' '}
                                                rows
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {backup.file_count.toLocaleString()}{' '}
                                                private files ·{' '}
                                                {bytes(backup.source_bytes)}{' '}
                                                source
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <p className="font-medium text-slate-800">
                                                {bytes(backup.encrypted_size)}
                                            </p>
                                            {backup.encrypted_checksum && (
                                                <p
                                                    className="mt-1 max-w-40 truncate font-mono text-xs text-slate-500"
                                                    title={
                                                        backup.encrypted_checksum
                                                    }
                                                >
                                                    SHA-256{' '}
                                                    {backup.encrypted_checksum}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            {backup.restore_test_status ? (
                                                <div
                                                    className={
                                                        backup.restore_test_status ===
                                                        'passed'
                                                            ? 'text-emerald-700'
                                                            : 'text-red-700'
                                                    }
                                                >
                                                    <p className="flex items-center gap-1.5 font-semibold capitalize">
                                                        {backup.restore_test_status ===
                                                        'passed' ? (
                                                            <CheckCircle2
                                                                size={16}
                                                            />
                                                        ) : (
                                                            <XCircle
                                                                size={16}
                                                            />
                                                        )}
                                                        {
                                                            backup.restore_test_status
                                                        }
                                                    </p>
                                                    <p className="mt-1 max-w-xs text-xs text-slate-500">
                                                        {
                                                            backup.restore_test_message
                                                        }
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        {date(
                                                            backup.restore_tested_at,
                                                        )}
                                                    </p>
                                                </div>
                                            ) : (
                                                <span className="text-slate-400">
                                                    Not tested
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex justify-end gap-2">
                                                {backup.downloadable && (
                                                    <a
                                                        href={`/backup-recovery/${backup.id}/download`}
                                                        className="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        <Download size={15} />
                                                        Download
                                                    </a>
                                                )}
                                                {backup.downloadable && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            testRestore(
                                                                backup.id,
                                                            )
                                                        }
                                                        disabled={busy !== null}
                                                        className="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-red-200 px-3 font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50"
                                                    >
                                                        <PlayCircle size={15} />
                                                        {busy ===
                                                        `test-${backup.id}`
                                                            ? 'Testing…'
                                                            : 'Test restore'}
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!backups.data.length && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-5 py-14 text-center text-slate-500"
                                        >
                                            No backups yet. Create the first
                                            encrypted recovery point.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {backups.links.length > 3 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 text-sm text-slate-500">
                            <span>
                                Showing {backups.from ?? 0}–{backups.to ?? 0} of{' '}
                                {backups.total}
                            </span>
                            <div className="flex gap-1">
                                {backups.links.map((link, index) => (
                                    <button
                                        key={`${link.label}-${index}`}
                                        type="button"
                                        disabled={!link.url}
                                        onClick={() =>
                                            link.url &&
                                            router.visit(link.url, {
                                                preserveScroll: true,
                                            })
                                        }
                                        className={`min-h-9 rounded-lg px-3 ${link.active ? 'bg-red-700 font-bold text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'} disabled:opacity-40`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}

function HealthCard({
    icon,
    label,
    value,
    detail,
    healthy,
}: {
    icon: ReactNode;
    label: string;
    value: string;
    detail: string;
    healthy: boolean;
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div
                className={`mb-4 flex size-10 items-center justify-center rounded-xl ${healthy ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}
            >
                {icon}
            </div>
            <p className="text-sm text-slate-500">{label}</p>
            <p className="mt-1 text-xl font-bold text-slate-950 capitalize">
                {value}
            </p>
            <p className="mt-1 text-xs text-slate-500">{detail}</p>
        </div>
    );
}
