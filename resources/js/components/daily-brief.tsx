import { usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    CheckCircle2,
    ChevronRight,
    ExternalLink,
    Flame,
    Globe2,
    Lightbulb,
    Newspaper,
    Sparkles,
    Volume2,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type NewsItem = {
    title: string;
    url: string;
    published_at: string | null;
    source: string;
};

type DailyBriefData = {
    date: string;
    word: {
        term: string;
        pronunciation: string;
        meaning: string;
        example: string;
        synonym: string;
    };
    lesson: {
        category: string;
        title: string;
        body: string;
        question: string;
        options: string[];
        correct_index: number;
        explanation: string;
    };
    news: Record<string, { label: string; items: NewsItem[] }>;
    progress: { completed: boolean; correct: boolean | null; streak: number };
};

export default function DailyBrief({
    variant = 'portal',
}: {
    variant?: 'portal' | 'landing';
}) {
    const { auth } = usePage().props;
    const [brief, setBrief] = useState<DailyBriefData | null>(null);
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<number | null>(null);
    const [result, setResult] = useState<{
        correct: boolean;
        correct_index: number;
        explanation: string;
        streak?: number;
    } | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const load = useCallback(async () => {
        try {
            const response = await fetch('/daily-brief', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                setBrief(await response.json());
            }
        } catch {
            // The rest of the page remains usable while a remote news feed is unavailable.
        }
    }, []);

    useEffect(() => {
        const timer = window.setTimeout(() => void load(), 0);

        return () => window.clearTimeout(timer);
    }, [load]);

    const answer = async () => {
        if (!brief || selected === null || result) {
            return;
        }

        if (!auth.user) {
            setResult({
                correct: selected === brief.lesson.correct_index,
                correct_index: brief.lesson.correct_index,
                explanation: brief.lesson.explanation,
            });

            return;
        }

        setSubmitting(true);

        try {
            const token = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];
            const response = await fetch('/daily-brief/complete', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
                },
                body: JSON.stringify({ answer: selected }),
            });

            if (response.ok) {
                const next = await response.json();
                setResult(next);
                setBrief((current) =>
                    current
                        ? {
                              ...current,
                              progress: {
                                  completed: true,
                                  correct: next.correct,
                                  streak: next.streak,
                              },
                          }
                        : current,
                );
            }
        } finally {
            setSubmitting(false);
        }
    };

    if (!brief) {
        return null;
    }

    if (variant === 'landing') {
        return (
            <section id="daily-brief" className="bg-[#f2f3f5] py-20">
                <div className="mx-auto max-w-7xl px-5 lg:px-8">
                    <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-xs font-black tracking-[.25em] text-red-700 uppercase">
                                Learn something every day
                            </p>
                            <h2 className="mt-3 text-4xl font-black text-slate-950">
                                Today at Divertex
                            </h2>
                            <p className="mt-3 max-w-2xl text-slate-600">
                                A quick English lesson, practical workplace
                                knowledge, and current headlines from the U.S.
                                and Canada.
                            </p>
                        </div>
                        <Button
                            onClick={() => setOpen(true)}
                            className="h-12 rounded-xl bg-red-700 px-6 hover:bg-red-800"
                        >
                            Open today’s brief <ChevronRight />
                        </Button>
                    </div>
                    <div className="mt-9 grid gap-5 lg:grid-cols-3">
                        <PreviewCard
                            icon={<Sparkles />}
                            eyebrow="Word of the day"
                            title={brief.word.term}
                            text={brief.word.meaning}
                        />
                        <PreviewCard
                            icon={<Lightbulb />}
                            eyebrow={brief.lesson.category}
                            title={brief.lesson.title}
                            text={brief.lesson.body}
                        />
                        <PreviewCard
                            icon={<Globe2 />}
                            eyebrow="U.S. & Canada"
                            title="Current headlines"
                            text="Open the brief for today’s cached news feed and source links."
                        />
                    </div>
                    <BriefDialog
                        brief={brief}
                        open={open}
                        setOpen={setOpen}
                        selected={selected}
                        setSelected={setSelected}
                        result={result}
                        submit={answer}
                        submitting={submitting}
                    />
                </div>
            </section>
        );
    }

    const headline = Object.values(brief.news).flatMap((feed) => feed.items)[0];

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="group mx-4 mt-4 flex shrink-0 items-center gap-3 rounded-2xl border border-red-100 bg-gradient-to-r from-white via-red-50/70 to-amber-50 px-4 py-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:mx-6"
            >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-700 text-white shadow-sm">
                    <Newspaper size={19} />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <strong className="text-sm text-slate-950">
                            Daily Brief
                        </strong>
                        <span className="text-xs font-bold text-red-700 uppercase">
                            {brief.word.term}
                        </span>
                        {brief.progress.streak > 0 && (
                            <span className="inline-flex items-center gap-1 text-xs font-bold text-amber-700">
                                <Flame size={13} /> {brief.progress.streak} day
                                streak
                            </span>
                        )}
                    </span>
                    <span className="block truncate text-xs text-slate-600 sm:text-sm">
                        {headline?.title ?? brief.lesson.title}
                    </span>
                </span>
                <span className="hidden items-center gap-1 text-xs font-bold text-red-700 sm:flex">
                    {brief.progress.completed ? 'Reviewed' : 'Read & learn'}
                    <ChevronRight size={16} />
                </span>
            </button>
            <BriefDialog
                brief={brief}
                open={open}
                setOpen={setOpen}
                selected={selected}
                setSelected={setSelected}
                result={result}
                submit={answer}
                submitting={submitting}
            />
        </>
    );
}

