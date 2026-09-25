import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    MenuItem,
    Radio,
    RadioGroup,
    Tab,
    Tabs,
    TextField,
} from '@mui/material';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    FilePlus,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';

type Option = { option_text: string; is_correct: boolean };
type Item = {
    id: number;
    title: string;
    description?: string;
    type: string;
    content?: string;
    original_filename?: string;
    is_required: boolean;
    required_completion_percentage: number;
};
type LibraryAttachment = {
    id: number;
    is_required: boolean;
    required_completion_percentage: number;
    material: Omit<Item, 'is_required' | 'required_completion_percentage'>;
};
type Question = {
    id: number;
    question_text: string;
    question_type: string;
    points: string;
    skill?: { id: number; name: string };
    feedback?: string;
    is_required: boolean;
    options: ({ id: number } & Option)[];
};
type Assessment = {
    id: number;
    title: string;
    status: string;
    training_materials: Item[];
    training_attachments: LibraryAttachment[];
    questions: Question[];
    random_pools: {
        id: number;
        skill_id?: number;
        category_id?: number;
        difficulty?: string;
        question_type?: string;
        questions_to_select: number;
        points_per_question: string;
    }[];
};
type Props = {
    assessment: Assessment;
    skills: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    totals: { questions: number; points: number; materials: number };
};

