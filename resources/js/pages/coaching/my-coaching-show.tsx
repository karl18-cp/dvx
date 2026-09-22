import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@mui/material';
import { formatCoachingDate } from '@/lib/coaching-dates';

type Assignment = {
    id: number;
    material: {
        title: string;
        description?: string;
        type: string;
        content?: string;
        skill?: { name: string };
    };
    progress: { completed_at?: string }[];
};
type Coaching = {
    id: number;
    coaching_date: string;
    type: string;
    status: string;
    summary: string;
    strengths?: string;
    areas_for_improvement?: string;
    action_plan?: string;
    follow_up_date?: string;
    acknowledged_at?: string;
    coach: { name: string };
    skill?: { name: string };
    assessment?: { title: string };
    training_assignments: Assignment[];
};
export default function MyCoachingShow({ coaching }: { coaching: Coaching }) {
    const mediaUrl = (assignment: Assignment) =>
        `/my-coaching/${coaching.id}/training/${assignment.id}/media`;

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title={coaching.type} />
            <div className="mx-auto max-w-4xl space-y-5">
                <Link
                    className="font-semibold text-red-700"
                    href="/my-coaching"
                >
                    ← Back to My Coaching
                </Link>
                <section className="rounded-2xl border bg-white p-6">
                    <p className="text-xs font-bold tracking-wider text-red-700 uppercase">
                        Coaching Feedback
                    </p>
                    <h1 className="mt-2">{coaching.type}</h1>
                    <p className="text-slate-600">
                        {formatCoachingDate(coaching.coaching_date)} · Coach:{' '}
                        {coaching.coach.name} ·{' '}
                        {coaching.skill?.name || 'General'}
                    </p>
                    {coaching.assessment && (
                        <p className="mt-1 text-sm">
                            Related Assessment:{' '}
                            <b>{coaching.assessment.title}</b>
                        </p>
                    )}
                    <div className="mt-5 grid gap-4">
                        <Block title="Summary" text={coaching.summary} />
                        <Block title="Strengths" text={coaching.strengths} />
                        <Block
                            title="Areas for Improvement"
                            text={coaching.areas_for_improvement}
                        />
                        <Block
                            title="Action Plan"
                            text={coaching.action_plan}
                        />
                    </div>
                    {coaching.follow_up_date && (
                        <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm">
                            <b>Follow-up Date:</b>{' '}
                            {formatCoachingDate(coaching.follow_up_date)}
                        </p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-6">
                    <h2 className="text-lg font-bold">Assigned Training</h2>
                    <div className="mt-3 space-y-4">
                        {coaching.training_assignments.map((a) => {
                            const completed = Boolean(
                                a.progress[0]?.completed_at,
                            );

                            return (
                                <article
                                    className="rounded-xl border p-4"
                                    key={a.id}
                                >
                                    <div className="flex justify-between gap-3">
                                        <div>
                                            <b>{a.material.title}</b>
                                            <p className="text-sm text-slate-500 capitalize">
                                                {a.material.type} ·{' '}
                                                {a.material.skill?.name ||
                                                    'General'}
                                            </p>
                                        </div>
                                        <span
                                            className={`h-fit rounded-full px-3 py-1 text-xs font-bold ${completed ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}
                                        >
                                            {completed
                                                ? 'Completed'
                                                : 'Pending'}
                                        </span>
                                    </div>
                                    {a.material.description && (
                                        <p className="mt-3 text-sm text-slate-600">
                                            {a.material.description}
                                        </p>
                                    )}
                                    {a.material.type === 'written' && (
                                        <div className="mt-3 rounded-xl bg-slate-50 p-4 whitespace-pre-wrap">
                                            {a.material.content}
                                        </div>
                                    )}
                                    {a.material.type === 'video' && (
                                        <video
                                            className="mt-3 max-h-96 w-full rounded-xl bg-black"
                                            controls
                                            src={mediaUrl(a)}
                                            onPlay={() =>
                                                router.post(
                                                    `/my-coaching/${coaching.id}/training/${a.id}/open`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    )}
                                    {a.material.type === 'audio' && (
                                        <audio
                                            className="mt-3 w-full"
                                            controls
                                            src={mediaUrl(a)}
                                            onPlay={() =>
                                                router.post(
                                                    `/my-coaching/${coaching.id}/training/${a.id}/open`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    )}
                                    {['image', 'document'].includes(
                                        a.material.type,
                                    ) && (
                                        <a
                                            className="mt-3 inline-block font-semibold text-red-700"
                                            href={mediaUrl(a)}
                                            target="_blank"
                                            rel="noreferrer"
                                            onClick={() =>
                                                router.post(
                                                    `/my-coaching/${coaching.id}/training/${a.id}/open`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Open {a.material.type}
                                        </a>
                                    )}
                                    {!completed && (
                                        <div className="mt-4">
                                            <Button
                                                variant="outlined"
                                                onClick={() =>
                                                    router.post(
                                                        `/my-coaching/${coaching.id}/training/${a.id}/complete`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Mark as Reviewed
                                            </Button>
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                        {!coaching.training_assignments.length && (
                            <p className="text-sm text-slate-500">
                                No training assigned.
                            </p>
                        )}
                    </div>
                </section>
                <section className="rounded-2xl border bg-white p-6">
                    {coaching.acknowledged_at ? (
                        <p className="font-semibold text-green-700">
                            Acknowledged on{' '}
                            {new Date(
                                coaching.acknowledged_at,
                            ).toLocaleString()}
                        </p>
                    ) : (
                        <>
                            <p className="mb-3 text-sm text-slate-600">
                                I acknowledge that I have reviewed this coaching
                                feedback. This confirms review only and does not
                                imply agreement.
                            </p>
                            <Button
                                variant="contained"
                                onClick={() =>
                                    router.post(
                                        `/my-coaching/${coaching.id}/acknowledge`,
                                    )
                                }
                            >
                                Acknowledge Coaching
                            </Button>
                        </>
                    )}
                </section>
            </div>
        </main>
    );
}
function Block({ title, text }: { title: string; text?: string }) {
    return (
        <div>
            <h2 className="font-bold">{title}</h2>
            <p className="mt-1 whitespace-pre-wrap text-slate-700">
                {text || 'Not provided.'}
            </p>
        </div>
    );
}
MyCoachingShow.layout = {
    breadcrumbs: [
        { title: 'My Coaching', href: '/my-coaching' },
        { title: 'Feedback', href: '#' },
    ],
};
