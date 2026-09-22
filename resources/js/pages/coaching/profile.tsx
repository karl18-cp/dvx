import { Head, Link } from '@inertiajs/react';
import { formatCoachingDate } from '@/lib/coaching-dates';

type Skill = { id: number; name: string; percentage: number; status: string };
type Coaching = {
    id: number;
    coaching_date: string;
    type: string;
    status: string;
    coach: { name: string };
    skill?: { name: string };
    action_plan?: string;
};
type Assessment = {
    id: number;
    assessment: string;
    category?: string;
    date: string;
    score: number;
    status: string;
    attempt: number;
};
type Training = {
    title: string;
    type: string;
    skill?: string;
    assessment: string;
    completed_at: string;
};

export default function TrainingProfile({
    employee,
    summary,
    skills,
    recentAssessments,
    recentTraining,
    recentCoaching,
    qaEvaluations,
    qaSkills,
}: {
    employee: {
        id: number;
        name: string;
        username: string;
        team_membership?: {
            team?: {
                name: string;
                campaign?: { name: string; abbreviation: string };
            };
        };
    };
    summary: Record<string, number>;
    skills: Skill[];
    recentAssessments: Assessment[];
    recentTraining: Training[];
    recentCoaching: Coaching[];
    qaEvaluations: {
        id: number;
        scorecard_name: string;
        campaign_name: string;
        call_at: string;
        percentage: number;
        result: string;
        evaluator: { name: string };
    }[];
    qaSkills: { id: number; name: string; percentage: number }[];
}) {
    const label = (v: string) =>
        v
            .split('_')
            .map((w) => w[0].toUpperCase() + w.slice(1))
            .join(' ');

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title={`${employee.name} — Training Profile`} />
            <div className="mx-auto max-w-7xl space-y-5">
                <div>
                    <Link
                        className="font-semibold text-red-700"
                        href="/employees"
                    >
                        ← Back to Employees
                    </Link>
                    <h1 className="mt-3">{employee.name}</h1>
                    <p className="font-semibold">
                        Campaign:{' '}
                        {employee.team_membership?.team?.campaign?.name ||
                            'Unassigned Campaign'}
                    </p>
                    <p>
                        {employee.username} ·{' '}
                        {employee.team_membership?.team?.name || 'No team'} ·
                        Training & Performance
                    </p>
                </div>
                <section className="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    {[
                        [
                            'Assessments Completed',
                            summary.assessments_completed,
                        ],
                        ['Average Score', `${summary.average_score}%`],
                        ['Pass Rate', `${summary.pass_rate}%`],
                        ['Training Completed', summary.training_completed],
                        ['Coaching Sessions', summary.coaching_sessions],
                        ['Pending Actions', summary.pending_actions],
                    ].map(([name, value]) => (
                        <div
                            key={String(name)}
                            className="rounded-xl border bg-white p-4"
                        >
                            <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">
                                {name}
                            </p>
                            <p className="mt-1 text-xl font-bold">{value}</p>
                        </div>
                    ))}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-lg font-bold">
                        QA Evaluation Skill Performance
                    </h2>
                    <p className="mb-3 text-sm text-slate-500">
                        Reported separately from Assessment Skill Performance.
                    </p>
                    <div className="grid gap-3 md:grid-cols-2">
                        {qaSkills.map((skill) => (
                            <div
                                className="rounded-xl border p-4"
                                key={skill.id}
                            >
                                <div className="flex justify-between">
                                    <b>{skill.name}</b>
                                    <strong>{skill.percentage}%</strong>
                                </div>
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full bg-red-700"
                                        style={{
                                            width: `${Math.min(100, skill.percentage)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                    {!qaSkills.length && (
                        <p className="text-sm text-slate-500">
                            No submitted QA skill results yet.
                        </p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="mb-3 text-lg font-bold">
                        Recent QA Evaluations
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[750px] text-left text-sm">
                            <thead>
                                <tr>
                                    {[
                                        'Date',
                                        'Scorecard',
                                        'Campaign',
                                        'Score',
                                        'Result',
                                        'Evaluator',
                                        'Action',
                                    ].map((h) => (
                                        <th className="px-3 py-2" key={h}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {qaEvaluations.map((q) => (
                                    <tr className="border-t" key={q.id}>
                                        <td className="px-3 py-3">
                                            {new Date(
                                                q.call_at,
                                            ).toLocaleDateString()}
                                        </td>
                                        <td className="px-3 py-3 font-semibold">
                                            {q.scorecard_name}
                                        </td>
                                        <td className="px-3 py-3">
                                            {q.campaign_name}
                                        </td>
                                        <td className="px-3 py-3">
                                            {q.percentage}%
                                        </td>
                                        <td className="px-3 py-3">
                                            {label(q.result)}
                                        </td>
                                        <td className="px-3 py-3">
                                            {q.evaluator.name}
                                        </td>
                                        <td className="px-3 py-3">
                                            <Link
                                                className="font-semibold text-red-700"
                                                href={`/management/call-evaluations/${q.id}`}
                                            >
                                                View
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!qaEvaluations.length && (
                        <p className="text-sm text-slate-500">
                            No submitted QA Evaluations yet.
                        </p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-bold">
                                Skill Performance
                            </h2>
                            <p className="text-sm text-slate-500">
                                Best finalized attempt per assessment.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        {skills.map((skill) => (
                            <div
                                className="rounded-xl border p-4"
                                key={skill.id}
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <b>{skill.name}</b>
                                        <p className="text-sm text-slate-500">
                                            {skill.status}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <strong className="text-lg">
                                            {skill.percentage}%
                                        </strong>
                                        <br />
                                        <Link
                                            className="text-sm font-semibold text-red-700"
                                            href={`/management/coaching?employee=${employee.id}&skill=${skill.id}&create=1`}
                                        >
                                            Create Coaching
                                        </Link>
                                    </div>
                                </div>
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-red-700"
                                        style={{
                                            width: `${Math.min(100, skill.percentage)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                    {!skills.length && (
                        <p className="text-sm text-slate-500">
                            No finalized skill results yet.
                        </p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="mb-3 text-lg font-bold">
                        Recent Assessments
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[750px] text-left text-sm">
                            <thead>
                                <tr>
                                    {[
                                        'Assessment',
                                        'Category',
                                        'Date',
                                        'Score',
                                        'Status',
                                        'Attempt',
                                        'Action',
                                    ].map((h) => (
                                        <th className="px-3 py-2" key={h}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {recentAssessments.map((a) => (
                                    <tr className="border-t" key={a.id}>
                                        <td className="px-3 py-3 font-semibold">
                                            {a.assessment}
                                        </td>
                                        <td className="px-3 py-3">
                                            {a.category || 'None'}
                                        </td>
                                        <td className="px-3 py-3">
                                            {new Date(
                                                a.date,
                                            ).toLocaleDateString()}
                                        </td>
                                        <td className="px-3 py-3">
                                            {a.score}%
                                        </td>
                                        <td className="px-3 py-3">
                                            {label(a.status)}
                                        </td>
                                        <td className="px-3 py-3">
                                            {a.attempt}
                                        </td>
                                        <td className="px-3 py-3">
                                            <Link
                                                className="font-semibold text-red-700"
                                                href={`/assessments/attempts/${a.id}/result`}
                                            >
                                                View Result
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <div className="grid gap-5 xl:grid-cols-2">
                    <section className="rounded-2xl border bg-white p-5">
                        <h2 className="mb-3 text-lg font-bold">
                            Recent Training
                        </h2>
                        <div className="space-y-2">
                            {recentTraining.map((t, i) => (
                                <div
                                    key={`${t.title}-${i}`}
                                    className="rounded-xl border p-3"
                                >
                                    <b>{t.title}</b>
                                    <p className="text-sm text-slate-500 capitalize">
                                        {t.type} · {t.skill || 'General'} ·{' '}
                                        {t.assessment}
                                    </p>
                                    <p className="text-xs text-green-700">
                                        Completed{' '}
                                        {new Date(
                                            t.completed_at,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                            ))}
                            {!recentTraining.length && (
                                <p className="text-sm text-slate-500">
                                    No completed training yet.
                                </p>
                            )}
                        </div>
                    </section>
                    <section className="rounded-2xl border bg-white p-5">
                        <h2 className="mb-3 text-lg font-bold">
                            Recent Coaching
                        </h2>
                        <div className="space-y-2">
                            {recentCoaching.map((c) => (
                                <Link
                                    href={`/management/coaching/${c.id}`}
                                    key={c.id}
                                    className="block rounded-xl border p-3 transition hover:border-red-300"
                                >
                                    <div className="flex justify-between gap-2">
                                        <b>{c.type}</b>
                                        <span className="text-xs font-bold">
                                            {label(c.status)}
                                        </span>
                                    </div>
                                    <p className="text-sm text-slate-500">
                                        {c.skill?.name || 'General'} ·{' '}
                                        {c.coach.name} ·{' '}
                                        {formatCoachingDate(c.coaching_date)}
                                    </p>
                                </Link>
                            ))}
                            {!recentCoaching.length && (
                                <p className="text-sm text-slate-500">
                                    No coaching history yet.
                                </p>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </main>
    );
}
TrainingProfile.layout = {
    breadcrumbs: [
        { title: 'Employees', href: '/employees' },
        { title: 'Training & Performance', href: '#' },
    ],
};
