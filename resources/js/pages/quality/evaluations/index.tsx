import { Head, Link, router } from '@inertiajs/react';
import { DateTimeField } from '@/components/date-time-field';

type Ref = { id: number; name: string };
type Row = {
    id: number;
    campaign_name: string;
    team_name: string;
    scorecard_name: string;
    call_at: string;
    status: string;
    percentage?: string;
    result?: string;
    updated_at: string;
    employee: Ref & { username: string };
    evaluator: Ref;
};
type Page = {
    data: Row[];
    current_page: number;
    last_page: number;
    prev_page_url?: string;
    next_page_url?: string;
};

export default function Index({
    evaluations,
    filters,
    employees,
    campaigns,
    teams,
    scorecards,
    evaluators,
}: {
    evaluations: Page;
    filters: Record<string, string>;
    employees: Ref[];
    campaigns: Ref[];
    teams: Ref[];
    scorecards: Ref[];
    evaluators: Ref[];
}) {
    const set = (key: string, value: string) =>
        router.get(
            '/management/call-evaluations',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    const label = (v?: string) =>
        v
            ? v
                  .split('_')
                  .map((x) => x[0].toUpperCase() + x.slice(1))
                  .join(' ')
                  .replace('Failed Critical', 'Failed — Critical')
            : '—';

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-slate-900">
            <Head title="Call Evaluations" />
            <div className="mx-auto max-w-7xl space-y-5">
                <header className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                            Quality Assurance
                        </p>
                        <h1>Call Evaluations</h1>
                        <p>
                            Score recorded calls using an approved QA Scorecard.
                        </p>
                    </div>
                    <Link
                        className="rounded-xl bg-red-700 px-5 py-3 font-bold text-white"
                        href="/management/call-evaluations/create"
                    >
                        New Evaluation
                    </Link>
                </header>
                <section className="grid gap-2 rounded-2xl border bg-white p-4 md:grid-cols-4">
                    <Filter
                        name="Employee"
                        value={filters.employee}
                        options={employees}
                        onChange={(v) => set('employee', v)}
                    />
                    <Filter
                        name="Campaign"
                        value={filters.campaign}
                        options={campaigns}
                        onChange={(v) => set('campaign', v)}
                    />
                    <Filter
                        name="Team"
                        value={filters.team}
                        options={teams}
                        onChange={(v) => set('team', v)}
                    />
                    <Filter
                        name="Scorecard"
                        value={filters.scorecard}
                        options={scorecards}
                        onChange={(v) => set('scorecard', v)}
                    />
                    <Filter
                        name="Evaluator"
                        value={filters.evaluator}
                        options={evaluators}
                        onChange={(v) => set('evaluator', v)}
                    />
                    <Filter
                        name="Status"
                        value={filters.status}
                        values={['draft', 'submitted']}
                        onChange={(v) => set('status', v)}
                    />
                    <Filter
                        name="Result"
                        value={filters.result}
                        values={['passed', 'failed', 'failed_critical']}
                        onChange={(v) => set('result', v)}
                    />
                    <button
                        className="rounded-lg border border-red-300 font-semibold text-red-700"
                        onClick={() =>
                            router.get('/management/call-evaluations')
                        }
                    >
                        Clear Filters
                    </button>
                    <DateTimeField
                        className="rounded-lg border px-3 py-2"
                        type="date"
                        value={filters.from || ''}
                        onChange={(e) => set('from', e.target.value)}
                    />
                    <DateTimeField
                        className="rounded-lg border px-3 py-2"
                        type="date"
                        value={filters.to || ''}
                        onChange={(e) => set('to', e.target.value)}
                    />
                </section>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="w-full min-w-[1050px] text-left text-sm">
                        <thead>
                            <tr className="bg-slate-50">
                                {[
                                    'Employee',
                                    'Campaign',
                                    'Team',
                                    'Scorecard',
                                    'Evaluator',
                                    'Call Date',
                                    'Score',
                                    'Result',
                                    'Status',
                                    'Action',
                                ].map((h) => (
                                    <th className="px-4 py-3" key={h}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {evaluations.data.map((e) => (
                                <tr className="border-t" key={e.id}>
                                    <td className="px-4 py-3">
                                        <b>{e.employee.name}</b>
                                        <div className="text-xs text-slate-500">
                                            {e.employee.username}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.campaign_name}
                                    </td>
                                    <td className="px-4 py-3">{e.team_name}</td>
                                    <td className="px-4 py-3">
                                        {e.scorecard_name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.evaluator.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(e.call_at).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.percentage !== null &&
                                        e.percentage !== undefined
                                            ? `${e.percentage}%`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 font-semibold">
                                        {label(e.result)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {label(e.status)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            className="font-bold text-red-700"
                                            href={
                                                e.status === 'draft'
                                                    ? `/management/call-evaluations/${e.id}/edit`
                                                    : `/management/call-evaluations/${e.id}`
                                            }
                                        >
                                            {e.status === 'draft'
                                                ? 'Continue'
                                                : 'View'}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!evaluations.data.length && (
                        <p className="p-8 text-center text-slate-500">
                            No Call Evaluations found.
                        </p>
                    )}
                </section>
                {evaluations.last_page > 1 && (
                    <div className="flex gap-2">
                        {evaluations.prev_page_url && (
                            <Link
                                className="rounded border px-3 py-2"
                                href={evaluations.prev_page_url}
                            >
                                Previous
                            </Link>
                        )}
                        <span className="rounded bg-red-700 px-3 py-2 text-white">
                            {evaluations.current_page}
                        </span>
                        {evaluations.next_page_url && (
                            <Link
                                className="rounded border px-3 py-2"
                                href={evaluations.next_page_url}
                            >
                                Next
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </main>
    );
}
function Filter({
    name,
    value,
    options = [],
    values = [],
    onChange,
}: {
    name: string;
    value?: string;
    options?: Ref[];
    values?: string[];
    onChange: (v: string) => void;
}) {
    return (
        <select
            aria-label={name}
            className="rounded-lg border bg-white px-3 py-2"
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
        >
            <option value="">All {name}s</option>
            {options.map((o) => (
                <option key={o.id} value={o.id}>
                    {o.name}
                </option>
            ))}
            {values.map((v) => (
                <option key={v} value={v}>
                    {v
                        .split('_')
                        .map((x) => x[0].toUpperCase() + x.slice(1))
                        .join(' ')}
                </option>
            ))}
        </select>
    );
}
Index.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'Call Evaluations', href: '/management/call-evaluations' },
    ],
};
