import { Head, Link } from '@inertiajs/react';
type Row = {
    id: number;
    name: string;
    abbreviation: string;
    status: string;
    employees: number;
    assigned: number;
    completed: number;
    pending_review: number;
    overdue: number;
    completion_rate: number;
    average_score: number;
    pass_rate: number;
    teams: { id: number; name: string }[];
};
export default function CampaignAnalytics({ campaigns }: { campaigns: Row[] }) {
    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="Campaign Analytics" />
            <div className="mx-auto max-w-7xl space-y-5">
                <header>
                    <h1>Campaign Analytics</h1>
                    <p>
                        Historical performance uses stored Campaign snapshots.
                    </p>
                </header>
                <div className="grid gap-4 xl:grid-cols-2">
                    {campaigns.map((c) => (
                        <section
                            key={c.id}
                            className="rounded-2xl border bg-white p-5"
                        >
                            <div className="flex justify-between">
                                <div>
                                    <span className="rounded-full bg-red-50 px-3 py-1 font-bold text-red-700">
                                        {c.abbreviation}
                                    </span>
                                    <h2 className="mt-2">{c.name}</h2>
                                </div>
                                <b>{c.status}</b>
                            </div>
                            <div className="mt-4 grid grid-cols-3 gap-3">
                                {[
                                    ['Employees', c.employees],
                                    ['Assigned', c.assigned],
                                    ['Completed', c.completed],
                                    ['Completion', `${c.completion_rate}%`],
                                    ['Average', `${c.average_score}%`],
                                    ['Pass Rate', `${c.pass_rate}%`],
                                    ['Pending', c.pending_review],
                                    ['Overdue', c.overdue],
                                ].map(([k, v]) => (
                                    <div
                                        key={String(k)}
                                        className="rounded-xl bg-slate-50 p-3"
                                    >
                                        <span className="block text-slate-500">
                                            {k}
                                        </span>
                                        <b>{v}</b>
                                    </div>
                                ))}
                            </div>
                            <h3 className="mt-4">Teams</h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {c.teams.map((t) => (
                                    <Link
                                        className="rounded-lg border px-3 py-2 font-semibold text-red-700"
                                        key={t.id}
                                        href={`/management/assessment-teams/${t.id}/analytics`}
                                    >
                                        {t.name}
                                    </Link>
                                ))}
                                {!c.teams.length && (
                                    <span>
                                        No Teams belong to this Campaign yet.
                                    </span>
                                )}
                            </div>
                        </section>
                    ))}
                    {!campaigns.length && (
                        <div className="rounded-2xl border bg-white p-8">
                            No Campaigns found.
                        </div>
                    )}
                </div>
            </div>
        </main>
    );
}
CampaignAnalytics.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Campaign Analytics', href: '/management/campaign-analytics' },
    ],
};