export default function Builder({
    assessment,
    skills,
    categories,
    totals,
}: Props) {
    const confirmAction = useConfirmation();
    const [tab, setTab] = useState(0),
        [materialOpen, setMaterialOpen] = useState(false),
        [questionOpen, setQuestionOpen] = useState(false),
        [editingQ, setEditingQ] = useState<Question | null>(null);
    const material = useForm({
        title: '',
        description: '',
        type: 'written',
        content: '',
        file: null as File | null,
        is_required: true,
        required_completion_percentage: 90,
    });
    const question = useForm({
        question_text: '',
        question_type: 'multiple_choice',
        points: '1',
        skill_id: '',
        feedback: '',
        is_required: true,
        options: [
            { option_text: '', is_correct: true },
            { option_text: '', is_correct: false },
        ] as Option[],
    });
    const pool = useForm({
        skill_id: '',
        category_id: '',
        difficulty: '',
        question_type: '',
        questions_to_select: '1',
        points_per_question: '1',
    });
    const openQuestion = (q?: Question) => {
        setEditingQ(q || null);
        const normalizedOptions =
            q?.question_type === 'true_false'
                ? [
                      {
                          option_text: 'True',
                          is_correct:
                              q.options.find(
                                  (option) =>
                                      option.option_text.toLowerCase() ===
                                      'true',
                              )?.is_correct ??
                              q.options[0]?.is_correct ??
                              false,
                      },
                      {
                          option_text: 'False',
                          is_correct:
                              q.options.find(
                                  (option) =>
                                      option.option_text.toLowerCase() ===
                                      'false',
                              )?.is_correct ??
                              q.options[1]?.is_correct ??
                              false,
                      },
                  ]
                : q?.options.map((o) => ({
                      option_text: o.option_text,
                      is_correct: o.is_correct,
                  }));
        question.setData(
            q
                ? {
                      question_text: q.question_text,
                      question_type: q.question_type,
                      points: q.points,
                      skill_id: q.skill?.id.toString() || '',
                      feedback: q.feedback || '',
                      is_required: q.is_required,
                      options: normalizedOptions || [],
                  }
                : {
                      question_text: '',
                      question_type: 'multiple_choice',
                      points: '1',
                      skill_id: '',
                      feedback: '',
                      is_required: true,
                      options: [
                          { option_text: '', is_correct: true },
                          { option_text: '', is_correct: false },
                      ],
                  },
        );
        setQuestionOpen(true);
    };
    const changeQuestionType = async (nextType: string) => {
        const currentType = question.data.question_type;

        if (nextType === 'true_false') {
            const hasMeaningfulChoices = question.data.options.some(
                (option) => option.option_text.trim() !== '',
            );

            if (
                currentType !== 'true_false' &&
                hasMeaningfulChoices &&
                !(await confirmAction(
                    'Switching to True/False will replace the current answer choices. Continue?',
                ))
            ) {
                return;
            }

            question.setData((data) => ({
                ...data,
                question_type: nextType,
                options: [
                    { option_text: 'True', is_correct: true },
                    { option_text: 'False', is_correct: false },
                ],
            }));

            return;
        }

        question.setData((data) => ({
            ...data,
            question_type: nextType,
            options:
                currentType === 'true_false' && nextType !== 'short_answer'
                    ? [
                          { option_text: '', is_correct: true },
                          { option_text: '', is_correct: false },
                      ]
                    : data.options,
        }));
    };
    const saveQuestion = () => {
        const opts = {
            forceFormData: false,
            onSuccess: () => setQuestionOpen(false),
        };

        if (editingQ) {
            question.put(
                `/management/assessments/${assessment.id}/questions/${editingQ.id}`,
                opts,
            );
        } else {
            question.post(
                `/management/assessments/${assessment.id}/questions`,
                opts,
            );
        }
    };
    const reorder = (
        kind: 'materials' | 'questions',
        items: { id: number }[],
        index: number,
        delta: number,
    ) => {
        const next = [...items],
            target = index + delta;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];
        router.patch(
            `/management/assessments/${assessment.id}/${kind}/reorder`,
            {
                [kind === 'materials' ? 'material_ids' : 'question_ids']:
                    next.map((x) => x.id),
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Builder: ${assessment.title}`} />
            <main className="assessment-admin min-h-full bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-6xl space-y-5">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <Link
                                href="/management/assessments"
                                className="text-sm font-semibold text-[#ad2924]"
                            >
                                Assessment Management
                            </Link>
                            <h1 className="mt-1 text-2xl font-bold">
                                {assessment.title}
                            </h1>
                            <p className="text-sm text-[#777b8e] capitalize">
                                {assessment.status} · {totals.materials}{' '}
                                materials · {totals.questions} questions ·{' '}
                                {totals.points} points
                            </p>
                        </div>
                        <Link
                            href={`/management/assessments/${assessment.id}/preview`}
                        >
                            <Button startIcon={<Eye />}>Preview</Button>
                        </Link>
                    </header>
                    <section className="rounded-3xl border bg-white">
                        <Tabs
                            value={tab}
                            onChange={(_, v) => setTab(v)}
                            variant="scrollable"
                        >
                            <Tab label="Assessment Details" />
                            <Tab label="Training Material" />
                            <Tab label="Questions" />
                            <Tab label="Random Pools" />
                            <Tab label="Assignment" />
                            <Tab label="Preview" />
                        </Tabs>
                        <div className="p-4 lg:p-5">
                            {tab === 0 && (
                                <div>
                                    <h2 className="text-lg font-bold">
                                        Assessment Details
                                    </h2>
                                    <p className="mt-2 text-[#777b8e]">
                                        Edit core settings from Assessment
                                        Management. Builder content is saved
                                        independently.
                                    </p>
                                </div>
                            )}
                            {tab === 1 && (
                                <>
                                    <div className="mb-5 flex justify-between">
                                        <h2 className="text-lg font-bold">
                                            Training Material
                                        </h2>
                                        <Button
                                            variant="contained"
                                            startIcon={<FilePlus />}
                                            onClick={() =>
                                                setMaterialOpen(true)
                                            }
                                        >
                                            Add Material
                                        </Button>
                                        <Link
                                            href={`/management/training-library?assessment=${assessment.id}`}
                                        >
                                            <Button variant="outlined">
                                                Add From Training Library
                                            </Button>
                                        </Link>
                                        <Link
                                            href={`/management/question-bank?assessment=${assessment.id}`}
                                        >
                                            <Button variant="outlined">
                                                Add From Question Bank
                                            </Button>
                                        </Link>
                                    </div>
                                    <div className="space-y-3">
                                        {assessment.training_materials.map(
                                            (m, i) => (
                                                <article
                                                    key={m.id}
                                                    className="flex items-center gap-3 rounded-2xl border p-4"
                                                >
                                                    <div className="grow">
                                                        <b>
                                                            {i + 1}. {m.title}
                                                        </b>
                                                        <p className="text-sm text-[#777b8e] capitalize">
                                                            {m.type} ·{' '}
                                                            {m.is_required
                                                                ? 'Required'
                                                                : 'Optional'}{' '}
                                                            ·{' '}
                                                            {
                                                                m.required_completion_percentage
                                                            }
                                                            %
                                                        </p>
                                                    </div>
                                                    <Button
                                                        onClick={() =>
                                                            reorder(
                                                                'materials',
                                                                assessment.training_materials,
                                                                i,
                                                                -1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowUp />
                                                    </Button>
                                                    <Button
                                                        onClick={() =>
                                                            reorder(
                                                                'materials',
                                                                assessment.training_materials,
                                                                i,
                                                                1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowDown />
                                                    </Button>
                                                    <Button
                                                        color="error"
                                                        onClick={async () =>
                                                            (await confirmAction(
                                                                'Remove material?',
                                                            )) &&
                                                            router.delete(
                                                                `/management/assessments/${assessment.id}/materials/${m.id}`,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </article>
                                            ),
                                        )}
                                        {assessment.training_attachments.map(
                                            (attachment, i) => (
                                                <article
                                                    key={`library-${attachment.id}`}
                                                    className="flex items-center gap-3 rounded-2xl border border-red-100 bg-red-50/30 p-4"
                                                >
                                                    <div className="grow">
                                                        <div className="mb-1 text-xs font-bold tracking-wide text-red-700 uppercase">Training Library</div>
                                                        <b>{assessment.training_materials.length + i + 1}. {attachment.material.title}</b>
                                                        <p className="text-sm text-[#777b8e] capitalize">
                                                            {attachment.material.type} - {attachment.is_required ? 'Required' : 'Optional'} - {attachment.required_completion_percentage}%
                                                        </p>
                                                    </div>
                                                    <Button
                                                        color="error"
                                                        onClick={async () =>
                                                            (await confirmAction('Detach this Training Library material?')) &&
                                                            router.delete(`/management/assessments/${assessment.id}/training-library/${attachment.id}`)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </article>
                                            ),
                                        )}
                                        {!assessment.training_materials.length && !assessment.training_attachments.length && (
                                            <p className="rounded-xl border border-dashed p-6 text-center text-sm text-[#777b8e]">No training materials added yet.</p>
                                        )}
                                    </div>
                                </>
                            )}
                            {tab === 2 && (
                                <>
                                    <div className="mb-5 flex justify-between">
                                        <h2 className="text-xl font-bold">
                                            Questions
                                        </h2>
                                        <Button
                                            variant="contained"
                                            startIcon={<Plus />}
                                            onClick={() => openQuestion()}
                                        >
                                            Add Question
                                        </Button>
                                    </div>
                                    <div className="space-y-3">
                                        {assessment.questions.map((q, i) => (
                                            <article
                                                key={q.id}
                                                className="flex items-center gap-3 rounded-2xl border p-4"
                                            >
                                                <button
                                                    className="grow text-left"
                                                    onClick={() =>
                                                        openQuestion(q)
                                                    }
                                                >
                                                    <b>
                                                        {i + 1}.{' '}
                                                        {q.question_text}
                                                    </b>
                                                    <p className="text-sm text-[#777b8e] capitalize">
                                                        {q.question_type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}{' '}
                                                        ·{' '}
                                                        {q.skill?.name ||
                                                            'No skill'}{' '}
                                                        · {q.points} points
                                                    </p>
                                                </button>
                                                <Button
                                                    onClick={() =>
                                                        reorder(
                                                            'questions',
                                                            assessment.questions,
                                                            i,
                                                            -1,
                                                        )
                                                    }
                                                >
                                                    <ArrowUp />
                                                </Button>
                                                <Button
                                                    onClick={() =>
                                                        reorder(
                                                            'questions',
                                                            assessment.questions,
                                                            i,
                                                            1,
                                                        )
                                                    }
                                                >
                                                    <ArrowDown />
                                                </Button>
                                                <Button
                                                    onClick={() =>
                                                        router.post(
                                                            `/management/assessments/${assessment.id}/questions/${q.id}/duplicate`,
                                                        )
                                                    }
                                                >
                                                    <Copy />
                                                </Button>
                                                <Button
                                                    color="error"
                                                    onClick={async () =>
                                                        (await confirmAction(
                                                            'Delete question?',
                                                        )) &&
                                                        router.delete(
                                                            `/management/assessments/${assessment.id}/questions/${q.id}`,
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </article>
                                        ))}
                                    </div>
                                </>
                            )}
                            {tab === 3 && (
                                <div>
                                    <h2 className="mb-4 text-xl font-bold">
                                        Random Question Pools
                                    </h2>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <TextField
                                            select
                                            label="Skill"
                                            value={pool.data.skill_id}
                                            onChange={(e) =>
                                                pool.setData(
                                                    'skill_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <MenuItem value="">Any</MenuItem>
                                            {skills.map((s) => (
                                                <MenuItem
                                                    key={s.id}
                                                    value={s.id}
                                                >
                                                    {s.name}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                        <TextField
                                            select
                                            label="Category"
                                            value={pool.data.category_id}
                                            onChange={(e) =>
                                                pool.setData(
                                                    'category_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <MenuItem value="">Any</MenuItem>
                                            {categories.map((c) => (
                                                <MenuItem
                                                    key={c.id}
                                                    value={c.id}
                                                >
                                                    {c.name}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                        <TextField
                                            select
                                            label="Difficulty"
                                            value={pool.data.difficulty}
                                            onChange={(e) =>
                                                pool.setData(
                                                    'difficulty',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <MenuItem value="">Any</MenuItem>
                                            {['easy', 'medium', 'hard'].map(
                                                (v) => (
                                                    <MenuItem key={v} value={v}>
                                                        {v}
                                                    </MenuItem>
                                                ),
                                            )}
                                        </TextField>
                                        <TextField
                                            select
                                            label="Type"
                                            value={pool.data.question_type}
                                            onChange={(e) =>
                                                pool.setData(
                                                    'question_type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <MenuItem value="">Any</MenuItem>
                                            {[
                                                'multiple_choice',
                                                'true_false',
                                                'multiple_selection',
                                                'short_answer',
                                            ].map((v) => (
                                                <MenuItem key={v} value={v}>
                                                    {v.replaceAll('_', ' ')}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                        <TextField
                                            type="number"
                                            label="Questions to select"
                                            error={Boolean(
                                                pool.errors.questions_to_select,
                                            )}
                                            helperText={
                                                pool.errors.questions_to_select
                                            }
                                            value={
                                                pool.data.questions_to_select
                                            }
                                            onChange={(e) =>
                                                pool.setData(
                                                    'questions_to_select',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <TextField
                                            type="number"
                                            label="Points per question"
                                            value={
                                                pool.data.points_per_question
                                            }
                                            onChange={(e) =>
                                                pool.setData(
                                                    'points_per_question',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <Button
                                        className="mt-3"
                                        variant="contained"
                                        disabled={pool.processing}
                                        onClick={() =>
                                            pool.post(
                                                `/management/assessments/${assessment.id}/random-pools`,
                                            )
                                        }
                                    >
                                        Add Pool
                                    </Button>
                                    {pool.hasErrors &&
                                        !pool.errors.questions_to_select && (
                                            <Alert
                                                severity="error"
                                                className="mt-3"
                                            >
                                                The pool could not be saved.
                                                Check the selected filters and
                                                try again.
                                            </Alert>
                                        )}
                                    <div className="mt-5 space-y-2">
                                        {assessment.random_pools.map((p, i) => (
                                            <article
                                                key={p.id}
                                                className="flex justify-between rounded-xl border p-3"
                                            >
                                                <span>
                                                    Pool {i + 1}:{' '}
                                                    {p.questions_to_select}{' '}
                                                    question(s),{' '}
                                                    {p.points_per_question}{' '}
                                                    points each
                                                </span>
                                                <Button
                                                    color="error"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/management/assessments/${assessment.id}/random-pools/${p.id}`,
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </article>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {tab === 4 && (
                                <div>
                                    <h2 className="text-xl font-bold">
                                        Assignment
                                    </h2>
                                    <p className="mt-2">
                                        Assign this published assessment from
                                        the paginated{' '}
                                        <Link
                                            className="font-semibold text-[#ad2924]"
                                            href="/management/assessment-assignments"
                                        >
                                            Assignments page
                                        </Link>
                                        .
                                    </p>
                                </div>
                            )}
                            {tab === 5 && (
                                <Link
                                    href={`/management/assessments/${assessment.id}/preview`}
                                >
                                    <Button variant="contained">
                                        Open Preview Mode
                                    </Button>
                                </Link>
                            )}
                        </div>
                    </section>
                </div>
            </main>
            <Dialog
                open={materialOpen}
                onClose={() => setMaterialOpen(false)}
                fullWidth
            >
                <DialogTitle>Add Training Material</DialogTitle>
                <DialogContent>
                    <div className="mt-2 grid gap-4">
                        <TextField
                            label="Title"
                            value={material.data.title}
                            onChange={(e) =>
                                material.setData('title', e.target.value)
                            }
                        />
                        <TextField
                            select
                            label="Type"
                            value={material.data.type}
                            onChange={(e) =>
                                material.setData('type', e.target.value)
                            }
                        >
                            {[
                                'written',
                                'video',
                                'audio',
                                'image',
                                'document',
                            ].map((x) => (
                                <MenuItem key={x} value={x}>
                                    {x}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            multiline
                            label="Description"
                            value={material.data.description}
                            onChange={(e) =>
                                material.setData('description', e.target.value)
                            }
                        />
                        {material.data.type === 'written' ? (
                            <TextField
                                multiline
                                minRows={8}
                                label="Lesson body"
                                value={material.data.content}
                                onChange={(e) =>
                                    material.setData('content', e.target.value)
                                }
                            />
                        ) : (
                            <input
                                type="file"
                                onChange={(e) =>
                                    material.setData(
                                        'file',
                                        e.target.files?.[0] || null,
                                    )
                                }
                            />
                        )}
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={material.data.is_required}
                                    onChange={(e) =>
                                        material.setData(
                                            'is_required',
                                            e.target.checked,
                                        )
                                    }
                                />
                            }
                            label="Required before quiz"
                        />
                        <TextField
                            type="number"
                            label="Completion threshold"
                            value={material.data.required_completion_percentage}
                            onChange={(e) =>
                                material.setData(
                                    'required_completion_percentage',
                                    Number(e.target.value),
                                )
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setMaterialOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={() =>
                            material.post(
                                `/management/assessments/${assessment.id}/materials`,
                                {
                                    forceFormData: true,
                                    onSuccess: () => setMaterialOpen(false),
                                },
                            )
                        }
                    >
                        Save
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={questionOpen}
                onClose={() => setQuestionOpen(false)}
                fullWidth
                maxWidth="md"
            >
                <DialogTitle>{editingQ ? 'Edit' : 'Add'} Question</DialogTitle>
                <DialogContent>
                    <div className="mt-2 grid gap-4">
                        <TextField
                            multiline
                            label="Question"
                            value={question.data.question_text}
                            onChange={(e) =>
                                question.setData(
                                    'question_text',
                                    e.target.value,
                                )
                            }
                        />
                        <TextField
                            select
                            label="Type"
                            value={question.data.question_type}
                            onChange={(e) => changeQuestionType(e.target.value)}
                        >
                            {[
                                'multiple_choice',
                                'true_false',
                                'multiple_selection',
                                'short_answer',
                            ].map((x) => (
                                <MenuItem key={x} value={x}>
                                    {x.replaceAll('_', ' ')}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            type="number"
                            label="Points"
                            value={question.data.points}
                            onChange={(e) =>
                                question.setData('points', e.target.value)
                            }
                        />
                        <TextField
                            select
                            label="Skill"
                            value={question.data.skill_id}
                            onChange={(e) =>
                                question.setData('skill_id', e.target.value)
                            }
                        >
                            <MenuItem value="">None</MenuItem>
                            {skills.map((s) => (
                                <MenuItem key={s.id} value={s.id}>
                                    {s.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            multiline
                            label="Explanation / Feedback"
                            value={question.data.feedback}
                            onChange={(e) =>
                                question.setData('feedback', e.target.value)
                            }
                        />
                        {question.data.question_type === 'true_false' && (
                            <div className="space-y-2">
                                <b>Correct Answer</b>
                                <RadioGroup
                                    value={question.data.options.findIndex(
                                        (option) => option.is_correct,
                                    )}
                                    onChange={(event) => {
                                        const correctIndex = Number(
                                            event.target.value,
                                        );
                                        question.setData(
                                            'options',
                                            question.data.options.map(
                                                (option, index) => ({
                                                    ...option,
                                                    is_correct:
                                                        index === correctIndex,
                                                }),
                                            ),
                                        );
                                    }}
                                >
                                    <FormControlLabel
                                        value={0}
                                        control={<Radio />}
                                        label="True"
                                    />
                                    <FormControlLabel
                                        value={1}
                                        control={<Radio />}
                                        label="False"
                                    />
                                </RadioGroup>
                            </div>
                        )}
                        {question.data.question_type !== 'short_answer' &&
                            question.data.question_type !== 'true_false' && (
                                <div className="space-y-2">
                                    <b>Answer choices</b>
                                    {question.data.options.map((o, i) => (
                                        <div key={i} className="flex gap-2">
                                            <Checkbox
                                                checked={o.is_correct}
                                                onChange={(e) =>
                                                    question.setData(
                                                        'options',
                                                        question.data.options.map(
                                                            (x, j) =>
                                                                j === i
                                                                    ? {
                                                                          ...x,
                                                                          is_correct:
                                                                              e
                                                                                  .target
                                                                                  .checked,
                                                                      }
                                                                    : x,
                                                        ),
                                                    )
                                                }
                                            />
                                            <TextField
                                                fullWidth
                                                value={o.option_text}
                                                onChange={(e) =>
                                                    question.setData(
                                                        'options',
                                                        question.data.options.map(
                                                            (x, j) =>
                                                                j === i
                                                                    ? {
                                                                          ...x,
                                                                          option_text:
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : x,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Button
                                                onClick={() =>
                                                    question.setData(
                                                        'options',
                                                        question.data.options.filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        onClick={() =>
                                            question.setData('options', [
                                                ...question.data.options,
                                                {
                                                    option_text: '',
                                                    is_correct: false,
                                                },
                                            ])
                                        }
                                    >
                                        Add choice
                                    </Button>
                                </div>
                            )}
                    </div>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setQuestionOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        disabled={question.processing}
                        onClick={saveQuestion}
                    >
                        {question.processing ? 'Saving...' : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
Builder.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Assessments', href: '/management/assessments' },
        { title: 'Builder', href: '#' },
    ],
};
