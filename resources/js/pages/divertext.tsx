import { Head, usePage } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
    TextField,
} from '@mui/material';
import {
    ArrowLeft,
    MessageCircle,
    MessagesSquare,
    Plus,
    RefreshCw,
    Search,
    Send,
    Users,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { chatRequest } from '@/lib/divertext';
import type { Auth } from '@/types';
import '../../css/divertext.css';

export type Room = {
    id: number;
    type: 'direct' | 'team';
    name: string;
    subtitle: string;
    avatar: string;
    can_send: boolean;
    unread_count: number;
    latest_message: {
        body: string;
        sender_name: string;
        created_at: string;
    } | null;
};
type Message = {
    id: number;
    sender_id: number | null;
    sender_name: string;
    avatar: string;
    body: string;
    created_at: string;
};
type Person = {
    id: number;
    name: string;
    username: string;
    role: string;
    avatar: string;
};
type MessagePage = { room: Room; messages: Message[]; has_more: boolean };
const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('');
const timeLabel = (value: string) =>
    new Date(value).toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

function ChatAvatar({
    name,
    avatar,
    group = false,
}: {
    name: string;
    avatar: string;
    group?: boolean;
}) {
    return (
        <Avatar className="size-10 shrink-0">
            <AvatarImage src={avatar} alt={name} />
            <AvatarFallback className="bg-red-100 text-sm font-bold text-red-800">
                {group ? <Users size={19} /> : initials(name)}
            </AvatarFallback>
        </Avatar>
    );
}

export default function DiverText({
    initialRooms,
    embedded = false,
}: {
    initialRooms: Room[];
    embedded?: boolean;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [rooms, setRooms] = useState(initialRooms);
    const [selected, setSelected] = useState<Room | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [drafts, setDrafts] = useState<Record<number, string>>({});
    const [loading, setLoading] = useState(false);
    const [olderLoading, setOlderLoading] = useState(false);
    const [hasOlder, setHasOlder] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [listError, setListError] = useState('');
    const [newOpen, setNewOpen] = useState(false);
    const [peopleSearch, setPeopleSearch] = useState('');
    const [people, setPeople] = useState<Person[]>([]);
    const [peopleLoading, setPeopleLoading] = useState(false);
    const [peopleError, setPeopleError] = useState('');
    const [starting, setStarting] = useState(false);
    const active = useRef<number | null>(null);
    const loaded = useRef<Message[]>([]);
    const scroller = useRef<HTMLDivElement>(null);
    const atBottom = useRef(true);
    const lastRead = useRef<Record<number, number>>({});
    const pendingSend = useRef<{
        room: number;
        body: string;
        id: string;
    } | null>(null);
    const mounted = useRef(true);
    const roomId = selected?.id;
    useEffect(() => {
        mounted.current = true;

        return () => {
            mounted.current = false;
            active.current = null;
        };
    }, []);

    const refreshRooms = useCallback(async () => {
        try {
            const next = await chatRequest<Room[]>('/divertext/rooms');

            if (!mounted.current) {
                return;
            }

            setRooms(next);
            setListError('');

            if (
                active.current &&
                !next.some((room) => room.id === active.current)
            ) {
                active.current = null;
                loaded.current = [];
                setSelected(null);
                setMessages([]);
                setListError(
                    'Your conversation access changed. Choose another conversation.',
                );
            }
        } catch (reason) {
            if (mounted.current) {
                setListError(
                    reason instanceof Error
                        ? reason.message
                        : 'Could not refresh conversations.',
                );
            }
        }
    }, []);
    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                void refreshRooms();
            }
        };
        const timer = window.setInterval(refresh, 6000);
        window.addEventListener('focus', refresh);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('focus', refresh);
        };
    }, [refreshRooms]);

    const markSeen = useCallback(
        async (id: number, last: number) => {
            const topDialog = Array.from(
                document.querySelectorAll('[role="dialog"]'),
            ).at(-1);

            if (
                (topDialog &&
                    (!embedded ||
                        topDialog.getAttribute('aria-labelledby') !==
                            'message-widget-title')) ||
                !last ||
                active.current !== id ||
                document.visibilityState !== 'visible' ||
                !document.hasFocus() ||
                !atBottom.current ||
                (lastRead.current[id] ?? 0) >= last
            ) {
                return;
            }

            try {
                await chatRequest(`/divertext/${id}/read`, 'PATCH', {
                    message_id: last,
                });

                if (!mounted.current) {
                    return;
                }

                lastRead.current[id] = Math.max(
                    lastRead.current[id] ?? 0,
                    last,
                );
                window.dispatchEvent(new Event('divertext:read'));
                void refreshRooms();
            } catch {
                /* Retry on the next visible refresh; keep the unread badge. */
            }
        },
        [refreshRooms, embedded],
    );

    const fetchMessages = useCallback(
        async (
            id: number,
            mode: 'initial' | 'new' | 'older',
            signal?: AbortSignal,
        ) => {
            const snapshot = loaded.current;
            const cursor =
                mode === 'older' ? snapshot[0]?.id : snapshot.at(-1)?.id;
            const query =
                mode === 'initial'
                    ? ''
                    : mode === 'older'
                      ? `?before=${cursor}`
                      : `?after=${cursor ?? 0}`;
            const previousHeight = scroller.current?.scrollHeight ?? 0;

            try {
                const data = await chatRequest<MessagePage>(
                    `/divertext/${id}/messages${query}`,
                    'GET',
                    undefined,
                    signal,
                );

                if (
                    active.current !== id ||
                    signal?.aborted ||
                    !mounted.current
                ) {
                    return;
                }

                const combined =
                    mode === 'initial'
                        ? data.messages
                        : [...loaded.current, ...data.messages];
                const next = [
                    ...new Map(
                        combined.map((message) => [message.id, message]),
                    ).values(),
                ].sort((a, b) => a.id - b.id);
                loaded.current = next;
                setMessages(next);
                setSelected(data.room);
                setError('');

                if (mode !== 'new') {
                    setHasOlder(data.has_more);
                }

                const scrollDown =
                    mode === 'initial' || (mode === 'new' && atBottom.current);
                requestAnimationFrame(() => {
                    if (active.current !== id || !scroller.current) {
                        return;
                    }

                    if (scrollDown) {
                        scroller.current.scrollTop =
                            scroller.current.scrollHeight;
                        atBottom.current = true;
                    }

                    if (mode === 'older') {
                        scroller.current.scrollTop +=
                            scroller.current.scrollHeight - previousHeight;
                    }

                    if (
                        mode !== 'older' &&
                        (!data.has_more || mode === 'initial')
                    ) {
                        void markSeen(id, next.at(-1)?.id ?? 0);
                    }
                });
            } catch (reason) {
                if (
                    active.current === id &&
                    !signal?.aborted &&
                    mounted.current
                ) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Unable to load messages.',
                    );
                }
            } finally {
                if (
                    active.current === id &&
                    mounted.current &&
                    !signal?.aborted
                ) {
                    setLoading(false);
                    setOlderLoading(false);
                }
            }
        },
        [markSeen],
    );

    useEffect(() => {
        if (!roomId) {
            return;
        }

        const controller = new AbortController();
        let running = false;
        const refresh = async (initial = false) => {
            if (
                running ||
                (!initial && document.visibilityState !== 'visible')
            ) {
                return;
            }

            running = true;
            await fetchMessages(
                roomId,
                initial ? 'initial' : 'new',
                controller.signal,
            );
            running = false;
        };
        const initial = window.setTimeout(() => void refresh(true), 0);
        const timer = window.setInterval(() => void refresh(), 4000);
        const focus = () => void refresh();
        window.addEventListener('focus', focus);

        return () => {
            controller.abort();
            window.clearTimeout(initial);
            window.clearInterval(timer);
            window.removeEventListener('focus', focus);
        };
    }, [roomId, fetchMessages]);

    useEffect(() => {
        if (!newOpen) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setPeopleLoading(true);
            setPeopleError('');

            try {
                const data = await chatRequest<Person[]>(
                    `/divertext/people?search=${encodeURIComponent(peopleSearch)}`,
                    'GET',
                    undefined,
                    controller.signal,
                );

                if (!controller.signal.aborted) {
                    setPeople(data);
                }
            } catch (reason) {
                if (!controller.signal.aborted) {
                    setPeopleError(
                        reason instanceof Error
                            ? reason.message
                            : 'Unable to search employees.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setPeopleLoading(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [newOpen, peopleSearch]);

    const choose = (room: Room) => {
        if (active.current === room.id) {
            return;
        }

        active.current = room.id;
        loaded.current = [];
        atBottom.current = true;
        setSelected(room);
        setMessages([]);
        setLoading(true);
        setHasOlder(false);
        setError('');
    };
    const start = async (person: Person) => {
        setStarting(true);
        setPeopleError('');

        try {
            const room = await chatRequest<Room>('/divertext/direct', 'POST', {
                user_id: person.id,
            });

            if (!mounted.current) {
                return;
            }

            setRooms((current) => [
                room,
                ...current.filter((item) => item.id !== room.id),
            ]);
            choose(room);
            setNewOpen(false);
        } catch (reason) {
            setPeopleError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to start conversation.',
            );
        } finally {
            setStarting(false);
        }
    };
    const send = async () => {
        if (!selected || sending || loading || !selected.can_send) {
            return;
        }

        const id = selected.id;
        const body = (drafts[id] ?? '').trim();

        if (!body) {
            return;
        }

        if (
            pendingSend.current?.room !== id ||
            pendingSend.current.body !== body
        ) {
            pendingSend.current = { room: id, body, id: crypto.randomUUID() };
        }

        setSending(true);
        setError('');

        try {
            await chatRequest<Message>(`/divertext/${id}/messages`, 'POST', {
                body,
                request_id: pendingSend.current.id,
            });

            if (!mounted.current) {
                return;
            }

            pendingSend.current = null;
            setDrafts((current) => ({ ...current, [id]: '' }));

            if (active.current === id) {
                atBottom.current = true;
                await fetchMessages(id, 'new');
            }

            void refreshRooms();
        } catch (reason) {
            if (active.current === id) {
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Message was not sent. Please retry.',
                );
            }
        } finally {
            if (mounted.current) {
                setSending(false);
            }
        }
    };
    const filtered = rooms.filter(
        (room) =>
            (filter === 'all' || room.type === filter) &&
            `${room.name} ${room.subtitle}`
                .toLowerCase()
                .includes(search.toLowerCase()),
    );

    return (
        <>
            {!embedded && <Head title="DiverText" />}
            <main
                className={`flex min-h-0 min-w-0 flex-1 flex-col ${embedded ? 'gap-2 p-2' : 'divertext-workspace gap-4 p-3 sm:p-5 lg:p-6'}`}
            >
                <header className="flex shrink-0 items-center justify-between gap-3">
                    <div className={embedded ? 'hidden' : ''}>
                        <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900">
                            <MessagesSquare className="text-red-800" />
                            DiverText
                        </h1>
                        <p className="mt-1 hidden text-sm text-slate-500 sm:block">
                            Stay connected with your colleagues and teams.
                        </p>
                    </div>
                    <Button
                        variant="contained"
                        startIcon={<Plus size={17} />}
                        onClick={() => {
                            setNewOpen(true);
                            setPeopleSearch('');
                            setPeople([]);
                        }}
                    >
                        New message
                    </Button>
                </header>
                <div
                    className={`grid min-h-0 flex-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ${embedded ? '' : 'md:grid-cols-[300px_minmax(0,1fr)] lg:grid-cols-[340px_minmax(0,1fr)]'}`}
                >
                    <aside
                        className={`${selected ? (embedded ? 'hidden' : 'hidden md:flex') : 'flex'} min-h-0 flex-col border-r border-slate-200`}
                    >
                        <div className="space-y-3 border-b border-slate-100 p-4">
                            <div className="relative">
                                <Search
                                    className="absolute top-3 left-3 text-slate-400"
                                    size={17}
                                />
                                <input
                                    aria-label="Search conversations"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search conversations"
                                    className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pr-3 pl-10 text-sm outline-red-700"
                                />
                            </div>
                            <div className="flex gap-2">
                                {[
                                    ['all', 'All'],
                                    ['direct', 'Direct'],
                                    ['team', 'Teams'],
                                ].map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        aria-pressed={filter === value}
                                        onClick={() => setFilter(value)}
                                        className={`rounded-full px-4 py-1.5 text-xs font-bold ${filter === value ? 'bg-red-800 text-white' : 'bg-slate-100 text-slate-600'}`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        {listError && (
                            <Alert severity="warning">{listError}</Alert>
                        )}
                        <div
                            className="min-h-0 flex-1 overflow-y-auto p-2"
                            aria-label="Conversations"
                        >
                            {filtered.map((room) => (
                                <button
                                    key={room.id}
                                    type="button"
                                    aria-label={`Open ${room.name}`}
                                    onClick={() => choose(room)}
                                    className={`mb-1 flex w-full items-center gap-3 rounded-xl p-3 text-left ${selected?.id === room.id ? 'bg-red-50 ring-1 ring-red-100 ring-inset' : 'hover:bg-slate-50'}`}
                                >
                                    <ChatAvatar
                                        name={room.name}
                                        avatar={room.avatar}
                                        group={room.type === 'team'}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-bold text-slate-800">
                                            {room.name}
                                        </p>
                                        <p className="mt-1 truncate text-xs text-slate-500">
                                            {room.latest_message
                                                ? `${room.latest_message.sender_name}: ${room.latest_message.body}`
                                                : room.subtitle}
                                        </p>
                                    </div>
                                    {room.unread_count > 0 && (
                                        <span
                                            className="rounded-full bg-red-700 px-2 py-0.5 text-xs font-bold text-white"
                                            aria-label={`${room.unread_count} unread`}
                                        >
                                            {room.unread_count > 99
                                                ? '99+'
                                                : room.unread_count}
                                        </span>
                                    )}
                                </button>
                            ))}
                            {!filtered.length && (
                                <p className="px-4 py-10 text-center text-sm leading-6 text-slate-500">
                                    {search
                                        ? 'No conversations match your search.'
                                        : 'Start a direct message. Your assigned team chats will also appear here.'}
                                </p>
                            )}
                        </div>
                    </aside>
                    <section
                        className={`${selected ? 'flex' : embedded ? 'hidden' : 'hidden md:flex'} min-h-0 min-w-0 flex-col`}
                        aria-label="Chat"
                    >
                        {selected ? (
                            <>
                                <div className="flex shrink-0 items-center gap-3 border-b border-slate-200 p-4">
                                    <IconButton
                                        aria-label="Back to conversations"
                                        className={embedded ? '' : 'md:!hidden'}
                                        onClick={() => {
                                            active.current = null;
                                            setSelected(null);
                                            setMessages([]);
                                        }}
                                    >
                                        <ArrowLeft size={20} />
                                    </IconButton>
                                    <ChatAvatar
                                        name={selected.name}
                                        avatar={selected.avatar}
                                        group={selected.type === 'team'}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <h2 className="truncate font-bold text-slate-900">
                                            {selected.name}
                                        </h2>
                                        <p className="truncate text-xs text-slate-500">
                                            {selected.type === 'team'
                                                ? 'Team members, team leader, and admins'
                                                : 'Private conversation'}{' '}
                                            · {selected.subtitle}
                                        </p>
                                    </div>
                                    <IconButton
                                        aria-label="Refresh messages"
                                        disabled={loading}
                                        onClick={() =>
                                            void fetchMessages(
                                                selected.id,
                                                loaded.current.length
                                                    ? 'new'
                                                    : 'initial',
                                            )
                                        }
                                    >
                                        <RefreshCw size={18} />
                                    </IconButton>
                                </div>
                                {error && (
                                    <Alert
                                        severity="error"
                                        className="shrink-0"
                                    >
                                        {error}
                                    </Alert>
                                )}
                                <div
                                    ref={scroller}
                                    className="min-h-0 flex-1 overflow-y-auto bg-gradient-to-b from-red-50/40 to-slate-50 p-4 sm:p-6"
                                    onScroll={() => {
                                        const box = scroller.current;

                                        if (box) {
                                            atBottom.current =
                                                box.scrollHeight -
                                                    box.scrollTop -
                                                    box.clientHeight <
                                                60;

                                            if (
                                                atBottom.current &&
                                                active.current
                                            ) {
                                                void markSeen(
                                                    active.current,
                                                    loaded.current.at(-1)?.id ??
                                                        0,
                                                );
                                            }
                                        }
                                    }}
                                >
                                    {hasOlder && (
                                        <div className="mb-4 text-center">
                                            <Button
                                                size="small"
                                                disabled={olderLoading}
                                                onClick={() => {
                                                    setOlderLoading(true);
                                                    void fetchMessages(
                                                        selected.id,
                                                        'older',
                                                    );
                                                }}
                                            >
                                                {olderLoading
                                                    ? 'Loading…'
                                                    : 'Load older messages'}
                                            </Button>
                                        </div>
                                    )}
                                    {loading && (
                                        <p
                                            role="status"
                                            className="py-10 text-center text-sm text-slate-500"
                                        >
                                            Loading conversation…
                                        </p>
                                    )}
                                    {!loading && !messages.length && (
                                        <div className="py-16 text-center text-slate-500">
                                            <MessageCircle
                                                className="mx-auto mb-3 text-red-300"
                                                size={36}
                                            />
                                            <p className="font-semibold">
                                                Start the conversation
                                            </p>
                                            <p className="mt-2 text-sm">
                                                Say hello or share an update
                                                with {selected.name}.
                                            </p>
                                        </div>
                                    )}
                                    <div
                                        className="space-y-4"
                                        aria-label="Messages"
                                    >
                                        {messages.map((message) => {
                                            const own =
                                                message.sender_id ===
                                                auth.user.id;

                                            return (
                                                <div
                                                    key={message.id}
                                                    className={`flex ${own ? 'justify-end' : 'justify-start'}`}
                                                >
                                                    <div className="max-w-[90%] sm:max-w-[80%]">
                                                        <p
                                                            className={`mb-1 text-xs font-semibold text-slate-500 ${own ? 'text-right' : ''}`}
                                                        >
                                                            {own
                                                                ? 'You'
                                                                : message.sender_name}
                                                        </p>
                                                        <p
                                                            className={`divertext-message rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm ${own ? 'rounded-tr-sm bg-red-800 text-white' : 'rounded-tl-sm border border-slate-200 bg-white text-slate-800'}`}
                                                        >
                                                            {message.body}
                                                        </p>
                                                        <p
                                                            className={`mt-1 text-[10px] text-slate-400 ${own ? 'text-right' : ''}`}
                                                        >
                                                            {timeLabel(
                                                                message.created_at,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                                <form
                                    className="shrink-0 border-t border-slate-200 bg-white p-3 sm:p-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        void send();
                                    }}
                                >
                                    {!selected.can_send ? (
                                        <p className="text-sm text-slate-500">
                                            This employee is no longer active.
                                            Message history is still available.
                                        </p>
                                    ) : (
                                        <>
                                            <div className="flex items-end gap-2">
                                                <TextField
                                                    fullWidth
                                                    multiline
                                                    minRows={1}
                                                    maxRows={4}
                                                    placeholder="Write a message…"
                                                    value={
                                                        drafts[selected.id] ??
                                                        ''
                                                    }
                                                    disabled={sending}
                                                    onChange={(event) =>
                                                        setDrafts(
                                                            (current) => ({
                                                                ...current,
                                                                [selected.id]:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    slotProps={{
                                                        htmlInput: {
                                                            'aria-label':
                                                                'Message',
                                                            maxLength: 4000,
                                                        },
                                                    }}
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                                'Enter' &&
                                                            !event.shiftKey &&
                                                            !event.nativeEvent
                                                                .isComposing
                                                        ) {
                                                            event.preventDefault();
                                                            void send();
                                                        }
                                                    }}
                                                />
                                                <Button
                                                    type="submit"
                                                    variant="contained"
                                                    aria-label="Send message"
                                                    disabled={
                                                        sending ||
                                                        loading ||
                                                        !(
                                                            drafts[
                                                                selected.id
                                                            ] ?? ''
                                                        ).trim()
                                                    }
                                                    sx={{
                                                        minWidth: 48,
                                                        height: 48,
                                                    }}
                                                >
                                                    <Send size={20} />
                                                </Button>
                                            </div>
                                            <p className="mt-2 text-[10px] text-slate-400">
                                                Enter to send · Shift + Enter
                                                for a new line ·{' '}
                                                {
                                                    (drafts[selected.id] ?? '')
                                                        .length
                                                }
                                                /4,000
                                            </p>
                                        </>
                                    )}
                                </form>
                            </>
                        ) : (
                            <div className="m-auto p-10 text-center">
                                <MessagesSquare
                                    size={52}
                                    className="mx-auto mb-5 text-red-200"
                                />
                                <h2 className="text-xl font-bold text-slate-800">
                                    Your team, one conversation away
                                </h2>
                                <p className="mt-3 max-w-sm text-sm leading-6 text-slate-500">
                                    Choose a conversation or start a new direct
                                    message. Messages update automatically.
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
            <Dialog
                open={newOpen}
                onClose={() => {
                    if (!starting) {
                        setNewOpen(false);
                    }
                }}
                fullWidth
                maxWidth="sm"
                scroll="paper"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '85dvh' } },
                }}
            >
                <DialogTitle sx={{ fontWeight: 800, pr: 7 }}>
                    New direct message
                </DialogTitle>
                <IconButton
                    aria-label="Close new message"
                    disabled={starting}
                    onClick={() => setNewOpen(false)}
                    sx={{ position: 'absolute', right: 12, top: 12 }}
                >
                    <X size={20} />
                </IconButton>
                <DialogContent dividers>
                    <TextField
                        fullWidth
                        autoFocus
                        label="Search employee name or ID"
                        value={peopleSearch}
                        onChange={(event) =>
                            setPeopleSearch(event.target.value)
                        }
                        slotProps={{ htmlInput: { maxLength: 100 } }}
                    />
                    <p className="mt-3 text-xs text-slate-500">
                        Select an active employee. Showing up to 50 matches.
                    </p>
                    {peopleError && (
                        <Alert severity="error" sx={{ mt: 2 }}>
                            {peopleError}
                        </Alert>
                    )}
                    <div className="mt-4 space-y-1">
                        {peopleLoading && (
                            <p
                                role="status"
                                className="py-4 text-center text-sm text-slate-500"
                            >
                                Searching…
                            </p>
                        )}
                        {people.map((person) => (
                            <button
                                type="button"
                                key={person.id}
                                disabled={starting}
                                onClick={() => void start(person)}
                                className="flex w-full items-center gap-3 rounded-xl p-3 text-left hover:bg-red-50 disabled:opacity-50"
                            >
                                <ChatAvatar
                                    name={person.name}
                                    avatar={person.avatar}
                                />
                                <div className="min-w-0">
                                    <p className="font-semibold break-words text-slate-800">
                                        {person.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {person.username} ·{' '}
                                        {person.role.replaceAll('_', ' ')}
                                    </p>
                                </div>
                            </button>
                        ))}
                        {!peopleLoading && !people.length && (
                            <p className="py-6 text-center text-sm text-slate-500">
                                No employees found.
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

DiverText.layout = {
    breadcrumbs: [{ title: 'DiverText', href: '/divertext' }],
};
