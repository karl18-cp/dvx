import { Head, Link } from '@inertiajs/react';
import DocxPreview from '@/components/docx-preview';
type Criterion = {
    key: string;
    label: string;
    guidance?: string;
    skill_name?: string;
    points_possible: number;
};
type Category = { name: string; criteria: Criterion[] };
type Result = {
    criterion_snapshot_key: string;
    points_awarded?: string;
    is_na: boolean;
    comment?: string;
};
export default function Show({
    evaluation,
    review,
}: {
    evaluation: any;
    review: any;
}) {
    const results = new Map<string, Result>(
        evaluation.criterion_results.map((r: Result) => [
            r.criterion_snapshot_key,
            r,
        ]),
    );

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-slate-900">
            <Head title="Call Evaluation Result" />
            <div className="mx-auto max-w-6xl space-y-5">
                <header>
                    <Link
                        className="font-bold text-red-700"
                        href="/management/call-evaluations"
                    >
                        ← Call Evaluations
                    </Link>
                    <h1 className="mt-2">{evaluation.employee.name}</h1>
                    <p>
                        {evaluation.campaign_name} · {evaluation.team_name} ·{' '}
                        {evaluation.scorecard_name}
                    </p>
                </header>
                <section className="grid gap-3 rounded-2xl border bg-white p-5 sm:grid-cols-4">
                    <Stat
                        label="Final Score"
                        value={`${evaluation.percentage}%`}
                    />
                    <Stat
                        label="Result"
                        value={
                            evaluation.result === 'failed_critical'
                                ? 'Failed — Critical'
                                : evaluation.result
                        }
                    />
                    <Stat
                        label="Points"
                        value={`${evaluation.points_earned} / ${evaluation.points_possible}`}
                    />
                    <Stat label="Evaluator" value={evaluation.evaluator.name} />
                </section>
                {evaluation.storage_key && (
                    <section className="rounded-2xl border bg-white p-5">
                        <h2>Recording</h2>
                        <audio
                            className="mt-3 w-full"
                            controls
                            preload="metadata"
                            src={`/management/call-evaluations/${evaluation.id}/recording`}
                        />
                    </section>
                )}
                {evaluation.document_storage_key && (
                    <section className="rounded-2xl border bg-white p-5">
                        <h2>Evaluation Document</h2>
                        <a
                            className="mt-3 inline-block rounded-xl border border-red-700 px-5 py-3 font-bold text-red-700"
                            href={`/management/call-evaluations/${evaluation.id}/document`}
                        >
                            Download {evaluation.document_original_filename}
                        </a>
                        <DocxPreview
                            url={`/management/call-evaluations/${evaluation.id}/document`}
                        />
                        <p className="mt-2 text-xs text-slate-500">
                            Supporting DOCX attachment; Call QA fields remain
                            unchanged.
                        </p>
                    </section>
                )}
                {evaluation.scorecard_snapshot.categories.map((c: Category) => (
                    <section
                        key={c.name}
                        className="rounded-2xl border bg-white p-5"
                    >
                        <div className="flex justify-between">
                            <h2>{c.name}</h2>
                            <b>
                                {review.categories.find(
                                    (x: any) => x.name === c.name,
                                )?.percentage ?? 'N/A'}
                                %
                            </b>
                        </div>
                        {c.criteria.map((q) => {
                            const r = results.get(q.key);

                            return (
                                <div
                                    key={q.key}
                                    className="mt-3 rounded-xl border p-4"
                                >
                                    <div className="flex justify-between">
                                        <b>{q.label}</b>
                                        <b>
                                            {r?.is_na
                                                ? 'N/A'
                                                : `${r?.points_awarded ?? 0} / ${q.points_possible}`}
                                        </b>
                                    </div>
                                    {r?.comment && (
                                        <p className="mt-2 text-sm">
                                            Evidence: {r.comment}
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </section>
                ))}
                <section className="grid gap-4 rounded-2xl border bg-white p-5 md:grid-cols-2">
                    <Stat
                        label="Strengths"
                        value={evaluation.strengths || 'None recorded'}
                    />
                    <Stat
                        label="Areas for Improvement"
                        value={
                            evaluation.areas_for_improvement || 'None recorded'
                        }
                    />
                    <Stat
                        label="Overall Feedback"
                        value={evaluation.overall_feedback || 'None recorded'}
                    />
                    <Stat
                        label="Recommended Action"
                        value={evaluation.recommended_action || 'None recorded'}
                    />
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2>Coaching</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {evaluation.coaching_record
                            ? evaluation.coaching_record.status.replaceAll('_', ' ')
                            : 'Not Created'}
                    </p>
                    <Link
                        className="mt-3 inline-block rounded-xl bg-red-700 px-5 py-3 font-bold text-white"
                        href={
                            evaluation.coaching_record
                                ? `/management/coaching/${evaluation.coaching_record.id}`
                                : `/management/coaching?employee=${evaluation.employee_id}&create=1&call_evaluation=${evaluation.id}`
                        }
                    >
                        {evaluation.coaching_record
                            ? 'View Coaching'
                            : 'Create Coaching'}
                    </Link>
                </section>
            </div>
        </main>
    );
}
function Stat({ label, value }: { label: string; value: any }) {
    return (
        <div>
            <p className="text-xs font-bold text-slate-500 uppercase">
                {label}
            </p>
            <p className="mt-1 font-bold capitalize">{value}</p>
        </div>
    );
}
Show.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'Call Evaluations', href: '/management/call-evaluations' },
        { title: 'Evaluation Result', href: '#' },
    ],
};
