import { Head } from '@inertiajs/react';
import { Empty } from './history';
export default function TeamAnalytics({
    team,
    summary,
    skills,
}: {
    team: { name: string };
    summary: Record<string, number>;
    skills: { name: string; percentage: number }[];
}) {
    return (
        <>
            <Head title={`${team.name} Analytics`} />
            <main className="assessment-admin bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-6xl">
                    <h1 className="text-2xl font-bold">
                        {team.name} Analytics
                    </h1>
                    <div className="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
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
                    <section className="mt-5 rounded-2xl border bg-white p-4">
                        <h2 className="text-xl font-bold">Skill Averages</h2>
                        {skills.length === 0 ? (
                            <Empty text="No skill data is available yet." />
                        ) : (
                            <div className="mt-4 space-y-3">
                                {skills.map((s) => (
                                    <div
                                        key={s.name}
                                        className="flex justify-between rounded bg-gray-50 p-3"
                                    >
                                        <span>{s.name}</span>
                                        <strong>{s.percentage}%</strong>
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
