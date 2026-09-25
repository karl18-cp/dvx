import { Head } from '@inertiajs/react';
import {
    Alert,
    Button,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
} from '@mui/material';
import {
    Camera,
    Clock,
    Coffee,
    LogIn,
    LogOut,
    ScanFace,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Action = 'time_in' | 'lunch_out' | 'lunch_in' | 'time_out';
type ClockState = {
    date: string;
    now: string;
    schedule: { name: string | null; times: Partial<Record<Action, string>> };
    times: Record<Action, string | null>;
    actions: Action[];
    problem: string | null;
    totalMinutes: number | null;
    faceEnrolled: boolean;
};
type Challenge = { id: string; direction: 'left' | 'right'; expiresIn: number };
const labels: Record<Action, string> = {
    time_in: 'Time in',
    lunch_out: 'Break out',
    lunch_in: 'Break in',
    time_out: 'Time out',
};
const icons = {
    time_in: LogIn,
    lunch_out: Coffee,
    lunch_in: Coffee,
    time_out: LogOut,
};
const time = (value?: string | null) =>
    value
        ? new Date(value).toLocaleTimeString('en-US', {
              timeZone: 'Asia/Manila',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
async function api<T>(url: string, data?: unknown): Promise<T> {
    const token = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    const response = await fetch(url, {
        method: data ? 'POST' : 'GET',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
        },
        ...(data ? { body: JSON.stringify(data) } : {}),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(
            response.status === 419
                ? 'Your session expired. Refresh and sign in again.'
                : body.message || 'Unable to record attendance. Please retry.',
        );
    }

    return body as T;
}

export default function MyAttendance({
    clock: initial,
}: {
    clock: ClockState;
}) {
    const [clock, setClock] = useState(initial);
    const [action, setAction] = useState<Action | null>(null);
    const [challenge, setChallenge] = useState<Challenge | null>(null);
    const [frames, setFrames] = useState<string[]>([]);
    const [ready, setReady] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [cameraError, setCameraError] = useState('');
    const [captureWait, setCaptureWait] = useState(false);
    const video = useRef<HTMLVideoElement>(null);
    const stream = useRef<MediaStream | null>(null);
    const generation = useRef(0);
    const stop = () => {
        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;

        if (video.current) {
            video.current.srcObject = null;
        }
    };

    useEffect(() => {
        if (action) {
            return;
        }

        const refresh = () => {
            if (!document.hidden) {
                void api<ClockState>('/my-attendance/status')
                    .then(setClock)
                    .catch(() => {});
            }
        };
        const interval = setInterval(refresh, 30000);
        window.addEventListener('focus', refresh);

        return () => {
            clearInterval(interval);
            window.removeEventListener('focus', refresh);
        };
    }, [action]);

    useEffect(() => {
        const current = ++generation.current;

        if (!action) {
            return;
        }

        void (async () => {
            try {
                if (
                    !window.isSecureContext ||
                    !navigator.mediaDevices?.getUserMedia
                ) {
                    throw new Error(
                        'Open the HTTPS version of this system to use your camera.',
                    );
                }

                let media: MediaStream | undefined;

                // A just-closed camera can briefly remain busy while its driver releases it.
                for (let attempt = 0; attempt < 3; attempt++) {
                    if (generation.current !== current) {
                        return;
                    }

                    try {
                        media = await navigator.mediaDevices.getUserMedia({
                            audio: false,
                            video: {
                                facingMode: 'user',
                                width: { ideal: 640 },
                                height: { ideal: 480 },
                            },
                        });
                        break;
                    } catch (failure) {
                        if (
                            attempt === 2 ||
                            !(failure instanceof DOMException) ||
                            !['NotReadableError', 'NotFoundError'].includes(
                                failure.name,
                            )
                        ) {
                            throw failure;
                        }

                        await new Promise((resolve) =>
                            setTimeout(resolve, 400 * (attempt + 1)),
                        );
                    }
                }

                if (!media) {
                    return;
                }

                if (generation.current !== current) {
                    media.getTracks().forEach((track) => track.stop());

                    return;
                }

                stream.current = media;

                if (!video.current) {
                    stop();

                    return;
                }

                video.current.srcObject = media;
                await video.current.play();

                if (generation.current !== current) {
                    return;
                }

                setReady(true);
                media.getVideoTracks()[0].onended = () => {
                    setReady(false);
                    setCameraError(
                        'Camera disconnected. Close this dialog and try again.',
                    );
                };
            } catch (failure) {
                if (generation.current !== current) {
                    return;
                }

                stop();
                const name = failure instanceof Error ? failure.name : '';
                setCameraError(
                    name === 'NotAllowedError'
                        ? 'Camera permission was denied. Allow camera access, then close this dialog and try again.'
                        : name === 'NotReadableError'
                          ? 'The camera is busy or unavailable. Close other camera apps and try again.'
                          : name === 'NotFoundError'
                            ? 'No camera was found. Connect a camera and try again.'
                            : failure instanceof Error
                              ? failure.message
                              : 'Unable to start your camera.',
                );
            }
        })();

        return () => {
            generation.current = current + 1;
            stop();
        };
    }, [action]);

    const start = async (next: Action) => {
        setError('');
        setMessage('');
        setBusy(true);

        try {
            if (next === 'lunch_out' || next === 'lunch_in') {
                const result = await api<{
                    clock: ClockState;
                    message: string;
                }>('/my-attendance/break', { action: next });
                setClock(result.clock);
                setMessage(result.message);
            } else {
                const result = await api<Challenge>(
                    '/my-attendance/challenge',
                    { action: next },
                );
                setReady(false);
                setCameraError('');
                setCaptureWait(false);
                setChallenge(result);
                setFrames([]);
                setAction(next);
            }
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'Please try again.',
            );
        } finally {
            setBusy(false);
        }
    };
    const capture = async () => {
        if (
            !video.current ||
            !challenge ||
            !ready ||
            video.current.readyState < 2
        ) {
            return;
        }

        setCaptureWait(true);
        const canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = 480;
        canvas.getContext('2d')!.drawImage(video.current, 0, 0, 640, 480);
        const next = [...frames, canvas.toDataURL('image/jpeg', 0.85)];
        setFrames(next);

        if (next.length < 3) {
            window.setTimeout(() => setCaptureWait(false), 1000);

            return;
        }

        setBusy(true);
        stop();
        setReady(false);

        try {
            const result = await api<{ clock: ClockState; message: string }>(
                '/my-attendance/verify',
                { challenge_id: challenge.id, frames: next },
            );
            setClock(result.clock);
            setMessage(result.message);
            setAction(null);
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'Verification failed.',
            );
            setAction(null);
        } finally {
            setBusy(false);
            setCaptureWait(false);
            setFrames([]);
            setChallenge(null);
        }
    };

    return (
        <>
            <Head title="My Attendance" />
            <main className="min-w-0 flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
                <header>
                    <p className="text-xs font-bold tracking-[0.2em] text-red-700 uppercase">
                        Your workday
                    </p>
                    <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                        <Clock className="text-red-700" />
                        My Attendance
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        Time in and out with your enrolled face. Breaks use a
                        regular button.
                    </p>
                </header>
                {error && (
                    <Alert severity="error" onClose={() => setError('')}>
                        {error}
                    </Alert>
                )}
                {message && <Alert severity="success">{message}</Alert>}
                <section className="rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white p-5 shadow-sm sm:p-7">
                    <div className="flex flex-wrap justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-bold">
                                {clock.schedule.name || 'No assigned schedule'}
                            </h2>
                            <p className="mt-2 text-sm text-slate-500">
                                Shift date: {clock.date} · Asia/Manila
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-slate-500">
                                Recorded total
                            </p>
                            <p className="mt-1 text-2xl font-bold text-red-800">
                                {clock.totalMinutes === null
                                    ? '—'
                                    : `${Math.floor(clock.totalMinutes / 60)}h ${clock.totalMinutes % 60}m`}
                            </p>
                        </div>
                    </div>
                    <p className="mt-4 text-sm text-slate-500">
                        Scheduled {time(clock.schedule.times.time_in)} –{' '}
                        {time(clock.schedule.times.time_out)} · Break{' '}
                        {time(clock.schedule.times.lunch_out)} –{' '}
                        {time(clock.schedule.times.lunch_in)}
                    </p>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {(Object.keys(labels) as Action[]).map((item) => {
                            const Icon = icons[item];
                            const face =
                                item === 'time_in' || item === 'time_out';

                            return (
                                <div
                                    key={item}
                                    className="rounded-xl border border-slate-200 p-5"
                                >
                                    <Icon
                                        className="mb-4 text-red-700"
                                        size={24}
                                    />
                                    <h3 className="font-bold">
                                        {labels[item]}
                                    </h3>
                                    <p className="mt-2 text-2xl font-semibold">
                                        {time(clock.times[item])}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {clock.times[item]
                                            ? 'Actual punch · recorded by server'
                                            : face
                                              ? 'Face verification required'
                                              : 'No face verification needed'}
                                    </p>
                                    <Button
                                        fullWidth
                                        variant="contained"
                                        sx={{ mt: 3 }}
                                        startIcon={
                                            face ? (
                                                <ScanFace size={17} />
                                            ) : (
                                                <Icon size={17} />
                                            )
                                        }
                                        disabled={
                                            busy ||
                                            !clock.actions.includes(item) ||
                                            (face && !clock.faceEnrolled)
                                        }
                                        onClick={() => void start(item)}
                                    >
                                        {labels[item]}
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                    {clock.problem && (
                        <Alert severity="info" sx={{ mt: 3 }}>
                            {clock.problem}
                        </Alert>
                    )}
                    {!clock.faceEnrolled && (
                        <Alert severity="warning" sx={{ mt: 3 }}>
                            Your account needs face enrollment before you can
                            time in or out. Contact an administrator.
                        </Alert>
                    )}
                    <p className="mt-5 text-xs leading-5 text-slate-500">
                        Your schedule and approved overtime, undertime, or leave
                        determine credited hours. Break time is deducted. You
                        cannot edit the recorded punch time.
                    </p>
                </section>
            </main>
            <Dialog
                keepMounted
                open={!!action}
                onClose={() => !busy && setAction(null)}
                fullWidth
                maxWidth="sm"
                aria-labelledby="face-clock-title"
            >
                <DialogTitle
                    id="face-clock-title"
                    className="flex items-center justify-between"
                >
                    <span>Verify face · {action && labels[action]}</span>
                    <IconButton
                        aria-label="Close face verification"
                        disabled={busy}
                        onClick={() => setAction(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent>
                    <p className="mb-4 text-sm text-slate-500">
                        Use your own enrolled face in even lighting. Keep only
                        one person in view. Images are processed for this check
                        and are not saved.
                    </p>
                    <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-950">
                        <video
                            ref={video}
                            autoPlay
                            muted
                            playsInline
                            className="h-full w-full -scale-x-100 object-cover"
                        />
                        {!ready && (
                            <div className="absolute inset-0 grid place-items-center text-white">
                                {busy ? (
                                    <CircularProgress color="inherit" />
                                ) : (
                                    <Camera size={48} />
                                )}
                            </div>
                        )}
                    </div>
                    {cameraError && (
                        <Alert severity="error" sx={{ mt: 2 }}>
                            {cameraError}
                        </Alert>
                    )}
                    <div className="mt-4 rounded-xl bg-red-50 p-4 text-center">
                        <p className="text-xs font-bold text-red-700">
                            {busy
                                ? 'VERIFYING'
                                : `STEP ${Math.min(frames.length + 1, 3)} OF 3`}
                        </p>
                        <p className="mt-2 font-semibold">
                            {busy
                                ? 'Checking identity and liveness on the server…'
                                : frames.length === 1
                                  ? `Turn your head gently to your ${challenge?.direction}.`
                                  : 'Look straight at the camera.'}
                        </p>
                        <p className="mt-2 text-xs text-slate-500">
                            {busy
                                ? 'Your attendance is recorded only after verification passes.'
                                : 'Hold this position, then capture. Complete all three steps within two minutes.'}
                        </p>
                    </div>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 3 }}>
                    <Button
                        variant="contained"
                        disabled={!ready || busy || captureWait}
                        startIcon={<ScanFace size={18} />}
                        onClick={() => void capture()}
                    >
                        {busy ? 'Verifying…' : 'Capture position'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

MyAttendance.layout = {
    breadcrumbs: [{ title: 'My Attendance', href: '/my-attendance' }],
};
