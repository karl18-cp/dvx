import { Head, Link } from '@inertiajs/react';
import { AssessmentStatusBadge } from '@/components/assessment-status-badge';
import { Empty, Pages } from './history';
import { DateTimeField } from '@/components/date-time-field';
type Row = {
    id: number;
    employee: string;
    employee_code: string;
    employee_user_id: number;
    team: string | null;
    assessment_id: number;
    assessment: string;
    attempt_number: number;
    percentage: number | null;
    status: string;
    assigned_at: string;
    submitted_at: string | null;
    time_taken_seconds: number | null;
};
export default function ResultsAnalytics({
    rows,
    summary,
    assessments,
    teams,
    categories,
    skills,
    filters,
}: {
    rows: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    summary: Record<string, number>;
    assessments: { id: number; title: string }[];
    teams: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    skills: { id: number; name: string }[];
    filters: Record<string, string>;
}) {
    return (
        <>
            <Head title="Results & Analytics" />
            <main className="assessment-admin bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-7xl">
                    <h1 className="text-2xl font-bold">Results & Analytics</h1>
                    <form
                        method="get"
                        className="mt-5 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-3 lg:grid-cols-6"
                    >
                        <select
                            name="assessment"
                            defaultValue={filters.assessment ?? ''}
                            className="rounded border p-2"
                        >
                            <option value="">All assessments</option>
                            {assessments.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.title}
                                </option>
                            ))}
                        </select>
                        <select
                            name="category"
                            defaultValue={filters.category ?? ''}
                            className="rounded border p-2"
                        >
                            <option value="">All categories</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <select
                            name="skill"
                            defaultValue={filters.skill ?? ''}
                            className="rounded border p-2"
                        >
                            <option value="">All skills</option>
                            {skills.map((skill) => (
                                <option key={skill.id} value={skill.id}>
                                    {skill.name}
                                </option>
                            ))}
                        </select>
                        <select
                            name="team"
                            defaultValue={filters.team ?? ''}
                            className="rounded border p-2"
                        >
                            <option value="">All teams</option>
                            {teams.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </select>
                        <select
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className="rounded border p-2"
                        >
                            <option value="">All statuses</option>
                            {[
                                'in_progress',
                                'pending_review',
                                'passed',
                                'failed',
                            ].map((s) => (
                                <option key={s} value={s}>
                                    {s.replace('_', ' ')}
                                </option>
                            ))}
                        </select>
                        <DateTimeField
                            type="date"
                            name="from"
                            defaultValue={filters.from ?? ''}
                            className="rounded border p-2"
                        />
                        <DateTimeField
                            type="date"
                            name="to"
                            defaultValue={filters.to ?? ''}
                            className="rounded border p-2"
                        />
                        <button className="rounded bg-[#ad2924] px-4 py-2 font-semibold text-white">
                            Apply Filters
                        </button>
                        <a
                            href="/management/assessment-results"
                            className="rounded border px-4 py-2 text-center font-semibold"
                        >
                            Clear Filters
                        </a>
                        <a
                            href={`/management/assessment-results-export?${new URLSearchParams(filters).toString()}`}
                            className="rounded border border-[#ad2924] px-4 py-2 text-center font-semibold text-[#ad2924]"
                        >
                            Export CSV
                        </a>
                    </form>
                    {Object.entries(filters).some(([, value]) =>
                        Boolean(value),
                    ) && (
                        <div className="mt-3 flex flex-wrap gap-2 text-xs">
                            {Object.entries(filters)
                                .filter(([, value]) => Boolean(value))
                                .map(([key, value]) => (
                                    <span
                                        key={key}
                                        className="rounded-full bg-white px-3 py-1"
                                    >
                                        {key}: {value}
                                    </span>
                                ))}
                        </div>
                    )}
                    <div className="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {Object.entries(summary).map(([key, value]) => (
                            <div
                                key={key}
                                className="rounded-xl border bg-white p-4"
                            >
                                <p className="text-xs text-gray-500 uppercase">
                                    {key.replaceAll('_', ' ')}
                                </p>
                                <p className="mt-1 text-xl font-bold">
                                    {key.includes('rate') ||
                                    key.includes('score')
                                        ? `${value}%`
                                        : value}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-6 overflow-x-auto rounded-2xl border bg-white">
                        {rows.data.length === 0 ? (
                            <Empty text="No results match the selected filters." />
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-100">
                                    <tr>
                                        {[
                                            'Employee',
                                            'Team',
                                            'Assessment',
                                            'Attempt',
                                            'Score',
                                            'Status',
                                            'Submitted',
                                            'Action',
                                        ].map((h) => (
                                            <th key={h} className="p-4">
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.data.map((r) => (
                                        <tr key={r.id} className="border-t">
                                            <td className="p-4">
                                                {r.employee} ({r.employee_code})
                                            </td>
                                            <td className="p-4">
                                                {r.team ?? '—'}
                                            </td>
                                            <td className="p-4">
                                                <Link
                                                    className="text-[#ad2924]"
                                                    href={`/management/assessment-results/${r.assessment_id}/analytics`}
                                                >
                                                    {r.assessment}
                                                </Link>
                                            </td>
                                            <td className="p-4">
                                                {r.attempt_number}
                                            </td>
                                            <td className="p-4">
                                                {r.percentage === null
                                                    ? '—'
                                                    : `${r.percentage}%`}
                                            </td>
                                            <td className="p-4">
                                                <AssessmentStatusBadge
                                                    status={r.status}
                                                />
                                            </td>
                                            <td className="p-4">
                                                {r.submitted_at
                                                    ? new Date(
                                                          r.submitted_at,
                                                      ).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="p-4">
                                                <div className="flex flex-col gap-1">
                                                    <Link
                                                        className="text-[#ad2924]"
                                                        href={`/assessments/attempts/${r.id}/result`}
                                                    >
                                                        View Details
                                                    </Link>
                                                    <Link
                                                        className="text-[#ad2924]"
                                                        href={`/management/coaching?employee=${r.employee_user_id}&assessment=${r.assessment_id}&create=1`}
                                                    >
                                                        Create Coaching
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                    <Pages links={rows.links} />
                </div>
            </main>
        </>
    );
}

ResultsAnalytics.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        {
            title: 'Results & Analytics',
            href: '/management/assessment-results',
        },
    ],
};
