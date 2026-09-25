import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
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
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CampaignScopeField } from '@/components/campaign-scope-field';
import type { CampaignOption } from '@/components/campaign-scope-field';
import { useConfirmation } from '@/hooks/use-confirmation';

type Skill = { id: number; name: string };
type Criterion = {
    id: number;
    label: string;
    guidance: string | null;
    points_possible: string;
    skill_id: number | null;
    skill: Skill | null;
    is_required: boolean;
    is_critical: boolean;
    allows_na: boolean;
    display_order: number;
};
type Category = {
    id: number;
    name: string;
    description: string | null;
    display_order: number;
    criteria: Criterion[];
};
type Scorecard = {
    id: number;
    name: string;
    description: string | null;
    passing_score: string;
    applies_to_all_campaigns: boolean;
    campaigns: CampaignOption[];
    categories: Category[];
    status: string;
};

export default function ScorecardBuilder({
    scorecard,
    campaigns,
    skills,
    total_points,
}: {
    scorecard: Scorecard;
    campaigns: CampaignOption[];
    skills: Skill[];
    total_points: number;
}) {
    const confirmAction = useConfirmation();
    const readOnly = scorecard.status === 'archived';
    const page = usePage<{ errors: Record<string, string | string[]> }>();
    const settings = useForm({
        name: scorecard.name,
        description: scorecard.description || '',
        passing_score: Number(scorecard.passing_score),
        applies_to_all_campaigns: scorecard.applies_to_all_campaigns,
        campaign_ids: scorecard.campaigns.map((campaign) => campaign.id),
    });
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(
        null,
    );
    const categoryForm = useForm({ name: '', description: '' });
    const [criterionOpen, setCriterionOpen] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const [criterionCategory, setCriterionCategory] = useState<Category | null>(
        null,
    );
    const [editingCriterion, setEditingCriterion] = useState<Criterion | null>(
        null,
    );
    const criterionForm = useForm({
        label: '',
        guidance: '',
        points_possible: 5,
        skill_id: '' as number | '',
        is_required: true,
        is_critical: false,
        allows_na: false,
    });
    const openCategory = (category?: Category) => {
        setEditingCategory(category || null);
        categoryForm.setData({
            name: category?.name || '',
            description: category?.description || '',
        });
        setCategoryOpen(true);
    };
    const saveCategory = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setCategoryOpen(false),
        };

        if (editingCategory) {
            categoryForm.put(
                `/management/qa-scorecards/${scorecard.id}/categories/${editingCategory.id}`,
                options,
            );
        } else {
            categoryForm.post(
                `/management/qa-scorecards/${scorecard.id}/categories`,
                options,
            );
        }
    };
    const openCriterion = (category: Category, criterion?: Criterion) => {
        setCriterionCategory(category);
        setEditingCriterion(criterion || null);
        setAdvancedOpen(
            Boolean(
                criterion &&
                (criterion.skill_id ||
                    criterion.is_critical ||
                    criterion.allows_na),
            ),
        );
        criterionForm.setData({
            label: criterion?.label || '',
            guidance: criterion?.guidance || '',
            points_possible: Number(criterion?.points_possible || 5),
            skill_id: criterion?.skill_id || '',
            is_required: criterion?.is_required ?? true,
            is_critical: criterion?.is_critical ?? false,
            allows_na: criterion?.allows_na ?? false,
        });
        setCriterionOpen(true);
    };
    const saveCriterion = () => {
        if (!criterionCategory) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => setCriterionOpen(false),
        };
        const base = `/management/qa-scorecards/${scorecard.id}/categories/${criterionCategory.id}/criteria`;

        if (editingCriterion) {
            criterionForm.put(`${base}/${editingCriterion.id}`, options);
        } else {
            criterionForm.post(base, options);
        }
    };
    const move = (url: string, direction: 'up' | 'down') =>
        router.patch(url, { direction }, { preserveScroll: true });
    const activationErrors = Object.entries(page.props.errors)
        .filter(([key]) => key === 'activation')
        .flatMap(([, value]) => (Array.isArray(value) ? value : [value]));

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-[13px]">
            <Head title={`Scorecard: ${scorecard.name}`} />
            <div className="mx-auto max-w-7xl">
                <header className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href="/management/qa-scorecards"
                            className="font-semibold text-red-700"
                        >
                            ← QA Scorecards
                        </Link>
                        <h1 className="text-2xl font-bold">{scorecard.name}</h1>
                        <p className="text-slate-500 capitalize">
                            {scorecard.status} Scorecard
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={`/management/qa-scorecards/${scorecard.id}/preview`}
                        >
                            <Button variant="outlined">Preview</Button>
                        </Link>
                        {scorecard.status === 'draft' && (
                            <Button
                                color="success"
                                variant="contained"
                                onClick={() =>
                                    router.post(
                                        `/management/qa-scorecards/${scorecard.id}/activate`,
                                    )
                                }
                            >
                                Activate
                            </Button>
                        )}
                        {scorecard.status !== 'archived' && (
                            <Button
                                variant="outlined"
                                onClick={() =>
                                    router.post(
                                        `/management/qa-scorecards/${scorecard.id}/archive`,
                                    )
                                }
                            >
                                Archive
                            </Button>
                        )}
                    </div>
                </header>
                {scorecard.status === 'active' && (
                    <div className="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-900">
                        Changes apply only to future Call Evaluations. Existing
                        evaluations retain their original scorecard snapshot.
                    </div>
                )}
                {readOnly && (
                    <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        Archived Scorecards are read-only. Duplicate this
                        Scorecard to create an editable Draft.
                    </div>
                )}
                {activationErrors.length > 0 && (
                    <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
                        <strong>Cannot activate Scorecard:</strong>
                        <ul className="ml-5 list-disc">
                            {activationErrors.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    </div>
                )}
                <section className="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <h2 className="font-bold text-blue-950">
                        How to build this Scorecard
                    </h2>
                    <div className="mt-2 grid gap-2 text-xs text-blue-900 sm:grid-cols-4">
                        <span>
                            <strong>1.</strong> Check the basic settings.
                        </span>
                        <span>
                            <strong>2.</strong> Add sections such as Opening or
                            Closing.
                        </span>
                        <span>
                            <strong>3.</strong> Add the items an evaluator will
                            check.
                        </span>
                        <span>
                            <strong>4.</strong> Preview, then activate.
                        </span>
                    </div>
                </section>
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                    <div className="space-y-4">
                        <section className="rounded-xl border bg-white p-4">
                            <h2 className="mb-3 text-base font-bold">
                                1. Basic Settings
                            </h2>
                            <div className="grid gap-3 md:grid-cols-2">
                                <TextField
                                    size="small"
                                    label="Name"
                                    disabled={readOnly}
                                    value={settings.data.name}
                                    error={!!settings.errors.name}
                                    helperText={settings.errors.name}
                                    onChange={(event) =>
                                        settings.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <TextField
                                    size="small"
                                    type="number"
                                    label="Passing Score (%)"
                                    disabled={readOnly}
                                    value={settings.data.passing_score}
                                    error={!!settings.errors.passing_score}
                                    helperText={settings.errors.passing_score}
                                    onChange={(event) =>
                                        settings.setData(
                                            'passing_score',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <TextField
                                    className="md:col-span-2"
                                    multiline
                                    minRows={2}
                                    size="small"
                                    label="Description"
                                    disabled={readOnly}
                                    value={settings.data.description}
                                    onChange={(event) =>
                                        settings.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                                <div className="md:col-span-2">
                                    <CampaignScopeField
                                        campaigns={campaigns}
                                        all={
                                            settings.data
                                                .applies_to_all_campaigns
                                        }
                                        selected={settings.data.campaign_ids}
                                        onChange={(all, ids) => {
                                            settings.setData(
                                                'applies_to_all_campaigns',
                                                all,
                                            );
                                            settings.setData(
                                                'campaign_ids',
                                                ids,
                                            );
                                        }}
                                    />
                                </div>
                            </div>
                            {!readOnly && (
                                <div className="mt-3 text-right">
                                    <Button
                                        variant="contained"
                                        disabled={settings.processing}
                                        onClick={() =>
                                            settings.put(
                                                `/management/qa-scorecards/${scorecard.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Save Settings
                                    </Button>
                                </div>
                            )}
                        </section>
                        <section className="rounded-xl border bg-white p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <div>
                                    <h2 className="text-base font-bold">
                                        2. Evaluation Checklist
                                    </h2>
                                    <p className="text-xs text-slate-500">
                                        Create sections, then add the behaviors
                                        an evaluator should check.
                                    </p>
                                </div>
                                {!readOnly && (
                                    <Button
                                        size="small"
                                        startIcon={<Plus size={15} />}
                                        onClick={() => openCategory()}
                                    >
                                        Add Section
                                    </Button>
                                )}
                            </div>
                            <div className="space-y-3">
                                {scorecard.categories.map(
                                    (category, categoryIndex) => (
                                        <article
                                            key={category.id}
                                            className="overflow-hidden rounded-lg border"
                                        >
                                            <header className="flex items-center justify-between bg-slate-50 px-3 py-2">
                                                <div>
                                                    <strong className="text-sm">
                                                        {category.name}
                                                    </strong>
                                                    {category.description && (
                                                        <p className="text-xs text-slate-500">
                                                            {
                                                                category.description
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {!readOnly && (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            size="small"
                                                            onClick={() =>
                                                                openCriterion(
                                                                    category,
                                                                )
                                                            }
                                                        >
                                                            <Plus size={14} />{' '}
                                                            Check Item
                                                        </Button>
                                                        <button
                                                            aria-label="Move category up"
                                                            disabled={
                                                                categoryIndex ===
                                                                0
                                                            }
                                                            onClick={() =>
                                                                move(
                                                                    `/management/qa-scorecards/${scorecard.id}/categories/${category.id}/move`,
                                                                    'up',
                                                                )
                                                            }
                                                        >
                                                            <ArrowUp
                                                                size={16}
                                                            />
                                                        </button>
                                                        <button
                                                            aria-label="Move category down"
                                                            disabled={
                                                                categoryIndex ===
                                                                scorecard
                                                                    .categories
                                                                    .length -
                                                                    1
                                                            }
                                                            onClick={() =>
                                                                move(
                                                                    `/management/qa-scorecards/${scorecard.id}/categories/${category.id}/move`,
                                                                    'down',
                                                                )
                                                            }
                                                        >
                                                            <ArrowDown
                                                                size={16}
                                                            />
                                                        </button>
                                                        <button
                                                            aria-label="Edit category"
                                                            onClick={() =>
                                                                openCategory(
                                                                    category,
                                                                )
                                                            }
                                                        >
                                                            <Pencil size={15} />
                                                        </button>
                                                        <button
                                                            aria-label="Delete category"
                                                            className="text-red-700"
                                                            onClick={async () =>
                                                                (await confirmAction(
                                                                    `Delete ${category.name} and its ${category.criteria.length} Criteria? Historical Evaluation snapshots will not be affected.`,
                                                                )) &&
                                                                router.delete(
                                                                    `/management/qa-scorecards/${scorecard.id}/categories/${category.id}`,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={15} />
                                                        </button>
                                                    </div>
                                                )}
                                            </header>
                                            <div>
                                                {category.criteria.map(
                                                    (
                                                        criterion,
                                                        criterionIndex,
                                                    ) => (
                                                        <div
                                                            key={criterion.id}
                                                            className="flex items-start justify-between gap-3 border-t px-3 py-2"
                                                        >
                                                            <div className="min-w-0">
                                                                <strong>
                                                                    {
                                                                        criterion.label
                                                                    }
                                                                </strong>
                                                                <div className="mt-1 flex flex-wrap gap-1 text-[11px]">
                                                                    <span className="rounded bg-slate-100 px-2 py-0.5">
                                                                        {Number(
                                                                            criterion.points_possible,
                                                                        )}{' '}
                                                                        points
                                                                    </span>
                                                                    {criterion.is_required && (
                                                                        <span className="rounded bg-blue-50 px-2 py-0.5 text-blue-800">
                                                                            Must
                                                                            answer
                                                                        </span>
                                                                    )}
                                                                    {criterion.is_critical && (
                                                                        <span className="rounded bg-red-50 px-2 py-0.5 text-red-800">
                                                                            Critical
                                                                        </span>
                                                                    )}
                                                                    {criterion.allows_na && (
                                                                        <span className="rounded bg-amber-50 px-2 py-0.5 text-amber-800">
                                                                            N/A
                                                                            allowed
                                                                        </span>
                                                                    )}
                                                                    {criterion.skill && (
                                                                        <span className="rounded bg-green-50 px-2 py-0.5 text-green-800">
                                                                            {
                                                                                criterion
                                                                                    .skill
                                                                                    .name
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                {criterion.guidance && (
                                                                    <p className="mt-1 text-xs text-slate-500">
                                                                        {
                                                                            criterion.guidance
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                            {!readOnly && (
                                                                <div className="flex shrink-0 gap-2">
                                                                    <button
                                                                        aria-label="Move criterion up"
                                                                        disabled={
                                                                            criterionIndex ===
                                                                            0
                                                                        }
                                                                        onClick={() =>
                                                                            move(
                                                                                `/management/qa-scorecards/${scorecard.id}/categories/${category.id}/criteria/${criterion.id}/move`,
                                                                                'up',
                                                                            )
                                                                        }
                                                                    >
                                                                        <ArrowUp
                                                                            size={
                                                                                15
                                                                            }
                                                                        />
                                                                    </button>
                                                                    <button
                                                                        aria-label="Move criterion down"
                                                                        disabled={
                                                                            criterionIndex ===
                                                                            category
                                                                                .criteria
                                                                                .length -
                                                                                1
                                                                        }
                                                                        onClick={() =>
                                                                            move(
                                                                                `/management/qa-scorecards/${scorecard.id}/categories/${category.id}/criteria/${criterion.id}/move`,
                                                                                'down',
                                                                            )
                                                                        }
                                                                    >
                                                                        <ArrowDown
                                                                            size={
                                                                                15
                                                                            }
                                                                        />
                                                                    </button>
                                                                    <button
                                                                        aria-label="Edit criterion"
                                                                        onClick={() =>
                                                                            openCriterion(
                                                                                category,
                                                                                criterion,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Pencil
                                                                            size={
                                                                                15
                                                                            }
                                                                        />
                                                                    </button>
                                                                    <button
                                                                        aria-label="Delete criterion"
                                                                        className="text-red-700"
                                                                        onClick={async () =>
                                                                            (await confirmAction(
                                                                                `Delete “${criterion.label}”?`,
                                                                            )) &&
                                                                            router.delete(
                                                                                `/management/qa-scorecards/${scorecard.id}/categories/${category.id}/criteria/${criterion.id}`,
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2
                                                                            size={
                                                                                15
                                                                            }
                                                                        />
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    ),
                                                )}
                                                {!category.criteria.length && (
                                                    <p className="border-t p-3 text-center text-xs text-slate-500">
                                                        No Check Items yet.
                                                    </p>
                                                )}
                                            </div>
                                        </article>
                                    ),
                                )}
                                {!scorecard.categories.length && (
                                    <div className="rounded-lg border border-dashed p-8 text-center text-slate-500">
                                        Add a Section such as Opening, Call
                                        Handling, Compliance, or Closing.
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>
                    <aside className="space-y-4">
                        <section className="sticky top-4 rounded-xl border bg-white p-4">
                            <h2 className="text-base font-bold">
                                3. Score Summary
                            </h2>
                            <dl className="mt-3 space-y-2">
                                <div className="flex justify-between">
                                    <dt>Total Possible Points</dt>
                                    <dd className="font-bold">
                                        {Number(total_points)} points
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Passing Score</dt>
                                    <dd className="font-bold">
                                        {Number(scorecard.passing_score)}%
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Sections</dt>
                                    <dd>{scorecard.categories.length}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Check Items</dt>
                                    <dd>
                                        {scorecard.categories.reduce(
                                            (sum, category) =>
                                                sum + category.criteria.length,
                                            0,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            {scorecard.status === 'draft' && (
                                <Button
                                    fullWidth
                                    color="success"
                                    variant="contained"
                                    className="mt-4"
                                    onClick={() =>
                                        router.post(
                                            `/management/qa-scorecards/${scorecard.id}/activate`,
                                        )
                                    }
                                >
                                    Validate &amp; Activate
                                </Button>
                            )}
                            {scorecard.status === 'draft' && (
                                <Button
                                    fullWidth
                                    color="error"
                                    className="mt-2"
                                    onClick={async () =>
                                        (await confirmAction(
                                            'Delete this unused Draft permanently?',
                                        )) &&
                                        router.delete(
                                            `/management/qa-scorecards/${scorecard.id}`,
                                        )
                                    }
                                >
                                    Delete Draft
                                </Button>
                            )}
                        </section>
                    </aside>
                </div>
            </div>
            <Dialog
                open={categoryOpen}
                onClose={() => setCategoryOpen(false)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle>
                    {editingCategory ? 'Edit Section' : 'Add Section'}
                </DialogTitle>
                <DialogContent sx={{ pt: 1.5, pb: 2, display: 'grid', gap: 2 }}>
                    <TextField
                        fullWidth
                        size="small"
                        label="Name"
                        value={categoryForm.data.name}
                        error={!!categoryForm.errors.name}
                        helperText={categoryForm.errors.name}
                        onChange={(event) =>
                            categoryForm.setData('name', event.target.value)
                        }
                    />
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        size="small"
                        label="Description (optional)"
                        value={categoryForm.data.description}
                        onChange={(event) =>
                            categoryForm.setData(
                                'description',
                                event.target.value,
                            )
                        }
                    />
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2.5, gap: 1 }}>
                    <Button onClick={() => setCategoryOpen(false)}>
                        Cancel
                    </Button>
                    <Button variant="contained" onClick={saveCategory}>
                        Save
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={criterionOpen}
                onClose={() => setCriterionOpen(false)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle>
                    {editingCriterion ? 'Edit Check Item' : 'Add Check Item'}
                </DialogTitle>
                <DialogContent sx={{ pt: 1.5, pb: 2, display: 'grid', gap: 2 }}>
                    <TextField
                        fullWidth
                        size="small"
                        label="What should the evaluator check?"
                        value={criterionForm.data.label}
                        error={!!criterionForm.errors.label}
                        helperText={criterionForm.errors.label}
                        onChange={(event) =>
                            criterionForm.setData('label', event.target.value)
                        }
                    />
                    <TextField
                        fullWidth
                        multiline
                        minRows={2}
                        size="small"
                        label="Instructions for the evaluator (optional)"
                        value={criterionForm.data.guidance}
                        onChange={(event) =>
                            criterionForm.setData(
                                'guidance',
                                event.target.value,
                            )
                        }
                    />
                    <div className="grid gap-3">
                        <TextField
                            size="small"
                            type="number"
                            label="Points"
                            value={criterionForm.data.points_possible}
                            error={!!criterionForm.errors.points_possible}
                            helperText={criterionForm.errors.points_possible}
                            onChange={(event) =>
                                criterionForm.setData(
                                    'points_possible',
                                    Number(event.target.value),
                                )
                            }
                        />
                        {advancedOpen && (
                            <TextField
                                select
                                size="small"
                                label="Related Skill (optional)"
                                value={criterionForm.data.skill_id}
                                onChange={(event) =>
                                    criterionForm.setData(
                                        'skill_id',
                                        event.target.value
                                            ? Number(event.target.value)
                                            : '',
                                    )
                                }
                            >
                                <MenuItem value="">None</MenuItem>
                                {skills.map((skill) => (
                                    <MenuItem key={skill.id} value={skill.id}>
                                        {skill.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                        )}
                    </div>
                    <div className="flex flex-wrap">
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={criterionForm.data.is_required}
                                    onChange={(event) =>
                                        criterionForm.setData(
                                            'is_required',
                                            event.target.checked,
                                        )
                                    }
                                />
                            }
                            label="Evaluator must answer this item"
                        />
                        <Button
                            size="small"
                            onClick={() => setAdvancedOpen((value) => !value)}
                        >
                            {advancedOpen
                                ? 'Hide Advanced Options'
                                : 'Show Advanced Options'}
                        </Button>
                        {advancedOpen && (
                            <>
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={
                                                criterionForm.data.is_critical
                                            }
                                            onChange={(event) => {
                                                criterionForm.setData(
                                                    'is_critical',
                                                    event.target.checked,
                                                );

                                                if (event.target.checked) {
                                                    criterionForm.setData(
                                                        'allows_na',
                                                        false,
                                                    );
                                                }
                                            }}
                                        />
                                    }
                                    label="Critical failure item"
                                />
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={
                                                criterionForm.data.allows_na
                                            }
                                            onChange={(event) =>
                                                criterionForm.setData(
                                                    'allows_na',
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                    }
                                    label="Evaluator may choose N/A"
                                />
                            </>
                        )}
                    </div>
                    {advancedOpen && (
                        <p className="text-xs text-slate-500">
                            Critical criteria may trigger Auto-Fail during a
                            future Call Evaluation only when explicitly
                            confirmed. This Builder stores configuration
                            metadata only.
                        </p>
                    )}
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2.5, gap: 1 }}>
                    <Button onClick={() => setCriterionOpen(false)}>
                        Cancel
                    </Button>
                    <Button variant="contained" onClick={saveCriterion}>
                        Save Check Item
                    </Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}

ScorecardBuilder.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'QA Scorecards', href: '/management/qa-scorecards' },
        { title: 'Builder', href: '#' },
    ],
};
