import { Head, Link, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    MenuItem,
    Tab,
    Tabs,
    TextField,
} from '@mui/material';
import { BookOpenCheck, Pencil, Plus, Sparkles, X } from 'lucide-react';
import { useState } from 'react';
import type { ExamConfig } from '@/components/applicant-exam';

type Question = {
    id: number;
    category: string;
    prompt: string;
    options: string[];
    correct_index: number;
    explanation: string;
    approved: boolean;
};
type Result = {
    id: string;
    title: string;
    score: number;
    total: number;
    passed: boolean;
    passing_percent: number;
    submitted_at: string;
    application: {
        first_name: string;
        last_name: string;
        email: string;
        position: string;
    };
};
type Detail = Result & {
    answers: Record<string, string>;
    questions: {
        id: string;
        category: string;
        prompt: string;
        options: { id: string; label: string }[];
        correct_option: string;
        explanation: string;
    }[];
};
type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const base = '/management/applicant-exams';
const blank = {
    category: 'Logical reasoning',
    prompt: '',
    options: ['', '', '', ''],
    correct_index: 0,
    explanation: '',
    approved: false,
};
function Pagination({ page }: { page: Page<unknown> }) {
    return (
        <div className="mt-4 flex items-center justify-end gap-4 text-sm">
            <span>
                Page {page.current_page} of {page.last_page}
            </span>
            {page.prev_page_url && (
                <Link preserveScroll preserveState href={page.prev_page_url}>
                    Previous
                </Link>
            )}
            {page.next_page_url && (
                <Link preserveScroll preserveState href={page.next_page_url}>
                    Next
                </Link>
            )}
        </div>
    );
}

