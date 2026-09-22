import { useForm } from '@inertiajs/react';
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
    TextField,
} from '@mui/material';
import { useState } from 'react';
import { CampaignScopeField } from '@/components/campaign-scope-field';
import type { CampaignOption } from '@/components/campaign-scope-field';
import { detectQuestions, questionIssues } from '@/lib/question-import';
import type { ImportedQuestion } from '@/lib/question-import';

type Ref = { id: number; name: string };
type Draft = ImportedQuestion & { selected: boolean };

export function QuestionImportDialog({
    categories,
    skills,
    campaigns,
    onClose,
}: {
    categories: Ref[];
    skills: Ref[];
    campaigns: CampaignOption[];
    onClose: () => void;
}) {
    const form = useForm({
        category_id: '',
        skill_id: '',
        applies_to_all_campaigns: true,
        campaign_ids: [] as number[],
        questions: [] as ImportedQuestion[],
    });
    const [drafts, setDrafts] = useState<Draft[]>([]);
    const [fileName, setFileName] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [page, setPage] = useState(0);
    const update = (index: number, changes: Partial<Draft>) =>
        setDrafts((current) =>
            current.map((item, i) =>
                i === index ? { ...item, ...changes } : item,
            ),
        );
    const selected = drafts.filter((item) => item.selected);
    const invalid = selected.filter((item) => questionIssues(item).length);
    const load = async (file?: File) => {
        if (!file || !form.data.category_id || !form.data.skill_id) {
            return;
        }

        setBusy(true);
        setError('');
        setDrafts([]);
        setFileName(file.name);
        setPage(0);
        form.clearErrors();

        try {
            const questions = await detectQuestions(file);
            setDrafts(questions.map((item) => ({ ...item, selected: true })));
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Unable to read this file.',
            );
        } finally {
            setBusy(false);
        }
    };
    const duplicateCount =
        drafts.length -
        new Set(drafts.map((item) => item.question_text.trim().toLowerCase()))
            .size;

    return (
        <Dialog
            open
            fullWidth
            maxWidth="md"
            onClose={() => !busy && !form.processing && onClose()}
        >
            <DialogTitle>Upload questions</DialogTitle>
            <DialogContent>
                <div className="space-y-4 pt-2">
                    <h3 className="font-semibold">
                        1. Choose where these questions belong
                    </h3>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <TextField
                            required
                            select
                            label="Category"
                            value={form.data.category_id}
                            disabled={
                                busy || form.processing || drafts.length > 0
                            }
                            onChange={(event) =>
                                form.setData('category_id', event.target.value)
                            }
                        >
                            {categories.map((item) => (
                                <MenuItem key={item.id} value={String(item.id)}>
                                    {item.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            required
                            select
                            label="Skill"
                            value={form.data.skill_id}
                            disabled={
                                busy || form.processing || drafts.length > 0
                            }
                            onChange={(event) =>
                                form.setData('skill_id', event.target.value)
                            }
                        >
                            {skills.map((item) => (
                                <MenuItem key={item.id} value={String(item.id)}>
                                    {item.name}
                                </MenuItem>
                            ))}
                        </TextField>
                    </div>
                    {(!categories.length || !skills.length) && (
                        <Alert severity="warning">
                            An active category and skill must exist before
                            questions can be uploaded.
                        </Alert>
                    )}
                    <fieldset
                        disabled={form.processing}
                        className="border-0 p-0"
                    >
                        <CampaignScopeField
                            campaigns={campaigns}
                            all={form.data.applies_to_all_campaigns}
                            selected={form.data.campaign_ids}
                            onChange={(all, ids) =>
                                form.setData((current) => ({
                                    ...current,
                                    applies_to_all_campaigns: all,
                                    campaign_ids: ids,
                                }))
                            }
                        />
                    </fieldset>
                    <h3 className="font-semibold">
                        2. Choose an Excel, Word, or text file
                    </h3>
                    <p className="text-sm text-gray-600">
                        Select Category and Skill first. Every imported question
                        will use those selections, regardless of any labels
                        inside the file. Maximum 5 MB and 100 questions per
                        file.
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outlined"
                            component="label"
                            disabled={
                                !form.data.category_id ||
                                !form.data.skill_id ||
                                busy ||
                                form.processing
                            }
                        >
                            {busy
                                ? 'Detecting questions...'
                                : 'Choose .xlsx, .docx, or .txt file'}
                            <input
                                hidden
                                type="file"
                                accept=".xlsx,.docx,.txt"
                                disabled={
                                    !form.data.category_id ||
                                    !form.data.skill_id ||
                                    busy ||
                                    form.processing
                                }
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    event.target.value = '';
                                    void load(file);
                                }}
                            />
                        </Button>
                        <span className="text-sm">{fileName}</span>
                        {drafts.length > 0 && (
                            <Button
                                disabled={form.processing}
                                onClick={() => {
                                    setDrafts([]);
                                    setFileName('');
                                }}
                            >
                                Change destination / start over
                            </Button>
                        )}
                    </div>
                    <details className="rounded-lg border p-3 text-sm">
                        <summary className="cursor-pointer font-medium">
                            Supported file layouts
                        </summary>
                        <p className="mt-2">
                            Excel: one question per row, with headers such as
                            Question, Option A, Option B, Option C, Option D,
                            Correct Answer, Type, Points, Difficulty,
                            Explanation. Correct answers may use letters (B or
                            A,C) or the exact choice text. A single Question
                            column works for short-answer questions.
                        </p>
                        <p className="mt-2">
                            Word or plain text: numbered questions followed by
                            A. / B. choices and an explicit Answer: line, or a
                            table with the same headers as Excel. Questions with
                            no choices are detected as short answer. Images and
                            scanned text are not read.
                        </p>
                        <pre className="mt-2 rounded bg-gray-50 p-3 whitespace-pre-wrap">
                            {
                                '1. Which greeting is appropriate?\nA. How may I help you?\nB. What do you want?\nAnswer: A\n\n2. Explain how you would handle a complaint.'
                            }
                        </pre>
                        <p>
                            Optional types: multiple_choice, multiple_selection,
                            true_false, short_answer. Answers are never guessed.
                            Existing bank questions are not overwritten.
                        </p>
                    </details>
                    {error && <Alert severity="error">{error}</Alert>}
                    {Object.values(form.errors).length > 0 && (
                        <Alert severity="error">
                            {Object.values(form.errors).join(' ')}
                        </Alert>
                    )}
                    {drafts.length > 0 && (
                        <>
                            <h3 className="font-semibold">
                                3. Review detected questions
                            </h3>
                            <Alert severity="info">
                                {drafts.length} detected · {selected.length}{' '}
                                selected · {invalid.length} selected question(s)
                                need correction. Destination:{' '}
                                {
                                    categories.find(
                                        (item) =>
                                            String(item.id) ===
                                            form.data.category_id,
                                    )?.name
                                }{' '}
                                /{' '}
                                {
                                    skills.find(
                                        (item) =>
                                            String(item.id) ===
                                            form.data.skill_id,
                                    )?.name
                                }
                                .
                            </Alert>
                            {duplicateCount > 0 && (
                                <Alert severity="warning">
                                    {duplicateCount} repeated question(s)
                                    detected in this file. Deselect duplicates
                                    if they are not intentional.
                                </Alert>
                            )}
                            <Button
                                disabled={form.processing}
                                onClick={() =>
                                    setDrafts((current) =>
                                        current.map((item) => ({
                                            ...item,
                                            selected:
                                                questionIssues(item).length ===
                                                0,
                                        })),
                                    )
                                }
                            >
                                Select only valid questions
                            </Button>
                            <fieldset
                                disabled={form.processing}
                                className="space-y-3 border-0 p-0"
                            >
                                {drafts
                                    .slice(page * 10, page * 10 + 10)
                                    .map((item, offset) => {
                                        const index = page * 10 + offset;
                                        const issues = questionIssues(item);

                                        return (
                                            <details
                                                key={index}
                                                className="rounded-xl border p-3"
                                                open={
                                                    issues.length > 0 ||
                                                    offset === 0
                                                }
                                            >
                                                <summary className="cursor-pointer font-medium">
                                                    {index + 1}.{' '}
                                                    {item.question_text.slice(
                                                        0,
                                                        110,
                                                    )}{' '}
                                                    {issues.length > 0
                                                        ? '— needs review'
                                                        : ''}
                                                </summary>
                                                <div className="mt-3 grid gap-3">
                                                    <FormControlLabel
                                                        control={
                                                            <Checkbox
                                                                checked={
                                                                    item.selected
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    update(
                                                                        index,
                                                                        {
                                                                            selected:
                                                                                event
                                                                                    .target
                                                                                    .checked,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        }
                                                        label="Include this question"
                                                    />
                                                    <span className="text-xs text-gray-500">
                                                        {item.source}
                                                    </span>
                                                    {issues.length > 0 && (
                                                        <Alert severity="warning">
                                                            {issues.join(' ')}
                                                        </Alert>
                                                    )}
                                                    <TextField
                                                        multiline
                                                        minRows={2}
                                                        label="Question"
                                                        value={
                                                            item.question_text
                                                        }
                                                        onChange={(event) =>
                                                            update(index, {
                                                                question_text:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                    <div className="grid gap-3 sm:grid-cols-3">
                                                        <TextField
                                                            select
                                                            label="Type"
                                                            value={
                                                                item.question_type
                                                            }
                                                            onChange={(event) =>
                                                                update(index, {
                                                                    question_type:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    options:
                                                                        event
                                                                            .target
                                                                            .value ===
                                                                        'short_answer'
                                                                            ? []
                                                                            : event
                                                                                    .target
                                                                                    .value ===
                                                                                'true_false'
                                                                              ? [
                                                                                    {
                                                                                        option_text:
                                                                                            'True',
                                                                                        is_correct: false,
                                                                                    },
                                                                                    {
                                                                                        option_text:
                                                                                            'False',
                                                                                        is_correct: false,
                                                                                    },
                                                                                ]
                                                                              : item.options,
                                                                })
                                                            }
                                                        >
                                                            {[
                                                                'multiple_choice',
                                                                'multiple_selection',
                                                                'true_false',
                                                                'short_answer',
                                                            ].map((type) => (
                                                                <MenuItem
                                                                    key={type}
                                                                    value={type}
                                                                >
                                                                    {type.replaceAll(
                                                                        '_',
                                                                        ' ',
                                                                    )}
                                                                </MenuItem>
                                                            ))}
                                                        </TextField>
                                                        <TextField
                                                            type="number"
                                                            label="Points"
                                                            value={item.points}
                                                            onChange={(event) =>
                                                                update(index, {
                                                                    points: event
                                                                        .target
                                                                        .value,
                                                                })
                                                            }
                                                        />
                                                        <TextField
                                                            select
                                                            label="Difficulty"
                                                            value={
                                                                item.difficulty
                                                            }
                                                            onChange={(event) =>
                                                                update(index, {
                                                                    difficulty:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                })
                                                            }
                                                        >
                                                            {[
                                                                'easy',
                                                                'medium',
                                                                'hard',
                                                            ].map(
                                                                (
                                                                    difficulty,
                                                                ) => (
                                                                    <MenuItem
                                                                        key={
                                                                            difficulty
                                                                        }
                                                                        value={
                                                                            difficulty
                                                                        }
                                                                    >
                                                                        {
                                                                            difficulty
                                                                        }
                                                                    </MenuItem>
                                                                ),
                                                            )}
                                                        </TextField>
                                                    </div>
                                                    {item.options.map(
                                                        (option, i) => (
                                                            <div
                                                                key={i}
                                                                className="flex items-center gap-2"
                                                            >
                                                                <Checkbox
                                                                    slotProps={{
                                                                        input: {
                                                                            'aria-label':
                                                                                'Correct answer ' +
                                                                                (i +
                                                                                    1),
                                                                        },
                                                                    }}
                                                                    checked={
                                                                        option.is_correct
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        update(
                                                                            index,
                                                                            {
                                                                                options:
                                                                                    item.options.map(
                                                                                        (
                                                                                            choice,
                                                                                            j,
                                                                                        ) => ({
                                                                                            ...choice,
                                                                                            is_correct:
                                                                                                j ===
                                                                                                i
                                                                                                    ? event
                                                                                                          .target
                                                                                                          .checked
                                                                                                    : item.question_type ===
                                                                                                        'multiple_selection'
                                                                                                      ? choice.is_correct
                                                                                                      : false,
                                                                                        }),
                                                                                    ),
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                                <TextField
                                                                    fullWidth
                                                                    label={
                                                                        'Choice ' +
                                                                        (i + 1)
                                                                    }
                                                                    value={
                                                                        option.option_text
                                                                    }
                                                                    disabled={
                                                                        item.question_type ===
                                                                        'true_false'
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        update(
                                                                            index,
                                                                            {
                                                                                options:
                                                                                    item.options.map(
                                                                                        (
                                                                                            choice,
                                                                                            j,
                                                                                        ) =>
                                                                                            j ===
                                                                                            i
                                                                                                ? {
                                                                                                      ...choice,
                                                                                                      option_text:
                                                                                                          event
                                                                                                              .target
                                                                                                              .value,
                                                                                                  }
                                                                                                : choice,
                                                                                    ),
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                                {item.question_type !==
                                                                    'true_false' && (
                                                                    <Button
                                                                        onClick={() =>
                                                                            update(
                                                                                index,
                                                                                {
                                                                                    options:
                                                                                        item.options.filter(
                                                                                            (
                                                                                                _,
                                                                                                j,
                                                                                            ) =>
                                                                                                j !==
                                                                                                i,
                                                                                        ),
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        Remove
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        ),
                                                    )}
                                                    {[
                                                        'multiple_choice',
                                                        'multiple_selection',
                                                    ].includes(
                                                        item.question_type,
                                                    ) && (
                                                        <Button
                                                            disabled={
                                                                item.options
                                                                    .length >=
                                                                20
                                                            }
                                                            onClick={() =>
                                                                update(index, {
                                                                    options: [
                                                                        ...item.options,
                                                                        {
                                                                            option_text:
                                                                                '',
                                                                            is_correct: false,
                                                                        },
                                                                    ],
                                                                })
                                                            }
                                                        >
                                                            Add choice
                                                        </Button>
                                                    )}
                                                    <TextField
                                                        multiline
                                                        label="Explanation / reference answer (optional)"
                                                        value={item.feedback}
                                                        onChange={(event) =>
                                                            update(index, {
                                                                feedback:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                </div>
                                            </details>
                                        );
                                    })}
                            </fieldset>
                            <div className="flex items-center justify-between">
                                <Button
                                    disabled={page === 0}
                                    onClick={() => setPage(page - 1)}
                                >
                                    Previous
                                </Button>
                                <span>
                                    Page {page + 1} of{' '}
                                    {Math.ceil(drafts.length / 10)}
                                </span>
                                <Button
                                    disabled={(page + 1) * 10 >= drafts.length}
                                    onClick={() => setPage(page + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </DialogContent>
            <DialogActions>
                <Button disabled={busy || form.processing} onClick={onClose}>
                    Cancel
                </Button>
                <Button
                    variant="contained"
                    disabled={
                        busy ||
                        form.processing ||
                        !selected.length ||
                        invalid.length > 0 ||
                        !form.data.category_id ||
                        !form.data.skill_id ||
                        (!form.data.applies_to_all_campaigns &&
                            !form.data.campaign_ids.length)
                    }
                    onClick={() => {
                        form.transform((data) => ({
                            ...data,
                            questions: selected.map((question) => ({
                                question_text: question.question_text,
                                question_type: question.question_type,
                                points: question.points,
                                difficulty: question.difficulty,
                                feedback: question.feedback,
                                options: question.options,
                            })),
                        }));
                        form.post('/management/question-bank/import', {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    {form.processing
                        ? 'Importing...'
                        : 'Import ' + selected.length + ' question(s)'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
