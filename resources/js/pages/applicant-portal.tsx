import { Head } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ClipboardCheck,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import DivertexLogo from '@/components/divertex-logo';

type Application = {
    applicant_name: string;
    position: string;
    applicant_stage:
        'for_screening' | 'for_final_interview' | 'passed' | 'failed';
    applicant_update: string | null;
    applied_at: string;
    updated_at: string;
    scheduled_start_at: string | null;
    schedule_label: string | null;
};
const stages = {
    for_screening: {
        title: 'For screening',
        description:
            'Your application is waiting for the recruitment team to review it.',
        step: 1,
    },
    for_final_interview: {
        title: 'For final interview',
        description:
            'Your application has moved to the final interview stage. Check the recruitment update for instructions.',
        step: 2,
    },
    passed: {
        title: 'Passed',
        description:
            'You passed the application process. Check the recruitment update for your next steps.',
        step: 3,
    },
    failed: {
        title: 'Not selected',
        description:
            'Your application was not selected. Any feedback shared by recruitment appears below.',
        step: -1,
    },
};
const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));

async function fetchQuestion(signal?: AbortSignal): Promise<string> {
    const response = await fetch('/application-status/challenge', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal,
    });

    if (!response.ok) {
        throw new Error(
            'Unable to load verification. Please wait a moment and try again.',
        );
    }

    return (await response.json()).question;
}

