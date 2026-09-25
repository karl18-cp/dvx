import { router } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
} from '@mui/material';
import { CircleHelp, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmationContext } from '@/hooks/use-confirmation';

export function ConfirmationProvider({ children }: { children: ReactNode }) {
    const [message, setMessage] = useState<string | null>(null);
    const pending = useRef<((confirmed: boolean) => void) | null>(null);
    const settle = useCallback((confirmed: boolean) => {
        const resolve = pending.current;
        pending.current = null;
        setMessage(null);
        resolve?.(confirmed);
    }, []);
    const confirmAction = useCallback(
        (description: string): Promise<boolean> => {
            // Ignore repeated clicks while the current decision is still pending.
            if (pending.current) {
                return Promise.resolve(false);
            }

            return new Promise((resolve) => {
                pending.current = resolve;
                setMessage(description);
            });
        },
        [],
    );

    useEffect(() => {
        const unsubscribe = router.on('navigate', () => settle(false));

        return () => {
            unsubscribe();
            pending.current?.(false);
            pending.current = null;
        };
    }, [settle]);

    const action = message?.match(
        /^(Delete|Remove|Detach|Archive|Publish|Clone|Complete|Transfer|Submit)\b/i,
    )?.[1];
    const destructive = /^(Delete|Remove|Detach|Archive)\b/i.test(
        message ?? '',
    );
    const Icon = destructive ? TriangleAlert : CircleHelp;

    return (
        <ConfirmationContext.Provider value={confirmAction}>
            {children}
            <Dialog
                open={message !== null}
                onClose={() => settle(false)}
                fullWidth
                maxWidth="xs"
                aria-labelledby="confirmation-title"
                aria-describedby="confirmation-description"
                slotProps={{
                    paper: {
                        sx: { borderRadius: 3, borderTop: '4px solid #ae1b20' },
                    },
                }}
            >
                <DialogTitle
                    id="confirmation-title"
                    className="flex items-center gap-3"
                >
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[#ae1b20]">
                        <Icon className="size-5" />
                    </span>
                    {action
                        ? `Confirm ${action.toLowerCase()}`
                        : 'Confirm action'}
                </DialogTitle>
                <DialogContent>
                    <DialogContentText
                        id="confirmation-description"
                        className="whitespace-pre-line"
                    >
                        {message}
                    </DialogContentText>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 3, gap: 1 }}>
                    <Button
                        autoFocus
                        variant="outlined"
                        color="inherit"
                        onClick={() => settle(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        onClick={() => settle(true)}
                        sx={{
                            bgcolor: '#ae1b20',
                            '&:hover': { bgcolor: '#8e151a' },
                        }}
                    >
                        {action ?? 'Confirm'}
                    </Button>
                </DialogActions>
            </Dialog>
        </ConfirmationContext.Provider>
    );
}
