import { Alert, Button, CircularProgress } from '@mui/material';
import { useEffect, useState } from 'react';

export type ExamConfig = {
    enabled: boolean;
    title: string;
    instructions: string | null;
    question_count: number;
    duration_minutes: number;
    passing_percent: number;
};
type Attempt = {
    id: string;
    title: string;
    expires_at: string;
    server_now: string;
    questions: {
        id: string;
        category: string;
        prompt: string;
        options: { id: string; label: string }[];
    }[];
};

export default function ApplicantExam({
    config,
    attemptId,
    answers,
    onStart,
    onAnswer,
    error,
}: {
    config: ExamConfig;
    attemptId: string;
    answers: Record<string, string>;
    onStart: (id: string) => void;
    onAnswer: (id: string, answer: string) => void;
    error?: string;
}) {
    const [attempt, setAttempt] = useState<Attempt | null>(null);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [remaining, setRemaining] = useState(0);
    const [offset, setOffset] = useState(0);
    useEffect(() => {
        if (!attempt || !attemptId) {
return;
}

        const tick = () =>
            setRemaining(
                Math.max(
                    0,
                    Math.ceil(
                        (Date.parse(attempt.expires_at) - Date.now() - offset) /
                            1000,
                    ),
                ),
            );
        tick();
        const timer = window.setInterval(tick, 1000);

        return () => window.clearInterval(timer);
    }, [attempt, attemptId, offset]);

    if (!config.enabled) {
return null;
}

    const start = async () => {
        setLoading(true);
        setMessage('');

        try {
            const response = await fetch('/application-exam/start', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content || '',
                },
            });
            const data = await response.json();

            if (!response.ok) {
throw new Error(
                    data.errors?.exam?.[0] ||
                        data.message ||
                        'Unable to start the exam. Please try again.',
                );
}

            setAttempt(data);
            setOffset(Date.parse(data.server_now) - Date.now());
            onStart(data.id);
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Unable to start the exam.',
            );
        } finally {
            setLoading(false);
        }
    };
    const active = attempt && attempt.id === attemptId;

    return (
        <section className="space-y-5 rounded-2xl border border-red-100 bg-red-50/40 p-5 sm:col-span-2">
            <div>
                <h3 className="text-xl font-bold">{config.title}</h3>
                <p className="mt-2 text-sm text-slate-600">
                    {config.instructions ||
                        'Complete this short reasoning exam and submit it with your application. Your answers will be reviewed by our recruitment team.'}
                </p>
            </div>
            <p className="text-sm text-slate-600">
                {config.question_count} questions · {config.duration_minutes}{' '}
                minutes · Passing mark: {config.passing_percent}%
            </p>
            {(message || error) && (
                <Alert severity="error">{message || error}</Alert>
            )}
            {!active ? (
                <>
                    <p className="text-sm">
                        Complete your application details first. The timer
                        starts when you begin; submit your application before
                        time runs out.
                    </p>
                    <Button
                        type="button"
                        variant="contained"
                        disabled={loading}
                        onClick={start}
                    >
                        {loading ? (
                            <CircularProgress size={20} />
                        ) : (
                            'Start exam'
                        )}
                    </Button>
                </>
            ) : (
                <>
                    <div className="flex flex-wrap justify-between gap-2 rounded-xl bg-white p-3 font-semibold">
                        <span>
                            {Object.keys(answers).length}/
                            {attempt.questions.length} answered
                        </span>
                        <span
                            role="timer"
                            className={remaining < 120 ? 'text-red-700' : ''}
                        >
                            {Math.floor(remaining / 60)}:
                            {String(remaining % 60).padStart(2, '0')} remaining
                        </span>
                    </div>
                    {remaining === 0 ? (
                        <Alert
                            severity="warning"
                            action={
                                <Button
                                    type="button"
                                    onClick={start}
                                    disabled={loading}
                                >
                                    Restart exam
                                </Button>
                            }
                        >
                            Time has expired. Your application details are
                            preserved. Start a new exam to submit.
                        </Alert>
                    ) : (
                        <div className="max-h-[65vh] space-y-5 overflow-y-auto pr-2">
                            {attempt.questions.map((question, index) => (
                                <fieldset
                                    key={question.id}
                                    className="rounded-xl border border-slate-200 bg-white p-4"
                                >
                                    <legend className="px-1 text-xs font-bold text-red-700">
                                        Question {index + 1} ·{' '}
                                        {question.category}
                                    </legend>
                                    <p className="mb-3 font-semibold whitespace-pre-wrap">
                                        {question.prompt}
                                    </p>
                                    <div className="space-y-2">
                                        {question.options.map((option) => (
                                            <label
                                                key={option.id}
                                                className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm ${answers[question.id] === option.id ? 'border-red-400 bg-red-50' : 'border-slate-200 hover:bg-slate-50'}`}
                                            >
                                                <input
                                                    type="radio"
                                                    name={`exam-${question.id}`}
                                                    value={option.id}
                                                    checked={
                                                        answers[question.id] ===
                                                        option.id
                                                    }
                                                    onChange={() =>
                                                        onAnswer(
                                                            question.id,
                                                            option.id,
                                                        )
                                                    }
                                                    className="mt-1 accent-red-700"
                                                />
                                                <span>{option.label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>
                            ))}
                        </div>
                    )}
                </>
            )}
        </section>
    );
}
