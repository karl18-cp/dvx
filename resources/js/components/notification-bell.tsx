import { usePage } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
} from '@mui/material';
import { Bell, Check, RefreshCw, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Item = {
    id: number;
    title: string;
    type: string;
    message: string;
    read_at: string | null;
    created_at: string;
};
type Feed = {
    unread_count: number;
    notifications: { data: Item[]; current_page: number; last_page: number };
};

export function NotificationBell() {
    const shared = usePage().props.assessment_notifications as
        { unread_count: number } | undefined;
    const [open, setOpen] = useState(false);
    const [feed, setFeed] = useState<Feed | null>(null);
    const [page, setPage] = useState(1);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [loading, setLoading] = useState(false);
    const pending = useRef<AbortController | null>(null);
    const unread = feed?.unread_count ?? shared?.unread_count ?? 0;
    const load = useCallback(async () => {
        pending.current?.abort();
        const controller = new AbortController();
        pending.current = controller;
        setLoading(true);

        try {
            const response = await fetch(`/notification-feed?page=${page}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(
                    'Could not load notifications. Please try again.',
                );
            }

            const data: Feed = await response.json();

            if (!controller.signal.aborted) {
                setFeed(data);
                setError('');
            }
        } catch (reason) {
            if (!controller.signal.aborted) {
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Could not load notifications.',
                );
            }
        } finally {
            if (!controller.signal.aborted) {
                setLoading(false);
            }
        }
    }, [page]);
    useEffect(() => {
        const initial = window.setTimeout(() => void load(), 0);
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                void load();
            }
        };
        const timer = window.setInterval(refresh, 30000);
        window.addEventListener('focus', refresh);

        return () => {
            window.clearTimeout(initial);
            window.clearInterval(timer);
            window.removeEventListener('focus', refresh);
            pending.current?.abort();
        };
    }, [load]);
    const markRead = async (item?: Item) => {
        setBusy(true);

        try {
            const token = document.cookie
                .split('; ')
                .find((value) => value.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);
            const response = await fetch(
                item
                    ? `/notification-feed/${item.id}/read`
                    : '/notification-feed/read',
                {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                    },
                    body: JSON.stringify(
                        item
                            ? {}
                            : {
                                  ids:
                                      feed?.notifications.data
                                          .filter((entry) => !entry.read_at)
                                          .map((entry) => entry.id) ?? [],
                              },
                    ),
                },
            );

            if (!response.ok) {
                throw new Error(
                    'Could not mark notifications as read. Please try again.',
                );
            }

            await load();
        } catch (reason) {
            setError(
                reason instanceof Error ? reason.message : 'Unable to save.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => {
                    setOpen(true);
                    void load();
                }}
                aria-label={`Notifications, ${unread} unread`}
                aria-haspopup="dialog"
                className={`relative rounded-xl p-2.5 transition-colors focus-visible:outline-2 focus-visible:outline-red-700 ${unread ? 'bg-red-100 text-red-700 shadow-[0_0_16px_rgba(220,38,38,0.4)] ring-1 ring-red-300' : 'hover:bg-black/5'}`}
            >
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 rounded-xl bg-red-400/20 motion-safe:animate-pulse"
                    />
                )}
                <Bell className="relative size-5" />
                {unread > 0 && (
                    <span className="absolute -top-1 -right-1 min-w-5 rounded-full bg-red-700 px-1 text-center text-xs font-bold text-white">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                fullWidth
                maxWidth="sm"
                scroll="paper"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '85dvh' } },
                }}
            >
                <DialogTitle sx={{ fontWeight: 800, pr: 7 }}>
                    Notifications{' '}
                    <span className="ml-2 text-sm font-medium text-red-700">
                        {unread} unread
                    </span>
                </DialogTitle>
                <IconButton
                    aria-label="Close notifications"
                    onClick={() => setOpen(false)}
                    sx={{ position: 'absolute', top: 12, right: 12 }}
                >
                    <X size={20} />
                </IconButton>
                <div className="flex flex-wrap justify-between gap-2 px-5 pb-3">
                    <Button
                        size="small"
                        disabled={loading || busy}
                        startIcon={<RefreshCw size={15} />}
                        onClick={() => void load()}
                    >
                        Refresh
                    </Button>
                    <Button
                        size="small"
                        disabled={
                            busy ||
                            !feed?.notifications.data.some(
                                (item) => !item.read_at,
                            )
                        }
                        onClick={() => void markRead()}
                    >
                        Mark displayed as read
                    </Button>
                </div>
                <DialogContent dividers>
                    {error && (
                        <Alert severity="error" sx={{ mb: 2 }}>
                            {error}
                        </Alert>
                    )}
                    {!feed && loading && (
                        <p className="py-8 text-center text-sm text-slate-500">
                            Loading notifications…
                        </p>
                    )}
                    <div className="space-y-3">
                        {feed?.notifications.data.map((item) => (
                            <article
                                key={item.id}
                                className={`rounded-xl border p-4 ${item.read_at ? 'border-slate-200 bg-white' : 'border-red-200 bg-red-50'}`}
                            >
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <span className="text-xs font-semibold text-red-800 uppercase">
                                        {item.type.replaceAll('_', ' ')}
                                    </span>
                                    {!item.read_at && (
                                        <span className="rounded-full bg-red-700 px-2 py-0.5 text-xs text-white">
                                            Unread
                                        </span>
                                    )}
                                </div>
                                <h3 className="font-bold break-words text-slate-900">
                                    {item.title}
                                </h3>
                                <p className="mt-2 text-sm leading-6 break-words whitespace-pre-wrap text-slate-600">
                                    {item.message}
                                </p>
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <time className="text-xs text-slate-500">
                                        {new Date(
                                            item.created_at,
                                        ).toLocaleString('en-US', {
                                            timeZone: 'Asia/Manila',
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })}
                                    </time>
                                    {!item.read_at && (
                                        <Button
                                            size="small"
                                            disabled={busy}
                                            startIcon={<Check size={14} />}
                                            onClick={() => void markRead(item)}
                                        >
                                            Mark as read
                                        </Button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                    {feed && !feed.notifications.data.length && (
                        <p className="py-10 text-center text-sm text-slate-500">
                            You’re all caught up. No notifications to show.
                        </p>
                    )}
                </DialogContent>
                <DialogActions
                    sx={{
                        justifyContent: 'center',
                        flexWrap: 'wrap',
                        p: 2,
                    }}
                >
                    <div className="flex items-center gap-1">
                        <Button
                            disabled={busy || page <= 1}
                            onClick={() => setPage(page - 1)}
                        >
                            Previous
                        </Button>
                        <span className="text-xs text-slate-500">
                            {page} / {feed?.notifications.last_page ?? 1}
                        </span>
                        <Button
                            disabled={
                                busy ||
                                !feed ||
                                page >= feed.notifications.last_page
                            }
                            onClick={() => setPage(page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </DialogActions>
            </Dialog>
        </>
    );
}
