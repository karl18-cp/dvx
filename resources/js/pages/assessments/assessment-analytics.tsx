import { Head } from '@inertiajs/react';
import { Empty } from './history';
type Question = {
    question: string;
    type: string;
    skill: string | null;
    answered: number;
    correct: number;
    incorrect: number;
    unanswered: number;
    correct_percentage: number;
    incorrect_percentage: number;
    average_points: number | null;
    options: { text: string; count: number; percentage: number }[];
};
export default function AssessmentAnalytics({
    assessment,
    summary,
    questions,
}: {
    assessment: { title: string };
    summary: Record<string, number>;
    questions: Question[];
}) {
    return (
        <>
            <Head title={`${assessment.title} Analytics`} />
            <main className="assessment-admin bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-7xl">
                    <h1 className="text-2xl font-bold">
                        {assessment.title} Analytics
                    </h1>
                    <p className="mt-2 text-sm text-gray-500">
                        Employee outcomes use each employee’s best finalized
                        attempt.
                    </p>
                    <div className="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
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
                        <h2 className="text-xl font-bold">
                            Question Analytics
                        </h2>
                        {questions.length === 0 ? (
                            <Empty text="No question results are available yet." />
                        ) : (
                            <div className="mt-4 space-y-4">
                                {questions.map((q, i) => (
                                    <article
                                        key={i}
                                        className="rounded-xl border p-5"
                                    >
                                        <h3 className="font-bold">
                                            {i + 1}. {q.question}
                                        </h3>
                                        <p className="text-sm text-gray-500">
                                            {q.type.replace('_', ' ')} ·{' '}
                                            {q.skill ?? 'No skill'}
                                        </p>
                                        {q.type === 'short_answer' ? (
                                            <p className="mt-3">
                                                Responses: {q.answered} ·
                                                Average points:{' '}
                                                {q.average_points ?? 'Pending'}
                                            </p>
                                        ) : (
                                            <>
                                                <p className="mt-3">
                                                    Answered {q.answered} ·
                                                    Correct {q.correct} (
                                                    {q.correct_percentage}%) ·
                                                    Incorrect {q.incorrect} (
                                                    {q.incorrect_percentage}%) ·
                                                    Unanswered {q.unanswered}
                                                </p>
                                                {q.options.length > 0 && (
                                                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                        {q.options.map((o) => (
                                                            <div
                                                                key={o.text}
                                                                className="rounded bg-gray-50 p-2"
                                                            >
                                                                {o.text}:{' '}
                                                                {o.count} (
                                                                {o.percentage}%)
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
