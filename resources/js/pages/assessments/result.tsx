import { Head, Link } from '@inertiajs/react';

type Result = {
    title: string;
    attempt_number: number;
    score: number | null;
    total: number;
    percentage: number;
    passing_score: number;
    status: string;
    correct_count: number;
    incorrect_count: number;
    unanswered_count: number;
    time_taken_seconds: number | null;
    submitted_at: string | null;
    show_correct_answers: boolean;
    skills: {
        name: string;
        earned: number;
        possible: number;
        percentage: number | null;
    }[];
    questions: {
        id: number;
        text: string;
        answer: { option_ids?: number[]; text?: string } | null;
        is_correct: boolean | null;
        correct_option_ids?: number[];
        options?: { id: number; text: string }[];
        feedback?: string | null;
        grader_feedback?: string | null;
    }[];
};
export default function AssessmentResult({ result }: { result: Result }) {
    return (
        <>
            <Head title={`Results - ${result.title}`} />
            <main className="min-h-full bg-[#f7f7fa] p-6 lg:p-8">
                <div className="mx-auto max-w-5xl">
                    <Link
                        href="/assessments"
                        className="text-sm text-[#ad2924]"
                    >
                        ← My Assessments
                    </Link>
                    <section className="mt-4 rounded-2xl border border-t-4 border-t-[#ad2924] bg-white p-7">
                        {result.status === 'pending_review' && (
                            <div className="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-5">
                                <h2 className="text-xl font-bold text-blue-950">
                                    Assessment Submitted Successfully
                                </h2>
                                <p className="mt-2 text-sm text-blue-900">
                                    Some of your responses require manual
                                    review. Your final score and Pass/Fail
                                    result will be available after grading is
                                    completed.
                                </p>
                            </div>
                        )}
                        <p className="text-sm text-[#6f7282]">
                            Attempt {result.attempt_number}
                        </p>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <h1 className="text-3xl font-bold">
                                {result.title}
                            </h1>
                            <span className="rounded-full bg-[#f7e9e8] px-4 py-2 font-bold capitalize">
                                {result.status.replace('_', ' ')}
                            </span>
                        </div>
                        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {result.status !== 'pending_review' && (
                                <>
                                    <Stat
                                        label="Score"
                                        value={`${result.score}/${result.total}`}
                                    />
                                    <Stat
                                        label="Percentage"
                                        value={`${result.percentage}%`}
                                    />
                                    <Stat
                                        label="Passing score"
                                        value={`${result.passing_score}%`}
                                    />
                                </>
                            )}
                            {result.status === 'pending_review' && (
                                <Stat
                                    label="Manual Review Status"
                                    value="Pending Review"
                                />
                            )}
                            <Stat
                                label="Time taken"
                                value={
                                    result.time_taken_seconds === null
                                        ? '—'
                                        : `${Math.floor(result.time_taken_seconds / 60)}m ${result.time_taken_seconds % 60}s`
                                }
                            />
                            <Stat
                                label="Correct"
                                value={String(result.correct_count)}
                            />
                            <Stat
                                label="Incorrect"
                                value={String(result.incorrect_count)}
                            />
                            <Stat
                                label="Unanswered"
                                value={String(result.unanswered_count)}
                            />
                            <Stat
                                label="Completed"
                                value={
                                    result.submitted_at
                                        ? new Date(
                                              result.submitted_at,
                                          ).toLocaleString()
                                        : '—'
                                }
                            />
                        </div>
                        {result.status === 'pending_review' && (
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Link
                                    href="/assessments/history"
                                    className="rounded-lg bg-[#29263b] px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Assessment History
                                </Link>
                                <Link
                                    href="/assessments"
                                    className="rounded-lg border px-4 py-2 text-sm font-semibold"
                                >
                                    Return to My Assessments
                                </Link>
                            </div>
                        )}
                    </section>
                    {result.status !== 'pending_review' &&
                        result.skills.length > 0 && (
                            <section className="mt-5 rounded-2xl border bg-white p-6">
                                <h2 className="text-xl font-bold">
                                    Skill Results
                                </h2>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    {result.skills.map((s) => (
                                        <div
                                            key={s.name}
                                            className="rounded-xl bg-[#f7f7fa] p-4"
                                        >
                                            <div className="flex justify-between">
                                                <strong>{s.name}</strong>
                                                <span>
                                                    {s.earned}/{s.possible} ·{' '}
                                                    {s.percentage}%
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    {result.questions.some((q) => q.grader_feedback) && (
                        <section className="mt-5 rounded-2xl border bg-white p-6">
                            <h2 className="text-xl font-bold">
                                Grader Feedback
                            </h2>
                            <div className="mt-4 space-y-3">
                                {result.questions
                                    .filter((q) => q.grader_feedback)
                                    .map((q) => (
                                        <div
                                            key={q.id}
                                            className="rounded-xl bg-[#f7f7fa] p-4"
                                        >
                                            <p className="font-semibold">
                                                {q.text}
                                            </p>
                                            <p className="mt-1 text-sm text-[#6f7282]">
                                                {q.grader_feedback}
                                            </p>
                                        </div>
                                    ))}
                            </div>
                        </section>
                    )}
                    {result.show_correct_answers && (
                        <section className="mt-5 rounded-2xl border bg-white p-6">
                            <h2 className="text-xl font-bold">Answer Review</h2>
                            <div className="mt-4 space-y-4">
                                {result.questions.map((q, i) => (
                                    <article
                                        key={q.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <p className="font-bold">
                                            {i + 1}. {q.text}
                                        </p>
                                        <p className="mt-2 text-sm">
                                            Your answer:{' '}
                                            {q.answer?.text ||
                                                q.options
                                                    ?.filter((o) =>
                                                        q.answer?.option_ids?.includes(
                                                            o.id,
                                                        ),
                                                    )
                                                    .map((o) => o.text)
                                                    .join(', ') ||
                                                'Unanswered'}
                                        </p>
                                        <p className="text-sm">
                                            Correct answer:{' '}
                                            {q.options
                                                ?.filter((o) =>
                                                    q.correct_option_ids?.includes(
                                                        o.id,
                                                    ),
                                                )
                                                .map((o) => o.text)
                                                .join(', ') || 'Manual review'}
                                        </p>
                                        {q.feedback && (
                                            <p className="mt-2 text-sm text-[#6f7282]">
                                                {q.feedback}
                                            </p>
                                        )}
                                    </article>
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}
function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl bg-[#f7f7fa] p-4">
            <p className="text-xs font-semibold text-[#77798a] uppercase">
                {label}
            </p>
            <p className="mt-1 text-lg font-bold">{value}</p>
        </div>
    );
}
AssessmentResult.layout = {
    breadcrumbs: [
        { title: 'Training & Assessments', href: '/assessments' },
        { title: 'Results', href: '#' },
    ],
};