function BriefDialog({
    brief,
    open,
    setOpen,
    selected,
    setSelected,
    result,
    submit,
    submitting,
}: {
    brief: DailyBriefData;
    open: boolean;
    setOpen: (open: boolean) => void;
    selected: number | null;
    setSelected: (value: number) => void;
    result: {
        correct: boolean;
        correct_index: number;
        explanation: string;
    } | null;
    submit: () => void;
    submitting: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-h-[92vh] gap-0 overflow-hidden rounded-[1.75rem] border-0 p-0 sm:max-w-4xl">
                <DialogHeader className="bg-gradient-to-br from-[#18131f] to-[#65151a] px-7 py-6 pr-14 text-white">
                    <div className="flex items-center gap-2 text-xs font-bold tracking-[.2em] text-red-200 uppercase">
                        <BookOpenCheck size={16} /> Daily Brief · {brief.date}
                    </div>
                    <DialogTitle className="text-2xl font-black">
                        News, vocabulary, and a two-minute lesson
                    </DialogTitle>
                    <DialogDescription className="text-white/65">
                        Stay informed and keep your communication skills sharp.
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-[calc(92vh-145px)] overflow-y-auto bg-[#f5f6f8] p-5 sm:p-7">
                    <div className="grid gap-5 lg:grid-cols-[.9fr_1.1fr]">
                        <div className="space-y-5">
                            <article className="rounded-3xl border border-red-100 bg-white p-6 shadow-sm">
                                <p className="flex items-center gap-2 text-xs font-black tracking-[.16em] text-red-700 uppercase">
                                    <Volume2 size={15} /> Word of the day
                                </p>
                                <div className="mt-3 flex flex-wrap items-end gap-3">
                                    <h3 className="text-3xl font-black text-slate-950 capitalize">
                                        {brief.word.term}
                                    </h3>
                                    <span className="pb-1 text-sm text-slate-500">
                                        {brief.word.pronunciation}
                                    </span>
                                </div>
                                <p className="mt-3 leading-7 text-slate-700">
                                    {brief.word.meaning}
                                </p>
                                <div className="mt-4 rounded-2xl bg-red-50 p-4 text-sm leading-6 text-slate-700">
                                    “{brief.word.example}”
                                </div>
                                <p className="mt-3 text-xs text-slate-500">
                                    Similar word:{' '}
                                    <strong>{brief.word.synonym}</strong>
                                </p>
                            </article>
                            <article className="rounded-3xl border border-amber-100 bg-white p-6 shadow-sm">
                                <p className="text-xs font-black tracking-[.16em] text-amber-700 uppercase">
                                    {brief.lesson.category}
                                </p>
                                <h3 className="mt-2 text-xl font-black text-slate-950">
                                    {brief.lesson.title}
                                </h3>
                                <p className="mt-3 leading-7 text-slate-600">
                                    {brief.lesson.body}
                                </p>
                                <p className="mt-5 font-bold text-slate-900">
                                    {brief.lesson.question}
                                </p>
                                <div className="mt-3 space-y-2">
                                    {brief.lesson.options.map(
                                        (option, index) => {
                                            const correct =
                                                result &&
                                                index === result.correct_index;
                                            const wrong =
                                                result &&
                                                selected === index &&
                                                !result.correct;

                                            return (
                                                <button
                                                    key={option}
                                                    type="button"
                                                    disabled={Boolean(result)}
                                                    onClick={() =>
                                                        setSelected(index)
                                                    }
                                                    className={cn(
                                                        'flex w-full items-start gap-3 rounded-xl border px-4 py-3 text-left text-sm transition',
                                                        selected === index
                                                            ? 'border-red-500 bg-red-50'
                                                            : 'border-slate-200 bg-white hover:border-red-200',
                                                        correct &&
                                                            'border-emerald-500 bg-emerald-50',
                                                        wrong &&
                                                            'border-red-500 bg-red-50',
                                                    )}
                                                >
                                                    <span className="font-black text-slate-400">
                                                        {String.fromCharCode(
                                                            65 + index,
                                                        )}
                                                    </span>
                                                    {option}
                                                </button>
                                            );
                                        },
                                    )}
                                </div>
                                {result ? (
                                    <div
                                        className={cn(
                                            'mt-4 rounded-xl p-4 text-sm',
                                            result.correct
                                                ? 'bg-emerald-50 text-emerald-900'
                                                : 'bg-amber-50 text-amber-950',
                                        )}
                                    >
                                        <strong>
                                            {result.correct
                                                ? 'Correct. '
                                                : 'Good try. '}
                                        </strong>
                                        {result.explanation}
                                    </div>
                                ) : (
                                    <Button
                                        onClick={submit}
                                        disabled={
                                            selected === null || submitting
                                        }
                                        className="mt-4 w-full rounded-xl bg-red-700 hover:bg-red-800"
                                    >
                                        <CheckCircle2 /> Check answer
                                    </Button>
                                )}
                            </article>
                        </div>
                        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <p className="flex items-center gap-2 text-xs font-black tracking-[.16em] text-slate-600 uppercase">
                                <Globe2 size={15} /> Current headlines
                            </p>
                            <p className="mt-2 text-sm text-slate-500">
                                Updated periodically. Open the source to read
                                the complete report.
                            </p>
                            <div className="mt-5 space-y-6">
                                {Object.entries(brief.news).map(
                                    ([key, feed]) => (
                                        <section key={key}>
                                            <h3 className="border-b border-slate-100 pb-2 font-black text-slate-950">
                                                {feed.label}
                                            </h3>
                                            {feed.items.length ? (
                                                <div className="divide-y divide-slate-100">
                                                    {feed.items.map((item) => (
                                                        <a
                                                            key={item.url}
                                                            href={item.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="group block py-4"
                                                        >
                                                            <span className="flex gap-2 leading-6 font-bold text-slate-800 group-hover:text-red-700">
                                                                <span className="flex-1">
                                                                    {item.title}
                                                                </span>
                                                                <ExternalLink
                                                                    size={15}
                                                                    className="mt-1 shrink-0"
                                                                />
                                                            </span>
                                                            <span className="mt-1 block text-xs text-slate-500">
                                                                {item.source}
                                                                {item.published_at &&
                                                                    ` · ${new Date(item.published_at).toLocaleString()}`}
                                                            </span>
                                                        </a>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="py-5 text-sm text-slate-500">
                                                    Headlines are refreshing.
                                                    Please check again shortly.
                                                </p>
                                            )}
                                        </section>
                                    ),
                                )}
                            </div>
                            <p className="mt-5 text-xs leading-5 text-slate-400">
                                Headlines link to third-party publishers. Verify
                                important information from the original source.
                            </p>
                        </article>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function PreviewCard({
    icon,
    eyebrow,
    title,
    text,
}: {
    icon: React.ReactNode;
    eyebrow: string;
    title: string;
    text: string;
}) {
    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex size-11 items-center justify-center rounded-2xl bg-red-50 text-red-700">
                {icon}
            </div>
            <p className="mt-5 text-xs font-black tracking-[.14em] text-red-700 uppercase">
                {eyebrow}
            </p>
            <h3 className="mt-2 text-xl font-black text-slate-950 capitalize">
                {title}
            </h3>
            <p className="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                {text}
            </p>
        </article>
    );
}
