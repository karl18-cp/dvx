import { Head } from '@inertiajs/react';
import { Nav, Empty } from './history';
type Summary = {
    completed: number;
    average_score: number;
    pass_rate: number;
    highest_score: number;
    pending_review: number;
};
export default function Progress({
    summary,
    skills,
    trend,
    rule,
}: {
    summary: Summary;
    skills: { name: string; percentage: number }[];
    trend: { assessment: string; date: string; percentage: number }[];
    rule: string;
}) {
    return (
        <>
            <Head title="My Progress" />
            <main className="min-h-full bg-[#f7f7fa] p-6 lg:p-8">
                <div className="mx-auto max-w-6xl">
                    <Nav />
                    <h1 className="mt-5 text-3xl font-bold">My Progress</h1>
                    <p className="mt-2 text-sm text-[#6f7282]">{rule}</p>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {Object.entries(summary).map(([k, v]) => (
                            <div
                                key={k}
                                className="rounded-2xl border bg-white p-5"
                            >
                                <p className="text-xs font-semibold text-[#77798a] uppercase">
                                    {k.replaceAll('_', ' ')}
                                </p>
                                <p className="mt-2 text-2xl font-bold">
                                    {k.includes('score') || k === 'pass_rate'
                                        ? `${v}%`
                                        : v}
                                </p>
                            </div>
                        ))}
                    </div>
                    <section className="mt-6 rounded-2xl border bg-white p-6">
                        <h2 className="text-xl font-bold">Skill Performance</h2>
                        {skills.length === 0 ? (
                            <Empty text="No skill data is available yet." />
                        ) : (
                            <div className="mt-4 space-y-4">
                                {skills.map((s) => (
                                    <div key={s.name}>
                                        <div className="flex justify-between">
                                            <span>{s.name}</span>
                                            <strong>{s.percentage}%</strong>
                                        </div>
                                        <div className="mt-1 h-2 rounded bg-gray-200">
                                            <div
                                                className="h-full rounded bg-[#ad2924]"
                                                style={{
                                                    width: `${s.percentage}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                    <section className="mt-6 rounded-2xl border bg-white p-6">
                        <h2 className="text-xl font-bold">Score Trend</h2>
                        {trend.length === 0 ? (
                            <Empty text="No finalized scores yet." />
                        ) : (
                            <div className="mt-4 grid gap-2">
                                {trend.map((t, i) => (
                                    <div
                                        key={i}
                                        className="flex justify-between rounded-lg bg-[#f7f7fa] p-3"
                                    >
                                        <span>
                                            {t.date} · {t.assessment}
                                        </span>
                                        <strong>{t.percentage}%</strong>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
