import {
    ArrowRight,
    Bot,
    BriefcaseBusiness,
    CheckCircle2,
    MessageCircle,
    Send,
    Sparkles,
    UserRoundSearch,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type { PublicUpdate } from '@/components/public-updates-feed';

type Message = { from: 'bot' | 'visitor'; text: string };

const opening: Message[] = [
    {
        from: 'bot',
        text: 'Hi! I’m the Divertex assistant. Are you looking for a job or business support?',
    },
];

export default function LandingAssistant({
    onBusinessInquiry,
    updates,
}: {
    onBusinessInquiry: () => void;
    updates: PublicUpdate[];
}) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>(opening);
    const [input, setInput] = useState('');

    const reply = (visitor: string, bot: string) => {
        setMessages((current) => [
            ...current,
            { from: 'visitor', text: visitor },
            { from: 'bot', text: bot },
        ]);
    };

    const answer = (event: FormEvent) => {
        event.preventDefault();
        const text = input.trim();

        if (!text) {
            return;
        }

        const normalized = text.toLowerCase();
        let response =
            'I can help with applications, application status, services, or booking a business meeting. Choose an option below.';

        if (
            normalized.includes('apply') ||
            normalized.includes('job') ||
            normalized.includes('career')
        ) {
            response =
                'You can apply through the application form on this page. Prepare your contact details and résumé, then complete the applicant assessment.';
        } else if (
            normalized.includes('status') ||
            normalized.includes('application update')
        ) {
            response =
                'Use the Applicant Portal or the application-status section with the same email and phone number used in your application.';
        } else if (
            normalized.includes('update') ||
            normalized.includes('announcement') ||
            normalized.includes('news')
        ) {
            response = updates.length
                ? `Our latest update is “${updates[0].title}.” Open Company updates below to read it and join the conversation.`
                : 'There are no public company updates yet. Please check again soon.';
        } else if (
            normalized.includes('client') ||
            normalized.includes('business') ||
            normalized.includes('service')
        ) {
            response =
                'Divertex supports customer service, development, quality assurance, virtual assistance, digital marketing, and data operations. I can open the meeting form for you.';
        } else if (
            normalized.includes('meeting') ||
            normalized.includes('contact')
        ) {
            response =
                'Use “Book a meeting” below to share your business needs and preferred schedule.';
        }

        setInput('');
        reply(text, response);
    };

    return (
        <div className="fixed right-4 bottom-4 z-50 sm:right-6 sm:bottom-6">
            {open && (
                <section
                    className="mb-3 flex h-[min(620px,calc(100dvh-110px))] w-[min(390px,calc(100vw-32px))] flex-col overflow-hidden rounded-3xl border border-white/10 bg-white shadow-2xl"
                    aria-label="Divertex visitor assistant"
                >
                    <header className="flex items-start justify-between gap-3 bg-[linear-gradient(120deg,#15111c,#681b22,#b6222c)] p-5 text-white">
                        <div className="flex gap-3">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                                <Bot size={23} />
                            </span>
                            <div>
                                <p className="font-black">Divertex Assistant</p>
                                <p className="mt-0.5 flex items-center gap-1.5 text-xs text-white/75">
                                    <span className="size-2 rounded-full bg-emerald-400" />
                                    Ready to guide you
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            aria-label="Close assistant"
                            className="rounded-xl p-2 hover:bg-white/10"
                            onClick={() => setOpen(false)}
                        >
                            <X size={20} />
                        </button>
                    </header>
                    <div className="flex-1 space-y-3 overflow-y-auto bg-[#f6f6f8] p-4">
                        {messages.map((message, index) => (
                            <div
                                key={index}
                                className={
                                    message.from === 'bot'
                                        ? 'mr-8 rounded-2xl rounded-tl-md border border-slate-200 bg-white p-3 text-sm leading-6 text-slate-700'
                                        : 'ml-8 rounded-2xl rounded-tr-md bg-[#a91f28] p-3 text-sm leading-6 text-white'
                                }
                            >
                                {message.text}
                            </div>
                        ))}
                        <div className="grid grid-cols-2 gap-2 pt-1">
                            <QuickAction
                                icon={<UserRoundSearch size={16} />}
                                label="I’m an applicant"
                                onClick={() =>
                                    reply(
                                        'I’m an applicant',
                                        'I can help you apply or check an existing application. Use the buttons below.',
                                    )
                                }
                            />
                            <QuickAction
                                icon={<BriefcaseBusiness size={16} />}
                                label="I’m a client"
                                onClick={() =>
                                    reply(
                                        'I’m a client',
                                        'Tell us the roles and services you need. We can arrange a meeting with the Divertex team.',
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <a
                                href="#updates"
                                onClick={() => setOpen(false)}
                                className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800"
                            >
                                View company updates <MessageCircle size={16} />
                            </a>
                            <a
                                href="#apply"
                                onClick={() => setOpen(false)}
                                className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800"
                            >
                                Start an application <ArrowRight size={16} />
                            </a>
                            <a
                                href="/applicant-portal"
                                className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-800"
                            >
                                Check application status{' '}
                                <CheckCircle2 size={16} />
                            </a>
                            <button
                                type="button"
                                onClick={() => {
                                    setOpen(false);
                                    onBusinessInquiry();
                                }}
                                className="flex items-center justify-between rounded-xl bg-[#c4262d] px-3 py-2.5 text-left text-sm font-bold text-white"
                            >
                                Book a business meeting{' '}
                                <BriefcaseBusiness size={16} />
                            </button>
                        </div>
                    </div>
                    <form
                        onSubmit={answer}
                        className="flex gap-2 border-t border-slate-200 bg-white p-3"
                    >
                        <input
                            aria-label="Ask the Divertex assistant"
                            value={input}
                            onChange={(event) => setInput(event.target.value)}
                            maxLength={300}
                            placeholder="Ask about jobs or services…"
                            className="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-red-500"
                        />
                        <button
                            aria-label="Send message"
                            className="flex size-11 items-center justify-center rounded-xl bg-[#c4262d] text-white"
                        >
                            <Send size={18} />
                        </button>
                    </form>
                    <p className="bg-white px-4 pb-3 text-[10px] text-slate-400">
                        This assistant provides general guidance. Application
                        decisions and meeting confirmations come from the
                        Divertex team.
                    </p>
                </section>
            )}
            <button
                type="button"
                aria-label={
                    open
                        ? 'Close Divertex assistant'
                        : 'Open Divertex assistant'
                }
                onClick={() => setOpen((current) => !current)}
                className="ml-auto flex items-center gap-3 rounded-2xl bg-[#b3121d] px-4 py-3 font-bold text-white shadow-[0_14px_40px_rgba(153,27,27,.4)] transition hover:-translate-y-0.5 hover:bg-[#8f1018]"
            >
                {open ? <X size={21} /> : <MessageCircle size={21} />}
                <span>{open ? 'Close' : 'Chat with us'}</span>
                {!open && <Sparkles size={15} className="text-red-200" />}
            </button>
        </div>
    );
}

function QuickAction({
    icon,
    label,
    onClick,
}: {
    icon: React.ReactNode;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex items-center justify-center gap-2 rounded-xl border border-red-100 bg-red-50 px-2 py-2.5 text-xs font-bold text-red-800"
        >
            {icon}
            {label}
        </button>
    );
}
