import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Headphones,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-react';
import { useState } from 'react';

const positions = ['Customer Service Representative', 'Other'];

export default function Welcome() {
    const { auth } = usePage().props;
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        position: '',
        location: '',
        years_experience: '0',
        message: '',
        resume: null as File | null,
    });
    const [applicationSubmitted, setApplicationSubmitted] = useState(false);
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/apply', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setApplicationSubmitted(true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    };
    const [statusPhone, setStatusPhone] = useState('');
    const [statusEmail, setStatusEmail] = useState('');
    const [challenge, setChallenge] = useState('');
    const [challengeAnswer, setChallengeAnswer] = useState('');
    const [statusResult, setStatusResult] = useState<Record<
        string,
        string
    > | null>(null);
    const [statusMessage, setStatusMessage] = useState('');
    const [statusLoading, setStatusLoading] = useState(false);
    const csrf = () =>
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content || '';
    const loadChallenge = async () => {
        const response = await fetch('/application-status/challenge', {
            credentials: 'same-origin',
        });
        const data = await response.json();
        setChallenge(data.question || '');
        setChallengeAnswer('');
    };
    const checkStatus = async (event: React.FormEvent) => {
        event.preventDefault();
        setStatusLoading(true);
        setStatusMessage('');
        setStatusResult(null);

        try {
            const response = await fetch('/application-status', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    phone: statusPhone,
                    email: statusEmail,
                    challenge_answer: challengeAnswer,
                }),
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Unable to check the application status.',
                );
            }

            setStatusResult(data.application);
        } catch (error) {
            setStatusMessage(
                error instanceof Error
                    ? error.message
                    : 'Unable to check the application status.',
            );
        } finally {
            setStatusLoading(false);
            void loadChallenge();
        }
    };

    return (
        <div className="min-h-screen bg-[#f6f6f8] font-sans text-[#161522]">
            <Head title="Divertex — People. Performance. Possibility." />
            <header className="sticky top-0 z-30 border-b border-white/10 bg-[#0d0b12]/95 text-white backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
                    <a href="#top" className="flex items-center gap-3">
                        <span className="rounded-full border-2 border-red-500 bg-[#8f171d] px-5 py-2 text-sm font-black italic">
                            DIVERTEX
                        </span>
                        <span className="hidden text-xs font-semibold tracking-[.24em] text-white/60 sm:inline">
                            CORPORATION
                        </span>
                    </a>
                    <nav className="hidden items-center gap-7 text-sm font-semibold md:flex">
                        <a href="#about">About</a>
                        <a href="#services">What We Do</a>
                        <a href="#careers">Careers</a>
                        <a href="/applicant-portal">Applicant Portal</a>
                    </nav>
                    <div className="flex gap-2">
                        {auth.user ? (
                            <Link
                                href="/dashboard"
                                className="rounded-lg border border-white/30 px-4 py-2 text-sm font-bold"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <Link
                                href="/login"
                                className="rounded-lg border border-white/30 px-4 py-2 text-sm font-bold"
                            >
                                Employee Login
                            </Link>
                        )}
                        <div className="flex flex-col gap-1.5">
                            <a
                                href="#apply"
                                className="rounded-lg bg-[#c4262d] px-4 py-2 text-center text-sm font-bold"
                            >
                                Apply Now
                            </a>
                            <a
                                href="/applicant-portal"
                                className="text-center text-xs font-semibold text-white/75 hover:text-white"
                            >
                                Check for Updates
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            <main id="top">
                <section className="relative overflow-hidden bg-[#100d16] text-white">
                    <div className="absolute -top-48 -right-28 h-[520px] w-[520px] rounded-full bg-red-700/25 blur-3xl" />
                    <div className="relative mx-auto grid min-h-[620px] max-w-7xl items-center gap-12 px-5 py-20 lg:grid-cols-[1.2fr_.8fr] lg:px-8">
                        <div>
                            {applicationSubmitted && (
                                <div
                                    className="mb-7 rounded-2xl border border-green-400/40 bg-green-400/10 p-5 text-white shadow-lg"
                                    role="status"
                                >
                                    <p className="text-lg font-black">
                                        Your application has been submitted!
                                    </p>
                                    <div className="mt-2 space-y-1 text-sm text-white/75">
                                        <p>
                                            Kindly wait for an update regarding
                                            your application.
                                        </p>
                                        <p>
                                            Track your progress in the{' '}
                                            <a
                                                href="/applicant-portal"
                                                className="font-bold underline"
                                            >
                                                Applicant Portal
                                            </a>
                                            .
                                        </p>
                                    </div>
                                </div>
                            )}
                            <p className="mb-5 text-xs font-bold tracking-[.3em] text-red-400 uppercase">
                                Build better customer experiences
                            </p>
                            <h1 className="max-w-3xl text-5xl leading-[1.05] font-black tracking-tight sm:text-6xl lg:text-7xl">
                                People-first service.
                                <br />
                                <span className="text-red-500">
                                    Performance that matters.
                                </span>
                            </h1>
                            <p className="mt-7 max-w-2xl text-lg leading-8 text-white/65">
                                Divertex brings talented people, focused
                                training, and dependable operations together to
                                help teams deliver excellent customer
                                experiences.
                            </p>
                            <div className="mt-9 flex flex-wrap gap-3">
                                <a
                                    href="#apply"
                                    className="flex items-center gap-2 rounded-xl bg-[#c4262d] px-6 py-4 font-bold"
                                >
                                    Start your application{' '}
                                    <ArrowRight size={18} />
                                </a>
                                <a
                                    href="#about"
                                    className="rounded-xl border border-white/20 px-6 py-4 font-bold"
                                >
                                    Discover Divertex
                                </a>
                            </div>
                        </div>
                        <div className="grid gap-4">
                            <div className="rounded-3xl border border-white/10 bg-white/5 p-7 backdrop-blur">
                                <Sparkles className="text-red-400" />
                                <p className="mt-10 text-3xl font-black">
                                    Grow with a team that invests in you.
                                </p>
                                <p className="mt-3 text-white/60">
                                    Clear expectations, purposeful coaching, and
                                    opportunities to develop.
                                </p>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <Metric value="People" label="at the center" />
                                <Metric
                                    value="Quality"
                                    label="in every interaction"
                                />
                            </div>
                        </div>
                    </div>
                </section>
                <section
                    id="about"
                    className="mx-auto max-w-7xl px-5 py-24 lg:px-8"
                >
                    <div className="grid gap-12 lg:grid-cols-2">
                        <div>
                            <p className="text-xs font-bold tracking-[.25em] text-red-700 uppercase">
                                Who we are
                            </p>
                            <h2 className="mt-4 text-4xl font-black">
                                A workplace built for service, learning, and
                                meaningful growth.
                            </h2>
                        </div>
                        <div className="space-y-4 leading-7 text-slate-600">
                            <p>
                                Our teams support customer conversations with
                                professionalism, empathy, and consistent
                                standards.
                            </p>
                            <p>
                                We combine structured onboarding, ongoing
                                training, quality assurance, and coaching so
                                employees understand how to succeed and where
                                they can grow.
                            </p>
                        </div>
                    </div>
                </section>
                <section id="services" className="bg-white py-24">
                    <div className="mx-auto max-w-7xl px-5 lg:px-8">
                        <p className="text-xs font-bold tracking-[.25em] text-red-700 uppercase">
                            What we value
                        </p>
                        <h2 className="mt-3 max-w-2xl text-4xl font-black">
                            Professional service backed by strong people
                            systems.
                        </h2>
                        <div className="mt-10 grid gap-5 md:grid-cols-3">
                            <Value
                                icon={<Headphones />}
                                title="Customer Focus"
                                text="Thoughtful conversations that respect every customer's time and needs."
                            />
                            <Value
                                icon={<Users />}
                                title="People Development"
                                text="Training, feedback, and coaching designed to build confidence and capability."
                            />
                            <Value
                                icon={<ShieldCheck />}
                                title="Quality & Accountability"
                                text="Clear standards, fair evaluation, and dependable follow-through."
                            />
                        </div>
                    </div>
                </section>
                <section
                    id="careers"
                    className="mx-auto max-w-7xl px-5 py-24 lg:px-8"
                >
                    <div className="rounded-[2rem] bg-[#78171c] px-7 py-12 text-white sm:px-12 lg:flex lg:items-center lg:justify-between">
                        <div>
                            <p className="text-xs font-bold tracking-[.25em] text-red-200 uppercase">
                                Careers at Divertex
                            </p>
                            <h2 className="mt-3 text-4xl font-black">
                                Your next opportunity can start here.
                            </h2>
                            <p className="mt-4 max-w-2xl text-red-100/80">
                                Share your information with our recruitment
                                team. We will contact qualified applicants for
                                screening.
                            </p>
                        </div>
                        <a
                            href="#apply"
                            className="mt-7 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-4 font-bold text-[#78171c] lg:mt-0"
                        >
                            Application form <ArrowRight size={18} />
                        </a>
                    </div>
                </section>
                <section id="application-status" className="bg-white py-24">
                    <div className="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[.75fr_1.25fr] lg:px-8">
                        <div>
                            <p className="text-xs font-bold tracking-[.25em] text-red-700 uppercase">
                                Application updates
                            </p>
                            <h2 className="mt-3 text-4xl font-black">
                                Check your application status.
                            </h2>
                            <p className="mt-5 leading-7 text-slate-600">
                                Use the same mobile number and email address
                                from your application. Your private application
                                details will not be displayed to anyone who
                                cannot provide both.
                            </p>
                        </div>
                        <form
                            onSubmit={checkStatus}
                            className="rounded-3xl border border-slate-200 bg-[#f8f8fa] p-6 sm:p-9"
                            onFocus={() => !challenge && void loadChallenge()}
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Input label="Mobile Number">
                                    <input
                                        required
                                        value={statusPhone}
                                        onChange={(e) =>
                                            setStatusPhone(e.target.value)
                                        }
                                    />
                                </Input>
                                <Input label="Email Address">
                                    <input
                                        type="email"
                                        required
                                        value={statusEmail}
                                        onChange={(e) =>
                                            setStatusEmail(e.target.value)
                                        }
                                    />
                                </Input>
                                <Input
                                    label={
                                        challenge ||
                                        'Click here to load the human-verification question'
                                    }
                                    wide
                                >
                                    <input
                                        type="number"
                                        required
                                        value={challengeAnswer}
                                        onChange={(e) =>
                                            setChallengeAnswer(e.target.value)
                                        }
                                        onFocus={() =>
                                            !challenge && void loadChallenge()
                                        }
                                        placeholder="Your answer"
                                    />
                                </Input>
                            </div>
                            {statusMessage && (
                                <p className="mt-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700">
                                    {statusMessage}
                                </p>
                            )}
                            {statusResult && (
                                <div className="mt-5 rounded-2xl border border-green-200 bg-white p-5">
                                    <p className="text-xs font-bold tracking-widest text-green-700 uppercase">
                                        Application found
                                    </p>
                                    <h3 className="mt-2 text-xl font-black">
                                        {statusResult.applicant_name}
                                    </h3>
                                    <p className="mt-1 text-slate-600">
                                        {statusResult.position}
                                    </p>
                                    <div className="mt-4">
                                        <StatusItem
                                            label="Current Status"
                                            value={statusResult.applicant_stage}
                                        />
                                    </div>
                                    <div className="mt-4 rounded-xl bg-slate-50 p-4">
                                        <b>Recruitment update</b>
                                        {statusResult.scheduled_start_at && (
                                            <p className="mt-2 font-semibold">
                                                {statusResult.schedule_label}{' '}
                                                starts:{' '}
                                                {new Intl.DateTimeFormat(
                                                    'en-PH',
                                                    {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                        timeZone: 'Asia/Manila',
                                                    },
                                                ).format(
                                                    new Date(
                                                        statusResult.scheduled_start_at,
                                                    ),
                                                )}{' '}
                                                (Asia/Manila)
                                            </p>
                                        )}
                                        <p className="mt-1 text-slate-600">
                                            {statusResult.applicant_update ||
                                                'No additional update has been posted yet.'}
                                        </p>
                                    </div>
                                </div>
                            )}
                            <button
                                disabled={statusLoading}
                                className="mt-5 rounded-xl bg-[#c4262d] px-6 py-3 font-bold text-white disabled:opacity-60"
                            >
                                {statusLoading
                                    ? 'Checking...'
                                    : 'Check My Status'}
                            </button>
                        </form>
                    </div>
                </section>
                <section id="apply" className="bg-[#100d16] py-24 text-white">
                    <div className="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-[.7fr_1.3fr] lg:px-8">
                        <div>
                            <p className="text-xs font-bold tracking-[.25em] text-red-400 uppercase">
                                Apply now
                            </p>
                            <h2 className="mt-3 text-4xl font-black">
                                Tell us about yourself.
                            </h2>
                            <p className="mt-5 leading-7 text-white/60">
                                Complete the form and attach your résumé. Please
                                provide current contact information.
                            </p>
                            <div className="mt-8 space-y-3 text-sm text-white/70">
                                {[
                                    'Secure résumé submission',
                                    'Authorized recruitment review',
                                    'Screening and interview tracking',
                                ].map((x) => (
                                    <p key={x} className="flex gap-3">
                                        <CheckCircle2
                                            className="text-red-400"
                                            size={19}
                                        />
                                        {x}
                                    </p>
                                ))}
                            </div>
                        </div>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 rounded-3xl bg-white p-6 text-slate-900 sm:grid-cols-2 sm:p-9"
                        >
                            <Input
                                label="First Name"
                                error={form.errors.first_name}
                            >
                                <input
                                    required
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Input>
                            <Input
                                label="Last Name"
                                error={form.errors.last_name}
                            >
                                <input
                                    required
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Input>
                            <Input
                                label="Email Address"
                                error={form.errors.email}
                            >
                                <input
                                    type="email"
                                    required
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </Input>
                            <Input
                                label="Mobile Number"
                                error={form.errors.phone}
                            >
                                <input
                                    required
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </Input>
                            <Input
                                label="Position"
                                error={form.errors.position}
                            >
                                <select
                                    required
                                    value={form.data.position}
                                    onChange={(e) =>
                                        form.setData('position', e.target.value)
                                    }
                                >
                                    <option value="">Select a position</option>
                                    {positions.map((p) => (
                                        <option key={p}>{p}</option>
                                    ))}
                                </select>
                            </Input>
                            <Input
                                label="Current City / Province"
                                error={form.errors.location}
                            >
                                <input
                                    value={form.data.location}
                                    onChange={(e) =>
                                        form.setData('location', e.target.value)
                                    }
                                />
                            </Input>
                            <Input
                                label="Years of Relevant Experience"
                                error={form.errors.years_experience}
                            >
                                <input
                                    type="number"
                                    min="0"
                                    max="50"
                                    required
                                    value={form.data.years_experience}
                                    onChange={(e) =>
                                        form.setData(
                                            'years_experience',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Input>
                            <Input
                                label="Résumé (PDF, DOC, DOCX — max 5 MB)"
                                error={form.errors.resume}
                            >
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx"
                                    onChange={(e) =>
                                        form.setData(
                                            'resume',
                                            e.target.files?.[0] || null,
                                        )
                                    }
                                />
                            </Input>
                            <Input
                                label="Short Introduction / Relevant Experience"
                                error={form.errors.message}
                                wide
                            >
                                <textarea
                                    rows={4}
                                    value={form.data.message}
                                    onChange={(e) =>
                                        form.setData('message', e.target.value)
                                    }
                                    placeholder="Tell us why you are interested and what experience you would bring."
                                />
                            </Input>
                            <div className="flex items-center justify-between gap-4 sm:col-span-2">
                                <p className="text-xs text-slate-500">
                                    By submitting, you confirm that your
                                    information is accurate.
                                </p>
                                <button
                                    disabled={form.processing}
                                    className="rounded-xl bg-[#b72229] px-6 py-3 font-bold text-white"
                                >
                                    {form.processing
                                        ? 'Submitting…'
                                        : 'Submit Application'}
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
            <footer className="border-t border-white/10 bg-[#100d16] px-5 py-8 text-center text-sm text-white/45">
                © {new Date().getFullYear()} Divertex Corporation. Temporary
                recruitment landing page.
            </footer>
        </div>
    );
}
function StatusItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-slate-200 p-3">
            <p className="text-xs font-bold text-slate-500 uppercase">
                {label}
            </p>
            <p className="mt-1 font-black capitalize">
                {value.replaceAll('_', ' ')}
            </p>
        </div>
    );
}

