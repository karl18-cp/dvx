import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import DivertexLogo from '@/components/divertex-logo';

const SHOW_DELAY_MS = 70;
const MIN_VISIBLE_MS = 360;

type LottieAnimation = { destroy: () => void };
type LottiePlayer = {
    loadAnimation: (options: {
        container: HTMLElement;
        renderer: 'svg';
        loop: boolean;
        autoplay: boolean;
        path: string;
    }) => LottieAnimation;
};

declare global {
    interface Window {
        lottie?: LottiePlayer;
    }
}

let playerPromise: Promise<LottiePlayer> | null = null;

function loadLottiePlayer() {
    if (window.lottie) {
        return Promise.resolve(window.lottie);
    }

    if (!playerPromise) {
        playerPromise = new Promise((resolve, reject) => {
            const existing = document.querySelector<HTMLScriptElement>(
                'script[data-dvx-lottie]',
            );
            const script = existing ?? document.createElement('script');
            const loaded = () =>
                window.lottie
                    ? resolve(window.lottie)
                    : reject(new Error('Lottie player did not initialize.'));

            script.addEventListener('load', loaded, { once: true });
            script.addEventListener(
                'error',
                () => reject(new Error('Lottie player could not be loaded.')),
                { once: true },
            );

            if (!existing) {
                script.src = '/vendor/lottie-light.min.js';
                script.async = true;
                script.dataset.dvxLottie = 'true';
                document.head.appendChild(script);
            }
        });
    }

    return playerPromise;
}

function LoaderAnimation() {
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        let cancelled = false;
        let animation: LottieAnimation | null = null;

        loadLottiePlayer()
            .then((player) => {
                if (!cancelled && container.current) {
                    animation = player.loadAnimation({
                        container: container.current,
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: '/animations/mine-loader.json',
                    });
                }
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;
            animation?.destroy();
        };
    }, []);

    return <div ref={container} className="h-20 w-20" aria-hidden="true" />;
}

export default function PageLoadingScreen() {
    const [visible, setVisible] = useState(false);
    const visibleRef = useRef(false);
    const navigationRef = useRef(false);
    const shownAtRef = useRef(0);
    const showTimerRef = useRef<number | null>(null);
    const hideTimerRef = useRef<number | null>(null);

    useEffect(() => {
        loadLottiePlayer().catch(() => undefined);
        fetch('/animations/mine-loader.json', { cache: 'force-cache' }).catch(
            () => undefined,
        );

        const clearTimer = (timer: React.MutableRefObject<number | null>) => {
            if (timer.current !== null) {
                window.clearTimeout(timer.current);
                timer.current = null;
            }
        };

        const stopStarting = router.on('start', (event) => {
            if (event.detail.visit.method.toLowerCase() !== 'get') {
                return;
            }

            navigationRef.current = true;
            clearTimer(showTimerRef);
            clearTimer(hideTimerRef);
            showTimerRef.current = window.setTimeout(() => {
                if (!navigationRef.current) {
                    return;
                }

                shownAtRef.current = Date.now();
                visibleRef.current = true;
                setVisible(true);
            }, SHOW_DELAY_MS);
        });

        const stopFinishing = router.on('finish', () => {
            if (!navigationRef.current) {
                return;
            }

            navigationRef.current = false;
            clearTimer(showTimerRef);

            if (!visibleRef.current) {
                return;
            }

            const remaining = Math.max(
                0,
                MIN_VISIBLE_MS - (Date.now() - shownAtRef.current),
            );
            hideTimerRef.current = window.setTimeout(() => {
                visibleRef.current = false;
                setVisible(false);
            }, remaining);
        });

        return () => {
            stopStarting();
            stopFinishing();
            clearTimer(showTimerRef);
            clearTimer(hideTimerRef);
        };
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-[10000] grid place-items-center overflow-hidden bg-[#120d18]/94 px-6 backdrop-blur-md"
            role="status"
            aria-live="polite"
            aria-label="Loading the next page"
        >
            <div className="pointer-events-none absolute -top-32 -right-24 h-96 w-96 rounded-full bg-red-700/25 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-36 -left-24 h-96 w-96 rounded-full bg-rose-400/15 blur-3xl" />

            <div className="relative flex w-full max-w-[330px] flex-col items-center rounded-[2rem] border border-white/10 bg-white/[0.07] px-8 py-9 text-center shadow-2xl shadow-black/40">
                <DivertexLogo className="mb-5 w-36 drop-shadow-[0_10px_22px_rgba(0,0,0,0.35)]" />
                <div className="relative grid h-28 w-28 place-items-center rounded-full border border-red-400/25 bg-red-950/35 shadow-[0_0_45px_rgba(220,38,38,0.22)]">
                    <div className="absolute inset-2 rounded-full border border-white/[0.06]" />
                    <LoaderAnimation />
                </div>
                <p className="mt-5 text-base font-bold tracking-wide text-white">
                    Loading your workspace
                </p>
                <p className="mt-1 text-sm text-white/55">
                    One moment, please.
                </p>
                <span className="mt-5 h-1 w-24 overflow-hidden rounded-full bg-white/10">
                    <span className="dvx-loading-bar block h-full w-1/2 rounded-full bg-gradient-to-r from-red-500 to-rose-300" />
                </span>
            </div>
        </div>
    );
}
