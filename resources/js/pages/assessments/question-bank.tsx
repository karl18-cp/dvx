import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    TextField,
} from '@mui/material';
import { useEffect, useState } from 'react';
import {
    CampaignScopeBadge,
    CampaignScopeField,
} from '@/components/campaign-scope-field';
import type { CampaignOption } from '@/components/campaign-scope-field';
import { QuestionImportDialog } from '@/components/question-import-dialog';

type Ref = { id: number; name: string };
type Question = {
    id: number;
    question_text: string;
    question_type: string;
    difficulty: string;
    points: string;
    status: string;
    skill?: Ref;
    category?: Ref;
    feedback?: string;
    options: { option_text: string; is_correct: boolean }[];
    applies_to_all_campaigns: boolean;
    campaigns?: CampaignOption[];
};
type Props = {
    questions: {
        data: Question[];
        total: number;
        prev_page_url?: string;
        next_page_url?: string;
    };
    skills: Ref[];
    categories: Ref[];
    campaigns: CampaignOption[];
    filters: Record<string, string>;
    target_assessment_id?: number;
};
const blank = {
    question_text: '',
    question_type: 'multiple_choice',
    skill_id: '',
    category_id: '',
    difficulty: 'medium',
    points: '1',
    feedback: '',
    applies_to_all_campaigns: true,
    campaign_ids: [] as number[],
    options: [
        { option_text: '', is_correct: true },
        { option_text: '', is_correct: false },
    ],
};