export default function ApplicantExams({
    settings,
    questions,
    results,
    approvedCount,
    aiConfigured,
    statusMessage,
}: {
    settings: ExamConfig;
    questions: Page<Question>;
    results: Page<Result>;
    approvedCount: number;
    aiConfigured: boolean;
    statusMessage?: string;
}) {
    const [tab, setTab] = useState(0);
    const config = useForm({
        ...settings,
        instructions: settings.instructions || '',
    });
    const question = useForm({ ...blank });
    const generation = useForm({
        count: 5,
        difficulty: 'medium',
        focus: 'Call-center logical deduction, interpreting customer information, sequencing and basic numerical reasoning.',
    });
    const [editing, setEditing] = useState<number | null>(null);
    const [open, setOpen] = useState(false);
    const [aiOpen, setAiOpen] = useState(false);
    const [detail, setDetail] = useState<Detail | null>(null);
    const [resultError, setResultError] = useState('');
    const [loadingId, setLoadingId] = useState('');
    const edit = (item?: Question) => {
        setEditing(item?.id ?? null);
        question.clearErrors();
        question.setData(
            item
                ? {
                      category: item.category,
                      prompt: item.prompt,
                      options: item.options,
                      correct_index: item.correct_index,
                      explanation: item.explanation,
                      approved: item.approved,
                  }
                : { ...blank, options: ['', '', '', ''] },
        );
        setOpen(true);
    };
    const loadResult = async (id: string) => {
        setLoadingId(id);
        setResultError('');

        try {
            const response = await fetch(`${base}/results/${id}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
throw new Error();
}

            setDetail(await response.json());
        } catch {
            setResultError('Unable to load this result. Please try again.');
        } finally {
            setLoadingId('');
        }
    };
    const closeTitle = (title: string, close: () => void, disabled = false) => (
        <DialogTitle className="flex items-center justify-between gap-3">
            {title}
            <IconButton
                aria-label="Close modal"
                onClick={close}
                disabled={disabled}
            >
                <X />
            </IconButton>
        </DialogTitle>
    );

    return (
        <div className="space-y-6 p-4 md:p-8">
            <Head title="Divertex" />
            <header>
                <p className="text-sm font-bold tracking-[0.18em] text-red-700 uppercase">
                    Recruitment & development
                </p>
                <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                    <BookOpenCheck className="text-red-700" />
                    Applicant Logic Exam
                </h1>
                <p className="mt-2 text-slate-500">
                    Create questions, review AI drafts, and assess applicant
                    responses.
                </p>
            </header>
            {statusMessage && <Alert severity="success">{statusMessage}</Alert>}
            <div className="grid gap-4 sm:grid-cols-3">
                {[
                    ['Exam status', settings.enabled ? 'Enabled' : 'Disabled'],
                    ['Approved questions', String(approvedCount)],
                    [
                        'Exam length',
                        `${settings.question_count} questions · ${settings.duration_minutes} min`,
                    ],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <p className="text-sm text-slate-500">{label}</p>
                        <p className="mt-2 text-lg font-bold">{value}</p>
                    </div>
                ))}
            </div>
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <Tabs
                    value={tab}
                    onChange={(_, value: number) => setTab(value)}
                    variant="scrollable"
                >
                    <Tab label="Question bank" />
                    <Tab label="Exam settings" />
                    <Tab label="Applicant results" />
                </Tabs>
                <div className="p-5 md:p-6">
                    {tab === 0 && (
                        <>
                            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-slate-500">
                                    Only approved questions are used. Each
                                    applicant receives a randomized selection.
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        startIcon={<Sparkles size={18} />}
                                        variant="outlined"
                                        onClick={() => setAiOpen(true)}
                                    >
                                        Generate AI drafts
                                    </Button>
                                    <Button
                                        startIcon={<Plus size={18} />}
                                        variant="contained"
                                        onClick={() => edit()}
                                    >
                                        Add question
                                    </Button>
                                </div>
                            </div>
                            {!questions.data.length && (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-slate-500">
                                    Create your first question or generate
                                    drafts for review.
                                </p>
                            )}
                            <div className="space-y-3">
                                {questions.data.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-start justify-between gap-3 rounded-xl border border-slate-200 p-4"
                                    >
                                        <div>
                                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                                <Chip
                                                    size="small"
                                                    color={
                                                        item.approved
                                                            ? 'success'
                                                            : 'default'
                                                    }
                                                    label={
                                                        item.approved
                                                            ? 'Approved'
                                                            : 'Draft — review required'
                                                    }
                                                />
                                                <span className="text-xs text-slate-500">
                                                    {item.category}
                                                </span>
                                            </div>
                                            <p className="font-semibold whitespace-pre-wrap">
                                                {item.prompt}
                                            </p>
                                        </div>
                                        <IconButton
                                            aria-label="Review question"
                                            onClick={() => edit(item)}
                                        >
                                            <Pencil size={18} />
                                        </IconButton>
                                    </div>
                                ))}
                            </div>
                            <Pagination page={questions} />
                        </>
                    )}
                    {tab === 1 && (
                        <form
                            className="grid max-w-3xl gap-6"
                            onSubmit={(event) => {
                                event.preventDefault();
                                config.put(`${base}/settings`, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Alert severity="info">
                                The exam is required with new applications when
                                enabled. Scores are for review; they do not
                                automatically change application status.
                            </Alert>
                            <TextField
                                fullWidth
                                label="Exam title"
                                value={config.data.title}
                                onChange={(e) =>
                                    config.setData('title', e.target.value)
                                }
                                error={!!config.errors.title}
                                helperText={config.errors.title}
                            />
                            <TextField
                                fullWidth
                                multiline
                                minRows={3}
                                label="Applicant instructions"
                                value={config.data.instructions}
                                onChange={(e) =>
                                    config.setData(
                                        'instructions',
                                        e.target.value,
                                    )
                                }
                                helperText={config.errors.instructions}
                            />
                            <div className="grid gap-5 sm:grid-cols-3">
                                {(
                                    [
                                        'question_count',
                                        'duration_minutes',
                                        'passing_percent',
                                    ] as const
                                ).map((key, i) => (
                                    <TextField
                                        key={key}
                                        type="number"
                                        label={
                                            [
                                                'Questions',
                                                'Minutes',
                                                'Passing mark (%)',
                                            ][i]
                                        }
                                        value={config.data[key]}
                                        onChange={(e) =>
                                            config.setData(
                                                key,
                                                Number(e.target.value),
                                            )
                                        }
                                        error={!!config.errors[key]}
                                        helperText={config.errors[key]}
                                    />
                                ))}
                            </div>
                            <div>
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={config.data.enabled}
                                            onChange={(_, checked) =>
                                                config.setData(
                                                    'enabled',
                                                    checked,
                                                )
                                            }
                                        />
                                    }
                                    label="Enable exam in the application form"
                                />
                                {config.errors.enabled && (
                                    <p className="text-sm text-red-700">
                                        {config.errors.enabled}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                variant="contained"
                                disabled={config.processing}
                            >
                                Save exam settings
                            </Button>
                        </form>
                    )}
                    {tab === 2 && (
                        <>
                            {resultError && (
                                <Alert severity="error">{resultError}</Alert>
                            )}
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-red-50 text-red-800">
                                        <tr>
                                            {[
                                                'Applicant',
                                                'Position',
                                                'Score',
                                                'Result',
                                                'Submitted',
                                                '',
                                            ].map((label, i) => (
                                                <th key={i} className="p-3">
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {results.data.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b border-slate-100"
                                            >
                                                <td className="p-3">
                                                    <p className="font-semibold">
                                                        {
                                                            item.application
                                                                .first_name
                                                        }{' '}
                                                        {
                                                            item.application
                                                                .last_name
                                                        }
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {item.application.email}
                                                    </p>
                                                </td>
                                                <td className="p-3">
                                                    {item.application.position}
                                                </td>
                                                <td className="p-3">
                                                    {item.score}/{item.total} (
                                                    {Math.round(
                                                        (item.score /
                                                            item.total) *
                                                            100,
                                                    )}
                                                    %)
                                                </td>
                                                <td className="p-3">
                                                    <Chip
                                                        size="small"
                                                        color={
                                                            item.passed
                                                                ? 'success'
                                                                : 'warning'
                                                        }
                                                        label={
                                                            item.passed
                                                                ? 'Pass mark met'
                                                                : 'Below pass mark'
                                                        }
                                                    />
                                                </td>
                                                <td className="p-3">
                                                    {new Date(
                                                        item.submitted_at,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="p-3">
                                                    <Button
                                                        onClick={() =>
                                                            loadResult(item.id)
                                                        }
                                                        disabled={!!loadingId}
                                                    >
                                                        {loadingId === item.id
                                                            ? 'Loading…'
                                                            : 'View answers'}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {!results.data.length && (
                                <p className="p-8 text-center text-slate-500">
                                    Results will appear after applicants submit
                                    their applications and exam answers.
                                </p>
                            )}
                            <Pagination page={results} />
                        </>
                    )}
                </div>
            </div>
            <Dialog
                open={open}
                onClose={() => !question.processing && setOpen(false)}
                fullWidth
                maxWidth="md"
                slotProps={{ paper: { sx: { borderRadius: 4 } } }}
            >
                {closeTitle(
                    editing ? 'Review question' : 'Add question',
                    () => setOpen(false),
                    question.processing,
                )}
                <DialogContent dividers>
                    <div className="grid gap-5 pt-2">
                        {Object.values(question.errors).map((error, i) => (
                            <Alert key={i} severity="error">
                                {error}
                            </Alert>
                        ))}
                        <TextField
                            fullWidth
                            label="Category"
                            value={question.data.category}
                            onChange={(e) =>
                                question.setData('category', e.target.value)
                            }
                        />
                        <TextField
                            fullWidth
                            multiline
                            minRows={3}
                            label="Question"
                            value={question.data.prompt}
                            onChange={(e) =>
                                question.setData('prompt', e.target.value)
                            }
                        />
                        <div className="grid gap-5 sm:grid-cols-2">
                            {question.data.options.map((option, i) => (
                                <TextField
                                    key={i}
                                    fullWidth
                                    label={`Option ${String.fromCharCode(65 + i)}`}
                                    value={option}
                                    onChange={(e) =>
                                        question.setData(
                                            'options',
                                            question.data.options.map(
                                                (value, index) =>
                                                    index === i
                                                        ? e.target.value
                                                        : value,
                                            ),
                                        )
                                    }
                                />
                            ))}
                        </div>
                        <TextField
                            select
                            fullWidth
                            label="Correct answer"
                            value={question.data.correct_index}
                            onChange={(e) =>
                                question.setData(
                                    'correct_index',
                                    Number(e.target.value),
                                )
                            }
                        >
                            {question.data.options.map((_, i) => (
                                <MenuItem key={i} value={i}>
                                    Option {String.fromCharCode(65 + i)}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            fullWidth
                            multiline
                            minRows={3}
                            label="Explanation (reviewers only)"
                            value={question.data.explanation}
                            onChange={(e) =>
                                question.setData('explanation', e.target.value)
                            }
                        />
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={question.data.approved}
                                    onChange={(_, checked) =>
                                        question.setData('approved', checked)
                                    }
                                />
                            }
                            label="I have reviewed the question, choices and answer key. Approve for applicant exams."
                        />
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={question.processing}
                        onClick={() => {
                            const options = {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            };

                            if (editing) {
question.put(
                                    `${base}/questions/${editing}`,
                                    options,
                                );
} else {
question.post(`${base}/questions`, options);
}
                        }}
                    >
                        Save question
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={aiOpen}
                onClose={() => !generation.processing && setAiOpen(false)}
                fullWidth
                maxWidth="sm"
                slotProps={{ paper: { sx: { borderRadius: 4 } } }}
            >
                {closeTitle(
                    'Generate AI question drafts',
                    () => setAiOpen(false),
                    generation.processing,
                )}
                <DialogContent dividers>
                    <div className="grid gap-5 pt-2">
                        <Alert severity={aiConfigured ? 'info' : 'warning'}>
                            {aiConfigured
                                ? 'AI can make mistakes. Verify each answer and explanation before approving. Only these topic preferences are sent to the AI service.'
                                : 'AI generation needs a server API key. You can still create and approve questions manually.'}
                        </Alert>
                        {Object.values(generation.errors).map((error, i) => (
                            <Alert key={i} severity="error">
                                {error}
                            </Alert>
                        ))}
                        <TextField
                            fullWidth
                            multiline
                            minRows={3}
                            label="Topics and skills to assess"
                            value={generation.data.focus}
                            onChange={(e) =>
                                generation.setData('focus', e.target.value)
                            }
                        />
                        <div className="grid grid-cols-2 gap-5">
                            <TextField
                                type="number"
                                label="Questions (1–5)"
                                value={generation.data.count}
                                onChange={(e) =>
                                    generation.setData(
                                        'count',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <TextField
                                select
                                label="Difficulty"
                                value={generation.data.difficulty}
                                onChange={(e) =>
                                    generation.setData(
                                        'difficulty',
                                        e.target.value,
                                    )
                                }
                            >
                                {['easy', 'medium', 'hard'].map((value) => (
                                    <MenuItem key={value} value={value}>
                                        {value}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </div>
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={!aiConfigured || generation.processing}
                        onClick={() =>
                            generation.post(`${base}/generate`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setAiOpen(false);
                                    setTab(0);
                                },
                            })
                        }
                    >
                        {generation.processing
                            ? 'Generating drafts…'
                            : 'Generate drafts'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!detail}
                onClose={() => setDetail(null)}
                fullWidth
                maxWidth="md"
                slotProps={{ paper: { sx: { borderRadius: 4 } } }}
            >
                {closeTitle('Applicant exam result', () => setDetail(null))}
                <DialogContent dividers>
                    {detail && (
                        <div className="space-y-5">
                            <div>
                                <h3 className="text-xl font-bold">
                                    {detail.application.first_name}{' '}
                                    {detail.application.last_name}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    {detail.application.email} · {detail.title}
                                </p>
                                <p className="mt-3 font-bold">
                                    {detail.score}/{detail.total} ·{' '}
                                    {Math.round(
                                        (detail.score / detail.total) * 100,
                                    )}
                                    % · Passing mark {detail.passing_percent}%
                                </p>
                            </div>
                            {detail.questions.map((item, i) => (
                                <div
                                    key={item.id}
                                    className="rounded-xl border border-slate-200 p-4"
                                >
                                    <p className="mb-3 font-semibold">
                                        {i + 1}. {item.prompt}
                                    </p>
                                    <div className="space-y-2">
                                        {item.options.map((option) => (
                                            <p
                                                key={option.id}
                                                className={`rounded-lg p-2 text-sm ${option.id === item.correct_option ? 'bg-green-50 text-green-800' : option.id === detail.answers[item.id] ? 'bg-red-50 text-red-800' : 'bg-slate-50'}`}
                                            >
                                                {option.label}
                                                {option.id ===
                                                    detail.answers[item.id] &&
                                                    ' — Applicant answer'}
                                                {option.id ===
                                                    item.correct_option &&
                                                    ' — Correct answer'}
                                            </p>
                                        ))}
                                    </div>
                                    <p className="mt-3 text-sm text-slate-600">
                                        {item.explanation}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
