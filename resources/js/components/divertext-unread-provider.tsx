import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { chatRequest } from '@/lib/divertext';

const UnreadContext = createContext(0);

export const useDiverTextUnread = () => useContext(UnreadContext);

export function DiverTextUnreadProvider({ children }: { children: ReactNode }) {
    const [count, setCount] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        let pending = false;
        const refresh = async () => {
            if (pending || document.visibilityState !== 'visible') {
                return;
            }

            pending = true;

            try {
                const result = await chatRequest<{ count: number }>(
                    '/divertext/unread',
                    'GET',
                    undefined,
                    controller.signal,
                );

                if (!controller.signal.aborted) {
                    setCount(result.count);
                }
            } catch {
                /* Retain the last known unread count if disconnected. */
            } finally {
                pending = false;
            }
        };
        void refresh();
        const timer = window.setInterval(() => void refresh(), 10000);
        const update = () => void refresh();
        window.addEventListener('divertext:read', update);
        window.addEventListener('focus', update);
        document.addEventListener('visibilitychange', update);

        return () => {
            controller.abort();
            window.clearInterval(timer);
            window.removeEventListener('divertext:read', update);
            window.removeEventListener('focus', update);
            document.removeEventListener('visibilitychange', update);
        };
    }, []);

    return (
        <UnreadContext.Provider value={count}>
            {children}
        </UnreadContext.Provider>
    );
}