export default function ApplicantPortal() {
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [question, setQuestion] = useState('');
    const [answer, setAnswer] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [challengeLoading, setChallengeLoading] = useState(true);
    const [application, setApplication] = useState<Application | null>(null);

    const loadQuestion = async (signal?: AbortSignal) => {
        try {
            const nextQuestion = await fetchQuestion(signal);

            if (!signal?.aborted) {
                setQuestion(nextQuestion);
            }
        } catch (reason) {
            if (!signal?.aborted) {
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Unable to load verification.',
                );
            }
        } finally {
            if (!signal?.aborted) {
                setChallengeLoading(false);
            }
        }
    };
    const refreshQuestion = () => {
        setChallengeLoading(true);
        setQuestion('');
        setAnswer('');

        return loadQuestion();
    };
    useEffect(() => {
        const controller = new AbortController();
        void fetchQuestion(controller.signal)
            .then((value) => {
                if (!controller.signal.aborted) {
                    setQuestion(value);
                }
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setError(
                        'Unable to load verification. Please try a new question.',
                    );
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setChallengeLoading(false);
                }
            });

        return () => controller.abort();
    }, []);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setError('');
        setApplication(null);
        setLoading(true);

        try {
            const response = await fetch('/application-status', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
                body: JSON.stringify({
                    email: email.trim(),
                    phone: phone.trim(),
                    challenge_answer: answer,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    response.status === 429
                        ? 'Too many attempts. Please wait a few minutes before trying again.'
                        : (data.message ?? 'Unable to check your application.'),
                );
            }

            setApplication(data.application);
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to check your application.',
            );
        } finally {
            setLoading(false);
            await refreshQuestion();
        }
    };
    const current = application ? stages[application.applicant_stage] : null;
    const inputClass =
        'mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-red-600 focus:ring-2 focus:ring-red-100';

    return (
        <div className="min-h-dvh bg-gradient-to-br from-red-50 via-white to-rose-100 text-slate-900">
            <Head title="Applicant Portal" />
            <header className="border-b border-red-100 bg-white/80">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                    <a href="/careers" aria-label="Divertex careers">
                        <DivertexLogo className="w-32" />
                    </a>
                    <a
                        href="/careers"
                        className="flex items-center gap-2 text-sm font-semibold text-red-800"
                    >
                        <ArrowLeft size={16} />
                        Careers
                    </a>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-5 py-10 md:py-14">
                <p className="text-xs font-bold tracking-[.2em] text-red-700 uppercase">
                    Your next chapter at Divertex
                </p>
                <h1 className="mt-3 text-3xl font-bold md:text-4xl">
                    Applicant Portal
                </h1>
                <p className="mt-3 max-w-2xl text-slate-600">
                    Follow your application’s progress and read updates from our
                    recruitment team. No employee account is needed.
                </p>
                <div className="mt-8 grid items-start gap-6 lg:grid-cols-[.85fr_1.15fr]">
                    <form
                        onSubmit={submit}
                        className="space-y-5 rounded-3xl border border-t-4 border-slate-200 border-t-red-700 bg-white p-6 shadow-sm md:p-8"
                    >
                        <div>
                            <h2 className="text-xl font-bold">
                                Find your application
                            </h2>
                            <p className="mt-2 text-sm text-slate-500">
                                Enter the email and mobile number you used when
                                applying. If you applied more than once, your
                                latest matching application is shown.
                            </p>
                        </div>
                        <label className="block text-sm font-semibold">
                            Email address
                            <input
                                type="email"
                                autoComplete="email"
                                required
                                maxLength={255}
                                value={email}
                                onChange={(e) => {
                                    setEmail(e.target.value);
                                    setApplication(null);
                                }}
                                className={inputClass}
                            />
                        </label>
                        <label className="block text-sm font-semibold">
                            Mobile number
                            <input
                                type="tel"
                                autoComplete="tel"
                                required
                                maxLength={40}
                                value={phone}
                                onChange={(e) => {
                                    setPhone(e.target.value);
                                    setApplication(null);
                                }}
                                className={inputClass}
                            />
                        </label>
                        <div className="rounded-xl bg-slate-50 p-4">
                            <div className="flex items-center justify-between gap-3">
                                <label
                                    htmlFor="verification-answer"
                                    className="text-sm font-semibold"
                                >
                                    {question || 'Loading verification…'}
                                </label>
                                <button
                                    type="button"
                                    aria-label="New verification question"
                                    disabled={loading || challengeLoading}
                                    onClick={() => void refreshQuestion()}
                                    className="rounded-lg p-2 text-red-700 disabled:opacity-40"
                                >
                                    <RefreshCw size={17} />
                                </button>
                            </div>
                            <input
                                id="verification-answer"
                                type="number"
                                required
                                disabled={!question}
                                value={answer}
                                onChange={(e) => setAnswer(e.target.value)}
                                className={inputClass}
                                placeholder="Your answer"
                            />
                        </div>
                        {error && (
                            <p
                                role="alert"
                                className="rounded-xl bg-red-50 p-3 text-sm text-red-800"
                            >
                                {error}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={loading || challengeLoading || !question}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-900 to-red-600 px-5 py-3 font-bold text-white disabled:opacity-50"
                        >
                            <Search size={18} />
                            {loading
                                ? 'Checking…'
                                : application
                                  ? 'Refresh application status'
                                  : 'View application progress'}
                        </button>
                        <p className="text-center text-sm text-slate-500">
                            Haven’t applied yet?{' '}
                            <a
                                href="/careers#apply"
                                className="font-semibold text-red-700"
                            >
                                Apply here
                            </a>
                        </p>
                    </form>
                    <section
                        aria-live="polite"
                        className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8"
                    >
                        {application && current ? (
                            <>
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                                            Application progress
                                        </p>
                                        <h2 className="mt-2 text-2xl font-bold">
                                            {application.applicant_name}
                                        </h2>
                                        <p className="mt-1 text-slate-500">
                                            {application.position}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setApplication(null)}
                                        className="text-sm font-semibold text-slate-500"
                                    >
                                        Clear
                                    </button>
                                </div>
                                <div
                                    className={`mt-6 rounded-2xl p-5 ${application.applicant_stage === 'passed' ? 'bg-green-50 text-green-900' : 'bg-red-50 text-red-900'}`}
                                >
                                    <h3 className="font-bold">
                                        {current.title}
                                    </h3>
                                    <p className="mt-1 text-sm leading-6">
                                        {current.description}
                                    </p>
                                </div>
                                {current.step >= 0 && (
                                    <ol
                                        aria-label="Application stages"
                                        className="mt-7 grid grid-cols-4 gap-2"
                                    >
                                        {[
                                            'Received',
                                            'Screening',
                                            'Final interview',
                                            'Result',
                                        ].map((label, index) => (
                                            <li
                                                key={label}
                                                aria-current={
                                                    index === current.step
                                                        ? 'step'
                                                        : undefined
                                                }
                                                className="text-center"
                                            >
                                                <span
                                                    className={`mx-auto mb-2 flex size-9 items-center justify-center rounded-full ${index <= current.step ? 'bg-red-700 text-white' : 'bg-slate-100 text-slate-400'}`}
                                                >
                                                    {index < current.step ? (
                                                        <Check size={18} />
                                                    ) : (
                                                        index + 1
                                                    )}
                                                </span>
                                                <span
                                                    className={`text-xs ${index === current.step ? 'font-bold text-red-800' : 'text-slate-500'}`}
                                                >
                                                    {label}
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                                <div className="mt-7 border-t border-slate-100 pt-6">
                                    <h3 className="font-bold">
                                        Latest recruitment update
                                    </h3>
                                    {application.scheduled_start_at &&
                                        application.schedule_label && (
                                            <div className="mt-3 rounded-xl border border-red-100 bg-red-50 p-4">
                                                <p className="font-semibold">
                                                    {application.schedule_label}{' '}
                                                    starts
                                                </p>
                                                <p className="mt-1 text-sm">
                                                    {new Intl.DateTimeFormat(
                                                        'en-PH',
                                                        {
                                                            dateStyle: 'full',
                                                            timeStyle: 'short',
                                                            timeZone:
                                                                'Asia/Manila',
                                                        },
                                                    ).format(
                                                        new Date(
                                                            application.scheduled_start_at,
                                                        ),
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    Philippine time
                                                    (Asia/Manila)
                                                </p>
                                            </div>
                                        )}
                                    <p className="mt-3 rounded-xl bg-slate-50 p-4 text-sm leading-6 whitespace-pre-wrap text-slate-600">
                                        {application.applicant_update ||
                                            'No additional message has been posted yet. Please check back for updates.'}
                                    </p>
                                </div>
                                <dl className="mt-6 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <dt className="text-slate-500">
                                            Submitted
                                        </dt>
                                        <dd className="mt-1 font-semibold">
                                            {formatDate(application.applied_at)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-slate-500">
                                            Last updated
                                        </dt>
                                        <dd className="mt-1 font-semibold">
                                            {formatDate(application.updated_at)}
                                        </dd>
                                    </div>
                                </dl>
                                <p className="mt-6 text-xs text-slate-500">
                                    Showing the latest saved status. Answer the
                                    new verification question and refresh to
                                    check for changes.
                                </p>
                            </>
                        ) : (
                            <div className="flex min-h-80 flex-col items-center justify-center text-center">
                                <div className="mb-5 rounded-2xl bg-red-50 p-5 text-red-700">
                                    <ClipboardCheck size={36} />
                                </div>
                                <h2 className="text-xl font-bold">
                                    Your progress, in one place
                                </h2>
                                <p className="mt-3 max-w-sm text-sm leading-6 text-slate-500">
                                    Find your application to see its current
                                    stage and the latest message from
                                    recruitment.
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </div>
    );
}
