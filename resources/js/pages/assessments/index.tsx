import { Head, Link, router, usePage } from '@inertiajs/react';
import { BookOpenCheck, Clock3 } from 'lucide-react';
import { useState } from 'react';

type Assignment = {
    id: number;
    title: string;
    category: string | null;
    available_from: string | null;
    due_date: string | null;
    due_label: string | null;
    passing_score: number;
    time_limit_minutes: number | null;
    maximum_attempts: number;
    attempts_used: number;
    status: string;
    training_complete: boolean;
    required_training_total: number;
    required_training_completed: number;
    has_training: boolean;
    active_attempt_id: number | null;
    latest_attempt_id: number | null;
    can_retake: boolean;
    configuration_error: string | null;
};
type WeekSummary = { total: number; completed: number; remaining: number };
const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const assignmentDateTime = (value: string) =>
    new Date(value).toLocaleString('en-US', { timeZone: 'Asia/Manila' });

export default function MyAssessments({
    assignments,
    week_summary,
}: {
    assignments: Assignment[];
    week_summary: WeekSummary;
}) {
    const { errors } = usePage<{
        errors: Record<string, string | string[]>;
    }>().props;
    const [startingId, setStartingId] = useState<number | null>(null);
    const errorMessages = Object.values(errors).flatMap((error) =>
        Array.isArray(error) ? error : [error],
    );

    return (
        <>
            <Head title="Training & Assessments" />
            <main className="min-h-full bg-[#f7f7fa] p-6 lg:p-8">
                <div className="mx-auto max-w-6xl">
                    <header className="mb-6">
                        <p className="text-xs font-bold tracking-[.2em] text-[#b72822] uppercase">
                            Training & Assessments
                        </p>
                        <h1 className="mt-2 text-3xl font-bold text-[#1b1d2a]">
                            My Assessments
                        </h1>
                        <p className="mt-2 text-[#6f7282]">
                            Complete required learning, take assigned
                            assessments, and review your results.
                        </p>
                        <div className="mt-4 flex gap-4 text-sm font-semibold">
                            <Link href="/assessments/history">
                                Assessment History
                            </Link>
                            <Link href="/assessments/progress">
                                My Progress
                            </Link>
                        </div>
                        <div className="mt-5 grid max-w-xl grid-cols-3 gap-3 rounded-2xl border border-[#e2e3e8] bg-white p-4 text-sm">
                            <div>
                                <span className="block text-[#77798a]">
                                    This Week
                                </span>
                                <strong>{week_summary.total}</strong>
                            </div>
                            <div>
                                <span className="block text-[#77798a]">
                                    Completed
                                </span>
                                <strong>{week_summary.completed}</strong>
                            </div>
                            <div>
                                <span className="block text-[#77798a]">
                                    Remaining
                                </span>
                                <strong>{week_summary.remaining}</strong>
                            </div>
                        </div>
                    </header>
                    {errorMessages.length > 0 && (
                        <div
                            role="alert"
                            className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"
                        >
                            {errorMessages.map((message) => (
                                <p key={message}>{message}</p>
                            ))}
                        </div>
                    )}
                    <div className="grid gap-4">
                        {assignments.length === 0 && (
                            <div className="rounded-2xl border bg-white p-10 text-center text-[#6f7282]">
                                No assessments are currently assigned to you.
                            </div>
                        )}
                        {assignments.map((a) => (
                            <article
                                key={a.id}
                                className="rounded-2xl border border-[#e2e3e8] bg-white p-6 shadow-sm"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm text-[#77798a]">
                                            {a.category ?? 'General'}
                                        </p>
                                        <h2 className="text-xl font-bold text-[#202231]">
                                            {a.title}
                                        </h2>
                                    </div>
                                    <span className="rounded-full bg-[#f7e9e8] px-3 py-1 text-sm font-semibold text-[#9e251f]">
                                        {label(a.status)}
                                    </span>
                                </div>
                                <div className="mt-4 grid gap-2 text-sm text-[#656878] sm:grid-cols-2 lg:grid-cols-5">
                                    <span>Pass: {a.passing_score}%</span>
                                    <span>
                                        <Clock3 className="mr-1 inline size-4" />
                                        {a.time_limit_minutes
                                            ? `${a.time_limit_minutes} min`
                                            : 'Untimed'}
                                    </span>
                                    <span>
                                        Attempts: {a.attempts_used}/
                                        {a.maximum_attempts}
                                    </span>
                                    <span>
                                        Available:{' '}
                                        {a.available_from
                                            ? assignmentDateTime(
                                                  a.available_from,
                                              )
                                            : 'Now'}
                                    </span>
                                    <span
                                        className={
                                            a.due_label?.startsWith('Overdue')
                                                ? 'font-semibold text-red-700'
                                                : a.due_label
                                                  ? 'font-semibold text-amber-700'
                                                  : ''
                                        }
                                    >
                                        Due:{' '}
                                        {a.due_date
                                            ? `${assignmentDateTime(a.due_date)}${a.due_label ? ` (${a.due_label})` : ''}`
                                            : 'None'}
                                    </span>
                                </div>
                                <div className="mt-5 flex flex-wrap gap-2">
                                    {a.configuration_error && (
                                        <p className="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                                            {a.configuration_error}
                                        </p>
                                    )}
                                    {a.required_training_total > 0 && (
                                        <span
                                            className={`rounded-lg px-3 py-2 text-sm font-semibold ${a.training_complete ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-800'}`}
                                        >
                                            {a.training_complete
                                                ? 'Training Complete'
                                                : `Training ${a.required_training_completed} of ${a.required_training_total} Complete`}
                                        </span>
                                    )}
                                    {a.has_training && (
                                        <Link
                                            href={`/assessments/assignments/${a.id}/training`}
                                            className="rounded-lg border px-4 py-2 text-sm font-semibold"
                                        >
                                            <BookOpenCheck className="mr-2 inline size-4" />
                                            {a.training_complete
                                                ? 'View Training'
                                                : 'Complete Training'}
                                        </Link>
                                    )}
                                    {a.active_attempt_id ? (
                                        <Link
                                            href={`/assessments/attempts/${a.active_attempt_id}`}
                                            className="rounded-lg bg-[#ad2924] px-4 py-2 text-sm font-semibold text-white"
                                        >
                                            Continue Assessment
                                        </Link>
                                    ) : a.latest_attempt_id && !a.can_retake ? (
                                        <Link
                                            href={`/assessments/attempts/${a.latest_attempt_id}/result`}
                                            className="rounded-lg bg-[#29263b] px-4 py-2 text-sm font-semibold text-white"
                                        >
                                            View Results
                                        </Link>
                                    ) : (
                                        (a.status === 'ready' ||
                                            a.can_retake) && (
                                            <button
                                                type="button"
                                                disabled={startingId !== null}
                                                onClick={() =>
                                                    router.post(
                                                        `/assessments/assignments/${a.id}/start`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onStart: () =>
                                                                setStartingId(
                                                                    a.id,
                                                                ),
                                                            onFinish: () =>
                                                                setStartingId(
                                                                    null,
                                                                ),
                                                        },
                                                    )
                                                }
                                                className="rounded-lg bg-[#ad2924] px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-70"
                                            >
                                                {startingId === a.id
                                                    ? 'Starting...'
                                                    : a.can_retake
                                                    ? 'Retake Assessment'
                                                    : 'Start Assessment'}
                                            </button>
                                        )
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            </main>
        </>
    );
}
MyAssessments.layout = {
    breadcrumbs: [{ title: 'Training & Assessments', href: '/assessments' }],
};