export default function QuestionBank({
    questions,
    skills,
    categories,
    campaigns,
    filters,
    target_assessment_id,
}: Props) {
    const [importOpen, setImportOpen] = useState(false);
    const [open, setOpen] = useState(false),
        [editing, setEditing] = useState<Question | null>(null),
        [search, setSearch] = useState(filters.search || ''),
        [selected, setSelected] = useState<number[]>([]);
    const form = useForm(blank);
    useEffect(() => {
        if (search === (filters.search || '')) {
            return;
        }

        const timer = window.setTimeout(
            () =>
                router.get(
                    '/management/question-bank',
                    { ...filters, search, assessment: target_assessment_id },
                    { preserveState: true, replace: true },
                ),
            400,
        );

        return () => clearTimeout(timer);
    }, [search, filters]);
    const setType = (type: string) =>
        form.setData((data) => ({
            ...data,
            question_type: type,
            options:
                type === 'true_false'
                    ? [
                          { option_text: 'True', is_correct: true },
                          { option_text: 'False', is_correct: false },
                      ]
                    : type === 'short_answer'
                      ? []
                      : data.options.length >= 2
                        ? data.options
                        : blank.options,
        }));
    const showForm = (question?: Question) => {
        setEditing(question || null);
        form.setData(
            question
                ? {
                      question_text: question.question_text,
                      question_type: question.question_type,
                      skill_id: question.skill?.id.toString() || '',
                      category_id: question.category?.id.toString() || '',
                      difficulty: question.difficulty,
                      points: question.points,
                      feedback: question.feedback || '',
                      applies_to_all_campaigns:
                          question.applies_to_all_campaigns,
                      campaign_ids: question.campaigns?.map((c) => c.id) || [],
                      options: question.options.map((option) => ({
                          option_text: option.option_text,
                          is_correct: option.is_correct,
                      })),
                  }
                : blank,
        );
        setOpen(true);
    };
    const save = () => {
        const options = {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        };

        if (editing) {
            form.put(`/management/question-bank/${editing.id}`, options);
        } else {
            form.post('/management/question-bank', options);
        }
    };
    const filter = (key: string, value: string) =>
        router.get(
            '/management/question-bank',
            { ...filters, [key]: value, assessment: target_assessment_id },
            { preserveState: true, replace: true },
        );
    const addSelected = () => {
        if (target_assessment_id) {
            router.post(
                `/management/assessments/${target_assessment_id}/questions/from-bank`,
                { question_ids: selected },
            );
        }
    };

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="Question Bank" />
            <div className="mx-auto max-w-7xl">
                <header className="mb-5 flex justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Question Bank</h1>
                        <p>
                            Reusable assessment questions and source metadata.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outlined"
                            onClick={() => setImportOpen(true)}
                        >
                            Upload questions
                        </Button>
                        <Button variant="contained" onClick={() => showForm()}>
                            Create Question
                        </Button>
                    </div>
                </header>
                {target_assessment_id && (
                    <div className="mb-4 rounded-xl border bg-white p-3">
                        <Button
                            variant="contained"
                            disabled={!selected.length}
                            onClick={addSelected}
                        >
                            Add Selected Questions ({selected.length})
                        </Button>
                    </div>
                )}
                <div className="mb-4 grid gap-2 md:grid-cols-7">
                    <TextField
                        size="small"
                        label="Search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Filter
                        label="Skill"
                        value={filters.skill}
                        values={skills}
                        onChange={(v) => filter('skill', v)}
                    />
                    <Filter
                        label="Category"
                        value={filters.category}
                        values={categories}
                        onChange={(v) => filter('category', v)}
                    />
                    <Filter
                        label="Campaign"
                        value={filters.campaign}
                        values={campaigns}
                        onChange={(v) => filter('campaign', v)}
                    />
                    <Filter
                        label="Difficulty"
                        value={filters.difficulty}
                        values={['easy', 'medium', 'hard']}
                        onChange={(v) => filter('difficulty', v)}
                    />
                    <Filter
                        label="Type"
                        value={filters.type}
                        values={[
                            'multiple_choice',
                            'true_false',
                            'multiple_selection',
                            'short_answer',
                        ]}
                        onChange={(v) => filter('type', v)}
                    />
                    <Filter
                        label="Status"
                        value={filters.status}
                        values={['active', 'archived']}
                        onChange={(v) => filter('status', v)}
                    />
                </div>
                <div className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="w-full text-left">
                        <thead>
                            <tr>
                                {target_assessment_id && (
                                    <th className="p-3">Select</th>
                                )}
                                <th className="p-3">Question</th>
                                <th>Type</th>
                                <th>Skill</th>
                                <th>Category</th>
                                <th>Campaign</th>
                                <th>Difficulty</th>
                                <th>Points</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {questions.data.map((q) => (
                                <tr key={q.id} className="border-t">
                                    {target_assessment_id && (
                                        <td className="p-3">
                                            <Checkbox
                                                disabled={q.status !== 'active'}
                                                checked={selected.includes(
                                                    q.id,
                                                )}
                                                onChange={() =>
                                                    setSelected((current) =>
                                                        current.includes(q.id)
                                                            ? current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      q.id,
                                                              )
                                                            : [
                                                                  ...current,
                                                                  q.id,
                                                              ],
                                                    )
                                                }
                                            />
                                        </td>
                                    )}
                                    <td className="max-w-md p-3">
                                        {q.question_text}
                                    </td>
                                    <td>
                                        <Button onClick={() => showForm(q)}>
                                            Edit
                                        </Button>
                                        {q.question_type.replaceAll('_', ' ')}
                                    </td>
                                    <td>{q.skill?.name || 'Any'}</td>
                                    <td>{q.category?.name || 'Any'}</td>
                                    <td>
                                        <CampaignScopeBadge
                                            all={q.applies_to_all_campaigns}
                                            campaigns={q.campaigns}
                                        />
                                    </td>
                                    <td className="capitalize">
                                        {q.difficulty}
                                    </td>
                                    <td>{q.points}</td>
                                    <td className="capitalize">{q.status}</td>
                                    <td>
                                        <Button
                                            onClick={() =>
                                                router.post(
                                                    `/management/question-bank/${q.id}/duplicate`,
                                                )
                                            }
                                        >
                                            Duplicate
                                        </Button>
                                        {q.status === 'active' && (
                                            <Button
                                                color="warning"
                                                onClick={() =>
                                                    router.patch(
                                                        `/management/question-bank/${q.id}/archive`,
                                                    )
                                                }
                                            >
                                                Archive
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="mt-3">{questions.total} question(s)</p>
            </div>
            {importOpen && (
                <QuestionImportDialog
                    categories={categories}
                    skills={skills}
                    campaigns={campaigns}
                    onClose={() => setImportOpen(false)}
                />
            )}
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                fullWidth
                maxWidth="md"
            >
                <DialogTitle>
                    {editing ? 'Edit' : 'Create'} Bank Question
                </DialogTitle>
                <DialogContent>
                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                        <TextField
                            className="sm:col-span-2"
                            multiline
                            label="Question text"
                            value={form.data.question_text}
                            onChange={(e) =>
                                form.setData('question_text', e.target.value)
                            }
                        />
                        <TextField
                            select
                            label="Type"
                            value={form.data.question_type}
                            onChange={(e) => setType(e.target.value)}
                        >
                            {[
                                'multiple_choice',
                                'true_false',
                                'multiple_selection',
                                'short_answer',
                            ].map((v) => (
                                <MenuItem
                                    className="question-bank-choice"
                                    key={v}
                                    value={v}
                                >
                                    {v.replaceAll('_', ' ')}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Difficulty"
                            value={form.data.difficulty}
                            onChange={(e) =>
                                form.setData('difficulty', e.target.value)
                            }
                        >
                            {['easy', 'medium', 'hard'].map((v) => (
                                <MenuItem
                                    className="question-bank-choice"
                                    key={v}
                                    value={v}
                                >
                                    {v}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Skill"
                            value={form.data.skill_id}
                            onChange={(e) =>
                                form.setData('skill_id', e.target.value)
                            }
                        >
                            <MenuItem className="question-bank-choice" value="">
                                Any
                            </MenuItem>
                            {skills.map((v) => (
                                <MenuItem
                                    className="question-bank-choice"
                                    key={v.id}
                                    value={v.id}
                                >
                                    {v.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Category"
                            value={form.data.category_id}
                            onChange={(e) =>
                                form.setData('category_id', e.target.value)
                            }
                        >
                            <MenuItem className="question-bank-choice" value="">
                                Any
                            </MenuItem>
                            {categories.map((v) => (
                                <MenuItem
                                    className="question-bank-choice"
                                    key={v.id}
                                    value={v.id}
                                >
                                    {v.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            type="number"
                            label="Points"
                            value={form.data.points}
                            onChange={(e) =>
                                form.setData('points', e.target.value)
                            }
                        />
                        {form.data.options.map((o, i) => (
                            <div key={i} className="flex items-center">
                                <Checkbox
                                    checked={o.is_correct}
                                    onChange={(e) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((x, j) => ({
                                                ...x,
                                                is_correct:
                                                    j === i
                                                        ? e.target.checked
                                                        : form.data
                                                                .question_type ===
                                                            'multiple_selection'
                                                          ? x.is_correct
                                                          : false,
                                            })),
                                        )
                                    }
                                />
                                <TextField
                                    fullWidth
                                    label={`Option ${i + 1}`}
                                    value={o.option_text}
                                    disabled={
                                        form.data.question_type === 'true_false'
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((x, j) =>
                                                j === i
                                                    ? {
                                                          ...x,
                                                          option_text:
                                                              e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        ))}
                        <CampaignScopeField
                            campaigns={campaigns}
                            all={form.data.applies_to_all_campaigns}
                            selected={form.data.campaign_ids}
                            onChange={(all, ids) =>
                                form.setData((data) => ({
                                    ...data,
                                    applies_to_all_campaigns: all,
                                    campaign_ids: ids,
                                }))
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setOpen(false)}>Cancel</Button>
                    <Button variant="contained" onClick={save}>
                        Save
                    </Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}
function Filter({
    label,
    value = '',
    values,
    onChange,
}: {
    label: string;
    value?: string;
    values: Ref[] | string[];
    onChange: (value: string) => void;
}) {
    return (
        <TextField
            select
            size="small"
            label={label}
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <MenuItem className="question-bank-choice" value="">
                All
            </MenuItem>
            {values.map((v) =>
                typeof v === 'string' ? (
                    <MenuItem
                        className="question-bank-choice"
                        key={v}
                        value={v}
                    >
                        {v.replaceAll('_', ' ')}
                    </MenuItem>
                ) : (
                    <MenuItem
                        className="question-bank-choice"
                        key={v.id}
                        value={v.id}
                    >
                        {v.name}
                    </MenuItem>
                ),
            )}
        </TextField>
    );
}
QuestionBank.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Question Bank', href: '/management/question-bank' },
    ],
};
