import { Head, Link, router } from '@inertiajs/react';
import { Button, MenuItem, TextField } from '@mui/material';
import { DateTimeField } from '@/components/date-time-field';

type Ref = {
    id: number;
    name: string;
    username?: string;
    campaign_id?: number;
};
type Row = {
    employee_id: number;
    employee: Ref;
    campaign_id: number;
    campaign: string;
    team_id: number;
    team: string;
    evaluations: number;
    average_score: number;
    latest_score: number;
    passed: number;
    failed: number;
    open_coaching: number;
    coaching_status: string;
};
type Page = { data: Row[]; prev_page_url?: string; next_page_url?: string };

export default function QaDashboard({
    summary,
    performance,
    filters,
    campaigns,
    teams,
    employees,
}: {
    summary: Record<string, number>;
    performance: Page;
    filters: Record<string, string>;
    campaigns: Ref[];
    teams: Ref[];
    employees: Ref[];
}) {
    const apply = (key: string, value: string) =>
        router.get(
            '/management/qa-dashboard',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    const query = new URLSearchParams(filters).toString();

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="QA Dashboard" />
            <div className="mx-auto max-w-7xl space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1>QA Dashboard</h1>
                        <p>Simple quality and coaching overview.</p>
                    </div>
                    <a
                        className="rounded-lg border px-4 py-2 font-semibold"
                        href={`/management/qa-dashboard/export?${query}`}
                    >
                        Export CSV
                    </a>
                </header>
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {[
                        ['Calls Evaluated', summary.calls_evaluated],
                        ['Average QA Score', `${summary.average_score}%`],
                        ['Passed', summary.passed],
                        ['Failed', summary.failed],
                        ['Open Coaching', summary.open_coaching],
                    ].map(([label, value]) => (
                        <div
                            className="rounded-xl border bg-white p-4"
                            key={String(label)}
                        >
                            <p className="text-xs font-bold text-slate-500 uppercase">
                                {label}
                            </p>
                            <p className="mt-1 text-2xl font-bold">{value}</p>
                        </div>
                    ))}
                </section>
                <section className="grid gap-2 rounded-xl border bg-white p-3 md:grid-cols-4 lg:grid-cols-7">
                    <Select
                        label="Date Range"
                        value={filters.range}
                        values={[
                            ['today', 'Today'],
                            ['week', 'This Week'],
                            ['month', 'This Month'],
                            ['custom', 'Custom'],
                        ]}
                        onChange={(v) => apply('range', v)}
                    />
                    <Select
                        label="Campaign"
                        value={filters.campaign}
                        options={campaigns}
                        onChange={(v) => apply('campaign', v)}
                    />
                    <Select
                        label="Team"
                        value={filters.team}
                        options={teams}
                        onChange={(v) => apply('team', v)}
                    />
                    <Select
                        label="Employee"
                        value={filters.employee}
                        options={employees}
                        onChange={(v) => apply('employee', v)}
                    />
                    {filters.range === 'custom' && (
                        <>
                            <DateTimeField
                                size="small"
                                label="From"
                                type="date"
                                value={filters.from || ''}
                                onChange={(e) => apply('from', e.target.value)}
                                slotProps={{ inputLabel: { shrink: true } }}
                            />
                            <DateTimeField
                                size="small"
                                label="To"
                                type="date"
                                value={filters.to || ''}
                                onChange={(e) => apply('to', e.target.value)}
                                slotProps={{ inputLabel: { shrink: true } }}
                            />
                        </>
                    )}
                    <Button
                        variant="outlined"
                        onClick={() => router.get('/management/qa-dashboard')}
                    >
                        Clear Filters
                    </Button>
                </section>
                <section>
                    <h2 className="mb-3 text-xl font-bold">
                        Agent QA Performance
                    </h2>
                    <div className="overflow-x-auto rounded-xl border bg-white">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead>
                                <tr>
                                    {[
                                        'Employee',
                                        'Campaign',
                                        'Team',
                                        'Evaluations',
                                        'Average Score',
                                        'Latest Score',
                                        'Coaching Status',
                                        'Action',
                                    ].map((h) => (
                                        <th className="px-4 py-3" key={h}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {performance.data.map((row) => (
                                    <tr
                                        className="border-t"
                                        key={`${row.employee_id}-${row.campaign_id}-${row.team_id}`}
                                    >
                                        <td className="px-4 py-3 font-semibold">
                                            {row.employee?.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.campaign}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.team}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.evaluations}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.average_score}%
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.latest_score}%
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.coaching_status}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link
                                                className="font-semibold text-red-700"
                                                href={`/management/qa-dashboard/employees/${row.employee_id}?campaign=${row.campaign_id}&team=${row.team_id}`}
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!performance.data.length && (
                            <p className="p-8 text-center text-slate-500">
                                No submitted evaluations match these filters.
                            </p>
                        )}
                    </div>
                    <div className="mt-3 flex justify-end gap-2">
                        {performance.prev_page_url && (
                            <Button
                                onClick={() =>
                                    router.get(performance.prev_page_url!)
                                }
                            >
                                Previous
                            </Button>
                        )}
                        {performance.next_page_url && (
                            <Button
                                onClick={() =>
                                    router.get(performance.next_page_url!)
                                }
                            >
                                Next
                            </Button>
                        )}
                    </div>
                </section>
            </div>
        </main>
    );
}

function Select({
    label,
    value,
    options = [],
    values = [],
    onChange,
}: {
    label: string;
    value?: string;
    options?: Ref[];
    values?: [string, string][];
    onChange: (value: string) => void;
}) {
    return (
        <TextField
            select
            size="small"
            label={label}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
        >
            <MenuItem value="">All</MenuItem>
            {options.map((o) => (
                <MenuItem key={o.id} value={String(o.id)}>
                    {o.name}
                </MenuItem>
            ))}
            {values.map(([id, name]) => (
                <MenuItem key={id} value={id}>
                    {name}
                </MenuItem>
            ))}
        </TextField>
    );
}
QaDashboard.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-dashboard' },
        { title: 'QA Dashboard', href: '#' },
    ],
};
