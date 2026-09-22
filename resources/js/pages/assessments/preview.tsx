import { Head, Link } from '@inertiajs/react';
type Material = {
    id: number;
    title: string;
    description?: string;
    type: string;
    content?: string;
    original_filename?: string;
    is_required: boolean;
    required_completion_percentage: number;
};
type Question = {
    id: number;
    question_text: string;
    question_type: string;
    points: string;
    skill?: { name: string };
    options: { id: number; option_text: string }[];
};
type Props = {
    assessment: {
        id: number;
        title: string;
        description?: string;
        instructions?: string;
        difficulty: string;
        passing_score: string;
        time_limit_minutes?: number;
        maximum_attempts: number;
        status: string;
        category?: { name: string };
        training_materials: Material[];
        questions: Question[];
    };
};
export default function Preview({ assessment }: Props) {
    return (
        <>
            <Head title={`Preview: ${assessment.title}`} />
            <main className="assessment-admin min-h-full bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-4xl space-y-5">
                    <Link
                        href={`/management/assessments/${assessment.id}/builder`}
                        className="font-semibold text-[#ad2924]"
                    >
                        Back to Builder
                    </Link>
                    <section className="rounded-2xl border bg-white p-5">
                        <span className="rounded-full bg-[#fff0ee] px-3 py-1 text-xs font-bold text-[#ad2924]">
                            PREVIEW MODE
                        </span>
                        <h1 className="mt-3 text-2xl font-bold">
                            {assessment.title}
                        </h1>
                        <p className="mt-3 whitespace-pre-wrap text-[#656979]">
                            {assessment.description}
                        </p>
                        <div className="mt-5 grid gap-2 rounded-2xl bg-[#f8f8fb] p-5 sm:grid-cols-4">
                            <span>
                                Category
                                <br />
                                <b>
                                    {assessment.category?.name ||
                                        'Uncategorized'}
                                </b>
                            </span>
                            <span>
                                Passing
                                <br />
                                <b>{assessment.passing_score}%</b>
                            </span>
                            <span>
                                Time limit
                                <br />
                                <b>
                                    {assessment.time_limit_minutes
                                        ? `${assessment.time_limit_minutes} min`
                                        : 'None'}
                                </b>
                            </span>
                            <span>
                                Attempts
                                <br />
                                <b>{assessment.maximum_attempts}</b>
                            </span>
                        </div>
                        <h2 className="mt-7 text-xl font-bold">Instructions</h2>
                        <p className="whitespace-pre-wrap">
                            {assessment.instructions ||
                                'No special instructions.'}
                        </p>
                        <h2 className="mt-7 text-xl font-bold">
                            Training Materials
                        </h2>
                        {assessment.training_materials.map((m) => (
                            <article
                                key={m.id}
                                className="mt-3 rounded-2xl border p-4"
                            >
                                <b>{m.title}</b>
                                <p className="text-sm text-[#777b8e] capitalize">
                                    {m.type} ·{' '}
                                    {m.is_required
                                        ? `Required (${m.required_completion_percentage}%)`
                                        : 'Optional'}
                                </p>
                                <p className="mt-2 whitespace-pre-wrap">
                                    {m.description}
                                </p>
                                {m.type === 'written' && (
                                    <div className="mt-3 rounded-xl bg-[#f8f8fb] p-4 whitespace-pre-wrap">
                                        {m.content}
                                    </div>
                                )}
                                {m.type === 'video' && (
                                    <video
                                        controls
                                        preload="metadata"
                                        className="mt-3 max-h-96 w-full"
                                        src={`/management/assessment-media/${m.id}`}
                                    />
                                )}{' '}
                                {m.type === 'audio' && (
                                    <audio
                                        controls
                                        preload="metadata"
                                        className="mt-3 w-full"
                                        src={`/management/assessment-media/${m.id}`}
                                    />
                                )}{' '}
                                {['image', 'document'].includes(m.type) && (
                                    <a
                                        className="mt-3 block font-semibold text-[#ad2924]"
                                        href={`/management/assessment-media/${m.id}`}
                                        target="_blank"
                                    >
                                        Open {m.original_filename}
                                    </a>
                                )}
                            </article>
                        ))}
                        <h2 className="mt-7 text-xl font-bold">Questions</h2>
                        {assessment.questions.map((q, i) => (
                            <article
                                key={q.id}
                                className="mt-3 rounded-2xl border p-4"
                            >
                                <b>
                                    {i + 1}. {q.question_text}
                                </b>
                                <p className="text-sm text-[#777b8e]">
                                    {q.skill?.name || 'No skill'} · {q.points}{' '}
                                    points
                                </p>
                                <div className="mt-2 space-y-1">
                                    {q.options.map((o) => (
                                        <div
                                            key={o.id}
                                            className="rounded-lg bg-[#f8f8fb] px-3 py-2"
                                        >
                                            {o.option_text}
                                        </div>
                                    ))}
                                    {q.question_type === 'short_answer' && (
                                        <div className="h-20 rounded-lg border border-dashed" />
                                    )}
                                </div>
                            </article>
                        ))}
                    </section>
                </div>
            </main>
        </>
    );
}
Preview.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Assessments', href: '/management/assessments' },
        { title: 'Preview', href: '#' },
    ],
};
