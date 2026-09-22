import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type ReviewQuestion = {
    id: number;
    number: number;
    preview: string;
    answered: boolean;
};

type ReviewAttempt = {
    id: number;
    title: string;
    attempt_number: number;
    expires_at: string | null;
    server_time: string;
    questions: ReviewQuestion[];
    answered_count: number;
    unanswered_count: number;
};

export default function AssessmentReview({
    attempt,
}: {
    attempt: ReviewAttempt;
}) {
    const [remaining, setRemaining] = useState<number | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const expirationSubmitted = useRef(false);

    useEffect(() => {
        if (!attempt.expires_at) {
            return;
        }

        const offset = new Date(attempt.server_time).getTime() - Date.now();
        const tick = () => {
            const seconds = Math.max(
                0,
                Math.floor(
                    (new Date(attempt.expires_at!).getTime() -
                        (Date.now() + offset)) /
                        1000,
                ),
            );
            setRemaining(seconds);

            if (seconds === 0 && !expirationSubmitted.current) {
                expirationSubmitted.current = true;
                setSubmitting(true);
                router.post(`/assessments/attempts/${attempt.id}/submit`);
            }
        };
        tick();
        const interval = setInterval(tick, 1000);

        return () => clearInterval(interval);
    }, [attempt]);

    const submit = () => {
        if (submitting) {
            return;
        }

        setSubmitting(true);
        router.post(`/assessments/attempts/${attempt.id}/submit`, undefined, {
            onError: () => setSubmitting(false),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <>
            <Head title={`Review ${attempt.title}`} />
            <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-8">
                <div className="mx-auto max-w-5xl">
                    <header className="rounded-2xl border bg-white p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold text-[#ad2924]">
                                    Final Review · Attempt{' '}
                                    {attempt.attempt_number}
                                </p>
                                <h1 className="mt-1 text-2xl font-bold">
                                    {attempt.title}
                                </h1>
                            </div>
                            {remaining !== null && (
                                <div className="text-right">
                                    <p className="text-xs font-semibold text-[#77798a] uppercase">
                                        Remaining time
                                    </p>
                                    <p className="text-lg font-bold text-[#ad2924]">
                                        {Math.floor(remaining / 60)}:
                                        {String(remaining % 60).padStart(2, '0')}
                                    </p>
                                </div>
                            )}
                        </div>
                    </header>

                    <section className="mt-5 rounded-2xl border bg-white p-5 lg:p-6">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <ReviewStat
                                label="Questions"
                                value={attempt.questions.length}
                            />
                            <ReviewStat
                                label="Answered"
                                value={attempt.answered_count}
                                tone="complete"
                            />
                            <ReviewStat
                                label="Unanswered"
                                value={attempt.unanswered_count}
                                tone="warning"
                            />
                        </div>

                        {attempt.unanswered_count > 0 && (
                            <p className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                                You still have {attempt.unanswered_count}{' '}
                                unanswered question
                                {attempt.unanswered_count === 1 ? '' : 's'}. You
                                may return to them before submitting.
                            </p>
                        )}

                        <div className="mt-5 divide-y overflow-hidden rounded-xl border">
                            {attempt.questions.map((question) => (
                                <div
                                    key={question.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-3">
                                            <p className="font-semibold">
                                                Question {question.number}
                                            </p>
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-bold ${question.answered ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'}`}
                                            >
                                                {question.answered
                                                    ? 'Answered'
                                                    : 'Unanswered'}
                                            </span>
                                        </div>
                                        <p className="mt-1 truncate text-sm text-[#6f7282] sm:max-w-xl">
                                            {question.preview}
                                        </p>
                                    </div>
                                    <button
                                        onClick={() =>
                                            router.visit(
                                                `/assessments/attempts/${attempt.id}?question=${question.number}`,
                                            )
                                        }
                                        className="shrink-0 rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-gray-50"
                                    >
                                        Review Question
                                    </button>
                                </div>
                            ))}
                        </div>

                        <div className="mt-6 flex flex-col-reverse justify-between gap-3 sm:flex-row">
                            <button
                                onClick={() =>
                                    router.visit(
                                        `/assessments/attempts/${attempt.id}`,
                                    )
                                }
                                className="rounded-lg border px-5 py-2.5 font-semibold"
                            >
                                Return to Assessment
                            </button>
                            <button
                                disabled={submitting}
                                onClick={() => setConfirming(true)}
                                className="rounded-lg bg-[#ad2924] px-5 py-2.5 font-semibold text-white disabled:opacity-60"
                            >
                                {submitting
                                    ? 'Submitting...'
                                    : 'Submit Assessment'}
                            </button>
                        </div>
                    </section>
                </div>
            </main>

            {confirming && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4"
                    role="presentation"
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget && !submitting) {
                            setConfirming(false);
                        }
                    }}
                >
                    <section
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="submit-title"
                        className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
                    >
                        <h2 id="submit-title" className="text-xl font-bold">
                            Submit assessment?
                        </h2>
                        <p className="mt-3 text-sm leading-6 text-[#6f7282]">
                            Once submitted, you will no longer be able to change
                            your answers.
                        </p>
                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                disabled={submitting}
                                onClick={() => setConfirming(false)}
                                className="rounded-lg border px-4 py-2 font-semibold disabled:opacity-60"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={submitting}
                                onClick={submit}
                                className="rounded-lg bg-[#ad2924] px-4 py-2 font-semibold text-white disabled:opacity-60"
                            >
                                {submitting
                                    ? 'Submitting...'
                                    : 'Submit Assessment'}
                            </button>
                        </div>
                    </section>
                </div>
            )}
        </>
    );
}

function ReviewStat({
    label,
    value,
    tone = 'neutral',
}: {
    label: string;
    value: number;
    tone?: 'neutral' | 'complete' | 'warning';
}) {
    const toneClass =
        tone === 'complete'
            ? 'bg-green-50 text-green-900'
            : tone === 'warning'
              ? 'bg-amber-50 text-amber-900'
              : 'bg-[#f7f7fa]';

    return (
        <div className={`rounded-xl p-4 ${toneClass}`}>
            <p className="text-xs font-semibold uppercase opacity-70">{label}</p>
            <p className="mt-1 text-2xl font-bold">{value}</p>
        </div>
    );
}

AssessmentReview.layout = {
    breadcrumbs: [
        { title: 'Training & Assessments', href: '/assessments' },
        { title: 'Final Review', href: '#' },
    ],
};