function Metric({ value, label }: { value: string; label: string }) {
    return (
        <div className="rounded-2xl border border-white/10 bg-white/5 p-5">
            <b className="text-xl">{value}</b>
            <p className="mt-1 text-xs text-white/50">{label}</p>
        </div>
    );
}
function Value({
    icon,
    title,
    text,
}: {
    icon: React.ReactNode;
    title: string;
    text: string;
}) {
    return (
        <article className="rounded-2xl border border-slate-200 p-7">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-red-700">
                {icon}
            </div>
            <h3 className="mt-6 text-xl font-black">{title}</h3>
            <p className="mt-3 leading-7 text-slate-600">{text}</p>
        </article>
    );
}
function Input({
    label,
    error,
    wide = false,
    children,
}: {
    label: string;
    error?: string;
    wide?: boolean;
    children: React.ReactNode;
}) {
    return (
        <label
            className={`${wide ? 'sm:col-span-2' : ''} block text-sm font-bold [&>input]:mt-2 [&>input]:w-full [&>input]:rounded-xl [&>input]:border [&>input]:border-slate-300 [&>input]:p-3 [&>select]:mt-2 [&>select]:w-full [&>select]:rounded-xl [&>select]:border [&>select]:border-slate-300 [&>select]:p-3 [&>textarea]:mt-2 [&>textarea]:w-full [&>textarea]:rounded-xl [&>textarea]:border [&>textarea]:border-slate-300 [&>textarea]:p-3`}
        >
            {label}
            {children}
            {error && (
                <span className="mt-1 block text-xs text-red-700">{error}</span>
            )}
        </label>
    );
}
