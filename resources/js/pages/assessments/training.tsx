import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Material = {
    id: number | string;
    title: string;
    description: string | null;
    type: string;
    content: string | null;
    required: boolean;
    threshold: number;
    duration_seconds: number | null;
    opened: boolean;
    completed: boolean;
    media_url: string | null;
};
export default function Training({
    assignment,
    materials,
}: {
    assignment: { id: number; title: string };
    materials: Material[];
}) {
    const [opened, setOpened] = useState<Record<string, boolean>>(
        Object.fromEntries(materials.map((m) => [m.id, m.opened])),
    );
    const [completed, setCompleted] = useState<Record<string, boolean>>(
        Object.fromEntries(materials.map((m) => [m.id, m.completed])),
    );
    const [processing, setProcessing] = useState<string | null>(null);
    const [error, setError] = useState('');
    const required = materials.filter((material) => material.required);
    const requiredCompleted = required.filter(
        (material) => completed[material.id],
    ).length;
    const requiredTrainingComplete = requiredCompleted === required.length;
    const post = async (
        url: string,
        data: Record<string, number> = {},
        done?: () => void,
    ) => {
        setError('');
        const materialId = url;
        setProcessing(materialId);

        try {
            const token = document.cookie
                .split('; ')
                .find((entry) => entry.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token
                        ? { 'X-XSRF-TOKEN': decodeURIComponent(token) }
                        : {}),
                },
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            done?.();
        } catch {
            setError('Training progress could not be saved. Please retry.');
        } finally {
            setProcessing(null);
        }
    };

    return (
        <>
            <Head title={`Training - ${assignment.title}`} />
            <main className="min-h-full bg-[#f7f7fa] p-6 lg:p-8">
                <div className="mx-auto max-w-4xl">
                    <Link
                        href="/assessments"
                        className="text-sm text-[#ad2924]"
                    >
                        ← My Assessments
                    </Link>
                    <h1 className="mt-3 text-3xl font-bold">
                        {assignment.title}
                    </h1>
                    <p className="mt-2 text-[#6f7282]">
                        Required materials must be completed before starting the
                        assessment.
                    </p>
                    {required.length > 0 && (
                        <div className="mt-4 rounded-xl border bg-white p-4">
                            <div className="flex justify-between text-sm">
                                <strong>Required Training</strong>
                                <span>
                                    {requiredCompleted === required.length
                                        ? 'Training Complete'
                                        : `${requiredCompleted} of ${required.length} Complete`}
                                </span>
                            </div>
                            <div className="mt-2 h-2 rounded bg-gray-200">
                                <div
                                    className="h-full rounded bg-[#ad2924]"
                                    style={{
                                        width: `${(requiredCompleted / required.length) * 100}%`,
                                    }}
                                />
                            </div>
                        </div>
                    )}
                    {error && (
                        <div className="mt-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-800">
                            {error}
                        </div>
                    )}
                    <div className="mt-6 space-y-4">
                        {materials.map((m) => (
                            <article
                                key={m.id}
                                className="rounded-2xl border bg-white p-6"
                            >
                                <div className="flex justify-between gap-4">
                                    <div>
                                        <h2 className="text-lg font-bold">
                                            {m.title}
                                        </h2>
                                        <p className="text-sm text-[#6f7282]">
                                            {m.type} ·{' '}
                                            {m.required
                                                ? 'Required'
                                                : 'Optional'}
                                        </p>
                                    </div>
                                    <span className="text-sm font-semibold">
                                        {completed[m.id]
                                            ? 'Completed'
                                            : 'Not completed'}
                                    </span>
                                </div>
                                {m.description && (
                                    <p className="mt-3 text-sm">
                                        {m.description}
                                    </p>
                                )}
                                {opened[m.id] && m.type === 'written' && (
                                    <div className="mt-4 rounded-xl bg-[#f7f7fa] p-5 whitespace-pre-wrap">
                                        {m.content}
                                    </div>
                                )}
                                {opened[m.id] &&
                                    m.media_url &&
                                    (m.type === 'video' ? (
                                        <video
                                            className="mt-4 w-full rounded-xl"
                                            controls
                                            src={m.media_url}
                                        />
                                    ) : m.type === 'audio' ? (
                                        <audio
                                            className="mt-4 w-full"
                                            controls
                                            src={m.media_url}
                                        />
                                    ) : (
                                        <a
                                            className="mt-4 block text-[#ad2924] underline"
                                            href={m.media_url}
                                            target="_blank"
                                        >
                                            Open material
                                        </a>
                                    ))}
                                <div className="mt-4 flex gap-2">
                                    {!opened[m.id] && (
                                        <button
                                            disabled={processing !== null}
                                            className="rounded-lg border px-4 py-2 text-sm font-semibold"
                                            onClick={() =>
                                                post(
                                                    typeof m.id === 'string' &&
                                                        m.id.startsWith(
                                                            'library-',
                                                        )
                                                        ? `/assessments/assignments/${assignment.id}/library-attachments/${m.id.replace('library-', '')}/open`
                                                        : `/assessments/assignments/${assignment.id}/materials/${m.id}/open`,
                                                    {},
                                                    () =>
                                                        setOpened((v) => ({
                                                            ...v,
                                                            [m.id]: true,
                                                        })),
                                                )
                                            }
                                        >
                                            {processing !== null
                                                ? 'Opening...'
                                                : 'Open Material'}
                                        </button>
                                    )}
                                    {opened[m.id] && !completed[m.id] && (
                                        <button
                                            disabled={processing !== null}
                                            className="rounded-lg bg-[#ad2924] px-4 py-2 text-sm font-semibold text-white"
                                            onClick={() =>
                                                post(
                                                    typeof m.id === 'string' &&
                                                        m.id.startsWith(
                                                            'library-',
                                                        )
                                                        ? `/assessments/assignments/${assignment.id}/library-attachments/${m.id.replace('library-', '')}/complete`
                                                        : `/assessments/assignments/${assignment.id}/materials/${m.id}/complete`,
                                                    {
                                                        completion_percentage:
                                                            m.type ===
                                                                'video' ||
                                                            m.type === 'audio'
                                                                ? 100
                                                                : 100,
                                                    },
                                                    () =>
                                                        setCompleted((v) => ({
                                                            ...v,
                                                            [m.id]: true,
                                                        })),
                                                )
                                            }
                                        >
                                            {processing !== null
                                                ? 'Saving...'
                                                : m.type === 'video' ||
                                                    m.type === 'audio'
                                                  ? `Confirm ${m.threshold}% Completed`
                                                  : 'Mark as Reviewed'}
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                    <div className="mt-6 flex flex-wrap gap-3">
                        {requiredTrainingComplete && (
                            <Link
                                href={`/assessments/assignments/${assignment.id}/start`}
                                method="post"
                                as="button"
                                disabled={processing !== null}
                                onStart={() => {
                                    setError('');
                                    setProcessing('assessment');
                                }}
                                onError={() =>
                                    setError(
                                        'Assessment could not be started. Please retry.',
                                    )
                                }
                                onFinish={() => setProcessing(null)}
                                className="rounded-lg bg-[#ad2924] px-5 py-2.5 font-semibold text-white disabled:cursor-wait disabled:opacity-70"
                            >
                                {processing === 'assessment'
                                    ? 'Starting...'
                                    : 'Take Assessment'}
                            </Link>
                        )}
                        <Link
                            href="/assessments"
                            className="inline-block rounded-lg bg-[#29263b] px-5 py-2.5 font-semibold text-white"
                        >
                            Return to My Assessments
                        </Link>
                    </div>
                </div>
            </main>
        </>
    );
}
Training.layout = {
    breadcrumbs: [
        { title: 'Training & Assessments', href: '/assessments' },
        { title: 'Training', href: '#' },
    ],
};
