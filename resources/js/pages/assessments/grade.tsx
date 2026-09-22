import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
type Answer = {
    id: number;
    question: string;
    skill: string | null;
    maximum_points: number;
    response: string;
    points_awarded: number | null;
    grader_feedback: string | null;
};
export default function Grade({
    attempt,
}: {
    attempt: {
        id: number;
        employee: string;
        assessment: string;
        attempt_number: number;
        answers: Answer[];
    };
}) {
    const [grades, setGrades] = useState(
        attempt.answers.map((a) => ({
            answer_id: a.id,
            points_awarded: a.points_awarded ?? '',
            grader_feedback: a.grader_feedback ?? '',
        })),
    );
    const send = (finalize: boolean) =>
        router.put(`/management/assessment-reviews/${attempt.id}`, {
            grades,
            finalize,
        });

    return (
        <>
            <Head title="Manual Grading" />
            <main className="assessment-admin p-4 lg:p-6">
                <div className="mx-auto max-w-4xl">
                    <h1 className="text-2xl font-bold">
                        Grade {attempt.assessment}
                    </h1>
                    <p className="mt-2">
                        {attempt.employee} · Attempt {attempt.attempt_number}
                    </p>
                    <div className="mt-6 space-y-4">
                        {attempt.answers.map((a, i) => (
                            <article
                                key={a.id}
                                className="rounded-2xl border bg-white p-4"
                            >
                                <h2 className="font-bold">{a.question}</h2>
                                <p className="text-sm text-gray-500">
                                    {a.skill ?? 'No skill'} · Maximum{' '}
                                    {a.maximum_points} points
                                </p>
                                <div className="mt-3 rounded bg-gray-50 p-4 whitespace-pre-wrap">
                                    {a.response || 'No response'}
                                </div>
                                <label className="mt-4 block text-sm font-semibold">
                                    Points Awarded
                                    <input
                                        type="number"
                                        min="0"
                                        max={a.maximum_points}
                                        step="0.01"
                                        value={grades[i].points_awarded}
                                        onChange={(e) =>
                                            setGrades((g) =>
                                                g.map((v, n) =>
                                                    n === i
                                                        ? {
                                                              ...v,
                                                              points_awarded:
                                                                  e.target
                                                                      .value,
                                                          }
                                                        : v,
                                                ),
                                            )
                                        }
                                        className="mt-1 block w-full rounded border p-2"
                                    />
                                </label>
                                <label className="mt-3 block text-sm font-semibold">
                                    Grader Feedback
                                    <textarea
                                        value={grades[i].grader_feedback}
                                        onChange={(e) =>
                                            setGrades((g) =>
                                                g.map((v, n) =>
                                                    n === i
                                                        ? {
                                                              ...v,
                                                              grader_feedback:
                                                                  e.target
                                                                      .value,
                                                          }
                                                        : v,
                                                ),
                                            )
                                        }
                                        className="mt-1 block min-h-24 w-full rounded border p-2"
                                    />
                                </label>
                            </article>
                        ))}
                    </div>
                    <div className="mt-6 flex gap-3">
                        <button
                            onClick={() => send(false)}
                            className="rounded border px-5 py-2"
                        >
                            Save Grading Progress
                        </button>
                        <button
                            onClick={() => send(true)}
                            className="rounded bg-[#ad2924] px-5 py-2 font-semibold text-white"
                        >
                            Finalize Grading
                        </button>
                    </div>
                </div>
            </main>
        </>
    );
}
