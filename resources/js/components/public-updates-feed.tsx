import { useForm } from '@inertiajs/react';
import { CheckCircle2, MessageCircle, Send } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

export type PublicUpdate = {
    id: number;
    title: string;
    body: string;
    author_name: string;
    published_at: string;
    comments_count: number;
    comments: {
        id: number;
        name: string;
        body: string;
        created_at: string;
    }[];
};

const date = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));

export default function PublicUpdatesFeed({
    updates,
}: {
    updates: PublicUpdate[];
}) {
    return (
        <section id="updates" className="scroll-mt-28 bg-white py-24">
            <div className="mx-auto max-w-7xl px-5 lg:px-8">
                <div className="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p className="text-xs font-bold tracking-[.25em] text-red-700 uppercase">
                            Divertex community
                        </p>
                        <h2 className="mt-3 text-4xl font-black">
                            Updates from our team.
                        </h2>
                        <p className="mt-4 max-w-2xl leading-7 text-slate-600">
                            Follow company news, hiring updates, and community
                            activities. Join the conversation by leaving a
                            comment.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-900">
                        Comments are reviewed before appearing publicly.
                    </div>
                </div>
                <div className="mt-10 grid gap-6 lg:grid-cols-2">
                    {updates.map((update) => (
                        <UpdateCard key={update.id} update={update} />
                    ))}
                </div>
                {!updates.length && (
                    <div className="mt-10 rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center text-slate-500">
                        Public updates will appear here soon.
                    </div>
                )}
            </div>
        </section>
    );
}

function UpdateCard({ update }: { update: PublicUpdate }) {
    const [commenting, setCommenting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const form = useForm({
        request_key: crypto.randomUUID(),
        name: '',
        email: '',
        body: '',
        website: '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/public-updates/${update.id}/comments`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.setData('request_key', crypto.randomUUID());
                setSubmitted(true);
                setCommenting(false);
            },
        });
    };

    return (
        <article className="flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-[#fafafb] shadow-sm">
            <div className="h-1.5 bg-[linear-gradient(90deg,#8d1820,#d32632,#ff6670)]" />
            <div className="flex-1 p-6 sm:p-7">
                <div className="flex items-center gap-3">
                    <img
                        src="/images/divertex-logo.png"
                        alt=""
                        className="size-11 rounded-full border border-red-100 bg-white object-contain p-1"
                    />
                    <div>
                        <p className="font-black">Divertex Corporation</p>
                        <p className="text-xs text-slate-500">
                            {date(update.published_at)} · {update.author_name}
                        </p>
                    </div>
                </div>
                <h3 className="mt-6 text-2xl font-black text-slate-950">
                    {update.title}
                </h3>
                <p className="mt-3 leading-7 whitespace-pre-wrap text-slate-600">
                    {update.body}
                </p>
                <div className="mt-6 flex items-center justify-between border-y border-slate-200 py-3 text-sm text-slate-500">
                    <span className="flex items-center gap-2">
                        <MessageCircle size={17} />
                        {update.comments_count}{' '}
                        {update.comments_count === 1 ? 'comment' : 'comments'}
                    </span>
                    <button
                        type="button"
                        onClick={() => {
                            setSubmitted(false);
                            setCommenting((value) => !value);
                        }}
                        className="font-bold text-red-700 hover:text-red-900"
                    >
                        Comment
                    </button>
                </div>
                {update.comments.length > 0 && (
                    <div className="mt-5 space-y-3">
                        {update.comments.map((comment) => (
                            <div key={comment.id} className="flex gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-black text-slate-600">
                                    {comment.name.slice(0, 2).toUpperCase()}
                                </span>
                                <div className="min-w-0 rounded-2xl rounded-tl-sm bg-white px-4 py-3">
                                    <p className="text-sm font-bold text-slate-900">
                                        {comment.name}
                                    </p>
                                    <p className="mt-1 text-sm leading-6 break-words whitespace-pre-wrap text-slate-600">
                                        {comment.body}
                                    </p>
                                    <p className="mt-1 text-[10px] text-slate-400">
                                        {date(comment.created_at)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {submitted && (
                    <div className="mt-5 flex gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                        <CheckCircle2 size={19} className="shrink-0" />
                        Thanks! Your comment was sent for review.
                    </div>
                )}
                {commenting && (
                    <form
                        onSubmit={submit}
                        className="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2"
                    >
                        <input
                            required
                            maxLength={100}
                            placeholder="Your name *"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            className="rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500"
                        />
                        <input
                            type="email"
                            maxLength={254}
                            placeholder="Email (not displayed)"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            className="rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500"
                        />
                        <textarea
                            required
                            minLength={2}
                            maxLength={1500}
                            rows={3}
                            placeholder="Write a comment *"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            className="rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500 sm:col-span-2"
                        />
                        <input
                            tabIndex={-1}
                            autoComplete="off"
                            aria-hidden="true"
                            value={form.data.website}
                            onChange={(event) =>
                                form.setData('website', event.target.value)
                            }
                            className="hidden"
                        />
                        {Object.values(form.errors).length > 0 && (
                            <p className="text-sm text-red-700 sm:col-span-2">
                                {Object.values(form.errors)[0]}
                            </p>
                        )}
                        <button
                            disabled={form.processing}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#c4262d] px-4 py-2.5 font-bold text-white disabled:opacity-60 sm:col-span-2 sm:justify-self-end"
                        >
                            <Send size={16} />
                            {form.processing ? 'Sending…' : 'Submit for review'}
                        </button>
                    </form>
                )}
            </div>
        </article>
    );
}
