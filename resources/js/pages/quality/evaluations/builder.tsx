import { Head, Link, router, useForm } from '@inertiajs/react';
import { DateTimeField } from '@/components/date-time-field';
import DocxPreview from '@/components/docx-preview';
import { useConfirmation } from '@/hooks/use-confirmation';
type Criterion = {
    key: string;
    label: string;
    guidance?: string;
    skill_name?: string;
    points_possible: number;
    is_required: boolean;
    is_critical: boolean;
    allows_na: boolean;
};
type Category = { name: string; description?: string; criteria: Criterion[] };
type Result = {
    criterion_snapshot_key: string;
    points_awarded?: string;
    is_na: boolean;
    comment?: string;
};
type Evaluation = {
    id: number;
    employee: { name: string; username: string };
    evaluator: { name: string };
    campaign_name: string;
    team_name: string;
    scorecard_name: string;
    scorecard_snapshot: { passing_score: number; categories: Category[] };
    call_at: string;
    call_direction: string;
    call_reference?: string;
    storage_key?: string;
    original_filename?: string;
    document_storage_key?: string;
    document_original_filename?: string;
    strengths?: string;
    areas_for_improvement?: string;
    overall_feedback?: string;
    recommended_action?: string;
    criterion_results: Result[];
};
type Review = {
    earned: number;
    possible: number;
    percentage?: number;
    critical: boolean;
    missing: string[];
    naCount: number;
    completed: number;
};
export default function Builder({
    evaluation,
    review,
}: {
    evaluation: Evaluation;
    review: Review;
}) {
    const confirmAction = useConfirmation();
    const existing = new Map(
        evaluation.criterion_results.map((r) => [r.criterion_snapshot_key, r]),
    );
    const definitions = evaluation.scorecard_snapshot.categories.flatMap(
        (c) => c.criteria,
    );
    const form = useForm({
        call_at: evaluation.call_at.slice(0, 16),
        call_direction: evaluation.call_direction,
        call_reference: evaluation.call_reference || '',
        strengths: evaluation.strengths || '',
        areas_for_improvement: evaluation.areas_for_improvement || '',
        overall_feedback: evaluation.overall_feedback || '',
        recommended_action: evaluation.recommended_action || '',
        recording: null as File | null,
        evaluation_document: null as File | null,
        criteria: definitions.map((c) => ({
            key: c.key,
            points_awarded: existing.get(c.key)?.points_awarded ?? '',
            is_na: existing.get(c.key)?.is_na ?? false,
            comment: existing.get(c.key)?.comment ?? '',
        })),
    });
    const update = (
        i: number,
        key: 'points_awarded' | 'is_na' | 'comment',
        value: string | boolean,
    ) =>
        form.setData(
            'criteria',
            form.data.criteria.map((x, n) =>
                n === i ? { ...x, [key]: value } : x,
            ),
        );
    const save = () =>
        form.put(`/management/call-evaluations/${evaluation.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-slate-900">
            <Head title={`Evaluate ${evaluation.employee.name}`} />
            <div className="mx-auto max-w-6xl space-y-5">
                <header className="flex justify-between gap-4">
                    <div>
                        <Link
                            className="font-bold text-red-700"
                            href="/management/call-evaluations"
                        >
                            ← Call Evaluations
                        </Link>
                        <h1 className="mt-2">{evaluation.employee.name}</h1>
                        <p>
                            {evaluation.campaign_name} · {evaluation.team_name}{' '}
                            · {evaluation.scorecard_name}
                        </p>
                    </div>
                    <span className="h-fit rounded-full bg-amber-100 px-4 py-2 font-bold text-amber-800">
                        Draft
                    </span>
                </header>
                <section className="grid gap-4 rounded-2xl border bg-white p-5 md:grid-cols-3">
                    <label>
                        Call Date
                        <DateTimeField
                            className="mt-1 w-full rounded border p-2"
                            type="datetime-local"
                            value={form.data.call_at}
                            onChange={(e) =>
                                form.setData('call_at', e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Direction
                        <select
                            className="mt-1 w-full rounded border p-2"
                            value={form.data.call_direction}
                            onChange={(e) =>
                                form.setData('call_direction', e.target.value)
                            }
                        >
                            <option value="inbound">Inbound</option>
                            <option value="outbound">Outbound</option>
                        </select>
                    </label>
                    <label>
                        Call Reference
                        <input
                            className="mt-1 w-full rounded border p-2"
                            value={form.data.call_reference}
                            onChange={(e) =>
                                form.setData('call_reference', e.target.value)
                            }
                        />
                    </label>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-bold">Call Recording</h2>
                    {evaluation.storage_key ? (
                        <>
                            <audio
                                className="mt-3 w-full"
                                controls
                                preload="metadata"
                                src={`/management/call-evaluations/${evaluation.id}/recording`}
                            />
                            <p className="mt-2 text-sm text-slate-500">
                                {evaluation.original_filename}
                            </p>
                        </>
                    ) : (
                        <p className="mt-2 text-sm text-slate-500">
                            No recording attached yet.
                        </p>
                    )}
                    <label className="mt-3 block text-sm font-semibold">
                        {evaluation.storage_key
                            ? 'Replace Recording'
                            : 'Attach Recording'}
                        <input
                            className="mt-1 block"
                            type="file"
                            accept=".mp3,.wav,.m4a,audio/*"
                            onChange={(e) =>
                                form.setData(
                                    'recording',
                                    e.target.files?.[0] || null,
                                )
                            }
                        />
                    </label>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-bold">Evaluation Document</h2>
                    {evaluation.document_storage_key ? (
                        <>
                            <a
                                className="mt-2 inline-block font-semibold text-red-700 underline"
                                href={`/management/call-evaluations/${evaluation.id}/document`}
                            >
                                Download {evaluation.document_original_filename}
                            </a>
                            <DocxPreview
                                url={`/management/call-evaluations/${evaluation.id}/document`}
                            />
                        </>
                    ) : (
                        <p className="mt-2 text-sm text-slate-500">
                            No DOCX evaluation attached yet.
                        </p>
                    )}
                    <label className="mt-3 block text-sm font-semibold">
                        {evaluation.document_storage_key
                            ? 'Replace DOCX Document'
                            : 'Attach DOCX Document'}
                        <input
                            className="mt-1 block"
                            type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            onChange={(e) =>
                                form.setData(
                                    'evaluation_document',
                                    e.target.files?.[0] || null,
                                )
                            }
                        />
                    </label>
                    <p className="mt-2 text-xs text-slate-500">
                        Stored as a supporting file only. It does not change
                        Call QA fields or scores.
                    </p>
                </section>
                {evaluation.scorecard_snapshot.categories.map((category) => (
                    <section
                        key={category.name}
                        className="rounded-2xl border bg-white p-5"
                    >
                        <h2 className="text-lg font-bold">{category.name}</h2>
                        {category.description && (
                            <p className="text-sm text-slate-500">
                                {category.description}
                            </p>
                        )}
                        <div className="mt-4 space-y-4">
                            {category.criteria.map((c) => {
                                const i = definitions.findIndex(
                                        (x) => x.key === c.key,
                                    ),
                                    a = form.data.criteria[i];

                                return (
                                    <div
                                        className="rounded-xl border p-4"
                                        key={c.key}
                                    >
                                        <div className="flex flex-wrap justify-between gap-2">
                                            <div>
                                                <b>{c.label}</b>
                                                {c.guidance && (
                                                    <p className="text-sm text-slate-500">
                                                        {c.guidance}
                                                    </p>
                                                )}
                                                <p className="mt-1 text-xs font-semibold">
                                                    {c.skill_name || 'General'}
                                                    {c.is_required
                                                        ? ' · Required'
                                                        : ''}
                                                    {c.is_critical
                                                        ? ' · Critical'
                                                        : ''}
                                                </p>
                                            </div>
                                            <b>{c.points_possible} points</b>
                                        </div>
                                        <div className="mt-3 grid gap-3 md:grid-cols-[160px_120px_1fr]">
                                            <label className="text-sm">
                                                Points Awarded
                                                <input
                                                    disabled={a.is_na}
                                                    className="mt-1 w-full rounded border p-2"
                                                    type="number"
                                                    min="0"
                                                    max={c.points_possible}
                                                    step="0.01"
                                                    value={a.points_awarded}
                                                    onChange={(e) =>
                                                        update(
                                                            i,
                                                            'points_awarded',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </label>
                                            {c.allows_na ? (
                                                <label className="flex items-center gap-2 pt-6">
                                                    <input
                                                        type="checkbox"
                                                        checked={a.is_na}
                                                        onChange={(e) =>
                                                            update(
                                                                i,
                                                                'is_na',
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                    Not Applicable
                                                </label>
                                            ) : (
                                                <div />
                                            )}
                                            <label className="text-sm">
                                                Comment / Evidence
                                                <textarea
                                                    className="mt-1 w-full rounded border p-2"
                                                    value={a.comment}
                                                    onChange={(e) =>
                                                        update(
                                                            i,
                                                            'comment',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </label>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                ))}
                <section className="grid gap-4 rounded-2xl border bg-white p-5 md:grid-cols-2">
                    {[
                        ['Strengths', 'strengths'],
                        ['Areas for Improvement', 'areas_for_improvement'],
                        ['Overall Feedback', 'overall_feedback'],
                        ['Recommended Action', 'recommended_action'],
                    ].map(([label, key]) => (
                        <label key={key} className="text-sm font-semibold">
                            {label}
                            <textarea
                                className="mt-1 min-h-24 w-full rounded border p-3"
                                value={String(
                                    form.data[key as keyof typeof form.data] ||
                                        '',
                                )}
                                onChange={(e) =>
                                    form.setData(
                                        key as 'strengths',
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                    ))}
                </section>
                <section className="sticky bottom-3 rounded-2xl border bg-white p-5 shadow-lg">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <b>
                                {review.completed} / {definitions.length}{' '}
                                completed
                            </b>
                            <p className="text-sm text-slate-500">
                                {review.missing.length} required missing ·{' '}
                                {review.naCount} N/A · Current score:{' '}
                                {review.percentage ?? 0}%
                                {review.critical ? ' · Critical Failure' : ''}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={save}
                                disabled={form.processing}
                                className="rounded-xl border border-red-700 px-5 py-3 font-bold text-red-700"
                            >
                                Save Draft
                            </button>
                            <button
                                disabled={review.missing.length > 0}
                                onClick={async () => {
                                    if (
                                        (await confirmAction(
                                            'Submit this Evaluation? Submitted evaluations cannot be edited.',
                                        ))
                                    ) {
                                        router.post(
                                            `/management/call-evaluations/${evaluation.id}/submit`,
                                        );
                                    }
                                }}
                                className="rounded-xl bg-green-700 px-5 py-3 font-bold text-white disabled:opacity-40"
                            >
                                Review & Submit
                            </button>
                        </div>
                    </div>
                    {Object.values(form.errors).map((e, i) => (
                        <p
                            className="mt-2 text-sm font-semibold text-red-700"
                            key={i}
                        >
                            {e}
                        </p>
                    ))}
                </section>
            </div>
        </main>
    );
}
Builder.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'Call Evaluations', href: '/management/call-evaluations' },
        { title: 'Evaluation Builder', href: '#' },
    ],
};
