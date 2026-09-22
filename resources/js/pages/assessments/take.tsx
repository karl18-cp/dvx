import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

type Option = { id: number; text: string };
type Question = {
    id: number;
    text: string;
    type: string;
    points: number;
    required: boolean;
    options: Option[];
};
type Answer = { option_ids?: number[]; text?: string };

function cookie(name: string): string | null {
    const value = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(`${name}=`))
        ?.slice(name.length + 1);

    return value ? decodeURIComponent(value) : null;
}

export default function TakeAssessment({
    attempt,
}: {
    attempt: {
        id: number;
        title: string;
        attempt_number: number;
        expires_at: string | null;
        server_time: string;
        questions: Question[];
        answers: Record<string, Answer>;
        flagged_question_ids: number[];
    };
}) {
    const [answers, setAnswers] = useState<Record<string, Answer>>(
        attempt.answers,
    );
    const initialQuestion = Number(
        typeof window === 'undefined'
            ? 1
            : (new URLSearchParams(window.location.search).get('question') ??
                  1),
    );
    const [index, setIndex] = useState(
        Math.min(
            Math.max(Number.isFinite(initialQuestion) ? initialQuestion - 1 : 0, 0),
            attempt.questions.length - 1,
        ),
    );
    const [save, setSave] = useState('Saved');
    const [flags, setFlags] = useState<number[]>(attempt.flagged_question_ids);
    const [flagSave, setFlagSave] = useState('');
    const [remaining, setRemaining] = useState<number | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const expirationSubmitted = useRef(false);
    const pendingAnswer = useRef<{ question: Question; value: Answer } | null>(
        null,
    );
    const q = attempt.questions[index];
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
                router.post(`/assessments/attempts/${attempt.id}/submit`);
            }
        };
        tick();
        const id = setInterval(tick, 1000);

        return () => clearInterval(id);
    }, [attempt]);
    const persist = async (question: Question, value: Answer) => {
        setSave('Saving...');

        try {
            const csrfToken = cookie('XSRF-TOKEN');
            const response = await fetch(
                `/assessments/attempts/${attempt.id}/answer`,
                {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
                    },
                    body: JSON.stringify({
                        question_id: question.id,
                        answer: value,
                    }),
                },
            );
            const result = response.ok
                ? ((await response.json()) as { saved?: boolean })
                : null;

            setSave(result?.saved === true ? 'Saved' : 'Save Failed');

            if (result?.saved === true && pendingAnswer.current?.question.id === question.id) {
                pendingAnswer.current = null;
            }
        } catch {
            setSave('Save Failed');
        }
    };
    const change = (value: Answer, debounce = false) => {
        setAnswers((a) => ({ ...a, [q.id]: value }));

        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (debounce) {
            pendingAnswer.current = { question: q, value };
            timer.current = setTimeout(() => persist(q, value), 700);
        } else {
            persist(q, value);
        }
    };
    const answered = useMemo(
        () =>
            attempt.questions.filter((question) => {
                const a = answers[question.id];

                return !!(a?.text?.trim() || a?.option_ids?.length);
            }).length,
        [answers, attempt.questions],
    );
    const selected = answers[q.id]?.option_ids ?? [];
    const isAnswered = (question: Question) => {
        const answer = answers[question.id];

        return Boolean(answer?.text?.trim() || answer?.option_ids?.length);
    };
    const toggleFlag = async () => {
        const flagged = !flags.includes(q.id);
        const previous = flags;
        setFlags((current) =>
            flagged ? [...current, q.id] : current.filter((id) => id !== q.id),
        );
        setFlagSave('Saving flag...');

        try {
            const csrfToken = cookie('XSRF-TOKEN');
            const response = await fetch(
                `/assessments/attempts/${attempt.id}/flag`,
                {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
                    },
                    body: JSON.stringify({ question_id: q.id, flagged }),
                },
            );

            if (!response.ok) {
                throw new Error('Flag save failed');
            }

            setFlagSave('Flag saved');
        } catch {
            setFlags(previous);
            setFlagSave('Flag save failed');
        }
    };
    const openReview = async () => {
        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (pendingAnswer.current) {
            await persist(
                pendingAnswer.current.question,
                pendingAnswer.current.value,
            );
        }

        router.visit(`/assessments/attempts/${attempt.id}/review`);
    };

    return (
        <>
            <Head title={attempt.title} />
            <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-8">
                <div className="mx-auto max-w-5xl">
                    <header className="rounded-2xl border bg-white p-5">
                        <div className="flex flex-wrap justify-between gap-3">
                            <div>
                                <p className="text-sm text-[#77798a]">
                                    Attempt {attempt.attempt_number}
                                </p>
                                <h1 className="text-2xl font-bold">
                                    {attempt.title}
                                </h1>
                            </div>
                            <div className="text-right">
                                <p className="font-semibold">{save}</p>
                                {remaining !== null && (
                                    <p className="text-lg font-bold text-[#ad2924]">
                                        {Math.floor(remaining / 60)}:
                                        {String(remaining % 60).padStart(
                                            2,
                                            '0',
                                        )}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="mt-4 h-2 overflow-hidden rounded bg-gray-200">
                            <div
                                className="h-full bg-[#ad2924]"
                                style={{
                                    width: `${(answered / attempt.questions.length) * 100}%`,
                                }}
                            />
                        </div>
                    </header>
                    <div className="mt-5 grid gap-5 lg:grid-cols-[1fr_220px]">
                            <section className="rounded-2xl border bg-white p-6">
                                <p className="text-sm font-semibold text-[#ad2924]">
                                    Question {index + 1} of{' '}
                                    {attempt.questions.length}
                                </p>
                                <h2 className="mt-3 text-xl font-bold">
                                    {q.text}
                                </h2>
                                <div className="mt-3 flex items-center gap-3">
                                    <button
                                        onClick={toggleFlag}
                                        className={`rounded-lg border px-3 py-1.5 text-sm font-semibold ${flags.includes(q.id) ? 'border-blue-300 bg-blue-50 text-blue-800' : 'bg-white'}`}
                                    >
                                        {flags.includes(q.id)
                                            ? 'Remove Flag'
                                            : 'Flag for Review'}
                                    </button>
                                    {flagSave && (
                                        <span className="text-xs text-[#6f7282]">
                                            {flagSave}
                                        </span>
                                    )}
                                </div>
                                {q.type === 'multiple_selection' && (
                                    <p className="mt-1 text-sm text-[#6f7282]">
                                        Select all that apply
                                    </p>
                                )}
                                <div className="mt-6 space-y-3">
                                    {q.type === 'short_answer' ? (
                                        <textarea
                                            value={answers[q.id]?.text ?? ''}
                                            onChange={(e) =>
                                                change(
                                                    { text: e.target.value },
                                                    true,
                                                )
                                            }
                                            className="min-h-36 w-full rounded-xl border p-4"
                                            placeholder="Enter your answer"
                                        />
                                    ) : (
                                        q.options.map((o) => (
                                            <label
                                                key={o.id}
                                                className="flex cursor-pointer gap-3 rounded-xl border p-4"
                                            >
                                                <input
                                                    type={
                                                        q.type ===
                                                        'multiple_selection'
                                                            ? 'checkbox'
                                                            : 'radio'
                                                    }
                                                    name={`q-${q.id}`}
                                                    checked={selected.includes(
                                                        o.id,
                                                    )}
                                                    onChange={() => {
                                                        const ids =
                                                            q.type ===
                                                            'multiple_selection'
                                                                ? selected.includes(
                                                                      o.id,
                                                                  )
                                                                    ? selected.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              o.id,
                                                                      )
                                                                    : [
                                                                          ...selected,
                                                                          o.id,
                                                                      ]
                                                                : [o.id];
                                                        change({
                                                            option_ids: ids,
                                                        });
                                                    }}
                                                />
                                                <span>{o.text}</span>
                                            </label>
                                        ))
                                    )}
                                </div>
                                <div className="mt-7 flex justify-between">
                                    <button
                                        disabled={index === 0}
                                        onClick={() => setIndex((i) => i - 1)}
                                        className="rounded-lg border px-5 py-2 disabled:opacity-40"
                                    >
                                        Previous
                                    </button>
                                    {index < attempt.questions.length - 1 ? (
                                        <button
                                            onClick={() =>
                                                setIndex((i) => i + 1)
                                            }
                                            className="rounded-lg bg-[#29263b] px-5 py-2 text-white"
                                        >
                                            Next
                                        </button>
                                    ) : (
                                        <button
                                            onClick={openReview}
                                            className="rounded-lg bg-[#ad2924] px-5 py-2 font-semibold text-white"
                                        >
                                            Review Assessment
                                        </button>
                                    )}
                                </div>
                            </section>
                            <aside className="rounded-2xl border bg-white p-5">
                                <h3 className="font-bold">Questions</h3>
                                <p className="mt-1 text-sm text-[#6f7282]">
                                    {answered} answered ·{' '}
                                    {attempt.questions.length - answered}{' '}
                                    unanswered
                                </p>
                                <div className="mt-4 grid grid-cols-5 gap-2">
                                    {attempt.questions.map((question, i) => (
                                        <button
                                            key={question.id}
                                            onClick={() => setIndex(i)}
                                            className={`relative aspect-square rounded-lg text-sm font-bold ${i === index ? 'bg-[#ad2924] text-white' : flags.includes(question.id) ? 'border border-blue-300 bg-blue-50 text-blue-800' : isAnswered(question) ? 'bg-green-100 text-green-800' : 'bg-gray-100'}`}
                                        >
                                            {i + 1}
                                            {flags.includes(question.id) && (
                                                <span className="absolute -top-1 -right-1 size-2 rounded-full bg-blue-600" />
                                            )}
                                        </button>
                                    ))}
                                </div>
                                <button
                                    onClick={openReview}
                                    className="mt-5 w-full rounded-lg border border-[#ad2924] px-4 py-2 text-sm font-semibold text-[#ad2924]"
                                >
                                    Review Assessment
                                </button>
                            </aside>
                        </div>
                </div>
            </main>
        </>
    );
}
TakeAssessment.layout = {
    breadcrumbs: [
        { title: 'Training & Assessments', href: '/assessments' },
        { title: 'Assessment', href: '#' },
    ],
};
