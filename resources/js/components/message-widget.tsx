import { Alert, Button, Dialog, DialogTitle, IconButton } from '@mui/material';
import { MessagesSquare, X } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { useDiverTextUnread } from '@/components/divertext-unread-provider';
import { chatRequest } from '@/lib/divertext';
import type { Room } from '@/pages/divertext';

const DiverText = lazy(() => import('@/pages/divertext'));

export function MessageWidget() {
    const unread = useDiverTextUnread();
    const [open, setOpen] = useState(false);
    const [rooms, setRooms] = useState<Room[] | null>(null);
    const [error, setError] = useState('');
    const pending = useRef<AbortController | null>(null);
    useEffect(() => () => pending.current?.abort(), []);
    const show = async () => {
        pending.current?.abort();
        const controller = new AbortController();
        pending.current = controller;
        setOpen(true);
        setRooms(null);
        setError('');

        try {
            const result = await chatRequest<Room[]>(
                '/divertext/rooms',
                'GET',
                undefined,
                controller.signal,
            );

            if (!controller.signal.aborted) {
                setRooms(result);
            }
        } catch (reason) {
            if (!controller.signal.aborted) {
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Unable to load messages.',
                );
            }
        }
    };
    const close = () => {
        pending.current?.abort();
        setOpen(false);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => void show()}
                aria-label={`Open messages, ${unread} unread`}
                aria-haspopup="dialog"
                title="DiverText messages"
                className={`fixed right-4 bottom-4 z-40 grid size-14 place-items-center rounded-full bg-red-800 text-white transition-shadow focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700 sm:right-6 sm:bottom-6 ${unread > 0 ? 'shadow-[0_0_28px_8px_rgba(220,38,38,0.45)] ring-4 ring-red-300/70' : 'shadow-lg hover:shadow-xl'}`}
            >
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 rounded-full bg-red-400/40 motion-safe:animate-pulse"
                    />
                )}
                <MessagesSquare className="relative" size={25} />
                {unread > 0 && (
                    <span className="absolute -top-1 -right-1 min-w-6 rounded-full border-2 border-white bg-red-600 px-1 text-xs leading-5 font-bold">
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>
            <Dialog
                open={open}
                onClose={close}
                aria-labelledby="message-widget-title"
                maxWidth={false}
                slotProps={{
                    paper: {
                        sx: {
                            position: 'fixed',
                            right: { xs: 12, sm: 24 },
                            bottom: { xs: 82, sm: 94 },
                            m: 0,
                            width: 'min(460px, calc(100vw - 24px))',
                            height: 'min(680px, calc(100dvh - 110px))',
                            maxHeight: 'calc(100dvh - 110px)',
                            borderRadius: 3,
                            display: 'flex',
                            flexDirection: 'column',
                            overflow: 'hidden',
                        },
                    },
                }}
            >
                <DialogTitle
                    id="message-widget-title"
                    sx={{
                        fontWeight: 800,
                        pr: 7,
                        color: 'white',
                        background: 'linear-gradient(120deg, #491c29, #a91e27)',
                    }}
                >
                    DiverText{' '}
                    <span className="ml-2 text-xs font-normal text-red-100">
                        {unread ? `${unread} unread` : 'Messages'}
                    </span>
                </DialogTitle>
                <IconButton
                    aria-label="Close message widget"
                    onClick={close}
                    sx={{
                        position: 'absolute',
                        right: 12,
                        top: 12,
                        color: 'white',
                    }}
                >
                    <X size={20} />
                </IconButton>
                {error ? (
                    <div className="p-4">
                        <Alert severity="error">{error}</Alert>
                        <Button onClick={() => void show()}>Try again</Button>
                    </div>
                ) : rooms ? (
                    <Suspense
                        fallback={
                            <p
                                role="status"
                                className="p-6 text-center text-sm text-slate-500"
                            >
                                Loading messages…
                            </p>
                        }
                    >
                        <DiverText initialRooms={rooms} embedded />
                    </Suspense>
                ) : (
                    <p
                        role="status"
                        className="p-6 text-center text-sm text-slate-500"
                    >
                        Loading messages…
                    </p>
                )}
            </Dialog>
        </>
    );
}
