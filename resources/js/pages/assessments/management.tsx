import { Head, Link, router } from '@inertiajs/react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    Menu,
    MenuItem,
    TextField,
} from '@mui/material';
import { BookOpenCheck, MoreVertical, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AssessmentStatusBadge } from '@/components/assessment-status-badge';
import {
    CampaignScopeBadge,
    CampaignScopeField,
} from '@/components/campaign-scope-field';
import type { CampaignOption } from '@/components/campaign-scope-field';
import { DateTimeField } from '@/components/date-time-field';
import { useConfirmation } from '@/hooks/use-confirmation';

type Category = { id: number; name: string };
type Assessment = {
    id: number;
    title: string;
    description?: string;
    instructions?: string;
    difficulty: string;
    passing_score: string;
    time_limit_minutes?: number;
    maximum_attempts: number;
    available_at?: string;
    due_at?: string;
    publish_at?: string;
    allow_retake: boolean;
    randomize_questions: boolean;
    randomize_answers: boolean;
    show_correct_answers: boolean;
    status: string;
    simple_builder_enabled: boolean;
    planned_question_count?: number;
    planned_total_points?: number;
    category?: Category;
    creator: { name: string };
    updated_at: string;
    assignments_count: number;
    assigned_count: number;
    in_progress_count: number;
    pending_review_count: number;
    passed_count: number;
    failed_count: number;
    applies_to_all_campaigns: boolean;
    campaigns: CampaignOption[];
};
type PageData = {
    data: Assessment[];
    current_page: number;
    last_page: number;
    prev_page_url?: string;
    next_page_url?: string;
    total: number;
};
type Props = {
    assessments: PageData;
    categories: Category[];
    campaigns: CampaignOption[];
    filters: {
        status: string;
        search: string;
        category: string;
        campaign: string;
        from: string;
        to: string;
    };
};

function AssessmentActions({
    item,
    onEdit,
}: {
    item: Assessment;
    onEdit: (item: Assessment) => void;
}) {
    const confirmAction = useConfirmation();
    const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
    const close = () => setAnchorEl(null);
    const builderUrl = `/management/assessments/${item.id}/${item.simple_builder_enabled ? 'simple-builder' : 'builder'}`;
    const actions = [
        { label: 'Open', run: () => router.visit(builderUrl) },
        {
            label: 'Clone',
            run: async () => {
                if ((await confirmAction(`Clone ${item.title} as a new draft?`))) {
                    router.post(`/management/assessments/${item.id}/clone`);
                }
            },
        },
        {
            label: 'Preview',
            run: () =>
                router.visit(`/management/assessments/${item.id}/preview`),
        },
        ...(item.status !== 'archived'
            ? [
                  {
                      label: 'Edit',
                      run: () =>
                          item.simple_builder_enabled
                              ? router.visit(builderUrl)
                              : onEdit(item),
                  },
              ]
            : []),
        ...(item.status === 'draft'
            ? [
                  {
                      label: 'Publish',
                      run: async () => {
                          if ((await confirmAction('Publish this assessment?'))) {
                              router.patch(
                                  `/management/assessments/${item.id}/publish`,
                              );
                          }
                      },
                  },
              ]
            : []),
        ...(item.status !== 'archived'
            ? [
                  {
                      label: 'Archive',
                      run: async () => {
                          if ((await confirmAction('Archive this assessment?'))) {
                              router.patch(
                                  `/management/assessments/${item.id}/archive`,
                              );
                          }
                      },
                  },
              ]
            : []),
    ];

    return (
        <div className="flex justify-end">
            <IconButton
                id={`assessment-actions-${item.id}`}
                aria-label={`Actions for ${item.title}`}
                aria-haspopup="menu"
                aria-expanded={Boolean(anchorEl)}
                aria-controls={
                    anchorEl ? `assessment-menu-${item.id}` : undefined
                }
                onClick={(event) => setAnchorEl(event.currentTarget)}
                size="small"
            >
                <MoreVertical size={20} />
            </IconButton>
            <Menu
                id={`assessment-menu-${item.id}`}
                anchorEl={anchorEl}
                open={Boolean(anchorEl)}
                onClose={close}
                slotProps={{
                    list: {
                        'aria-labelledby': `assessment-actions-${item.id}`,
                    },
                }}
            >
                {actions.map((action) => (
                    <MenuItem
                        key={action.label}
                        onClick={() => {
                            close();
                            action.run();
                        }}
                    >
                        {action.label}
                    </MenuItem>
                ))}
            </Menu>
        </div>
    );
}

const blank = {
    title: '',
    description: '',
    instructions: '',
    category_id: '',
    difficulty: 'beginner',
    passing_score: '75',
    time_limit_minutes: '',
    maximum_attempts: '1',
    available_at: '',
    due_at: '',
    publish_at: '',
    allow_retake: false,
    randomize_questions: false,
    randomize_answers: false,
    show_correct_answers: false,
    applies_to_all_campaigns: true,
    campaign_ids: [] as number[],
};
const localDateTime = (value?: string) =>
    value
        ? new Intl.DateTimeFormat('sv-SE', {
              timeZone: 'Asia/Manila',
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              hourCycle: 'h23',
          })
              .format(new Date(value))
              .replace(' ', 'T')
        : '';

export default function AssessmentManagement({
    assessments,
    categories,
    campaigns,
    filters,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Assessment | null>(null);
    const [form, setForm] =
        useState<Record<string, string | boolean | number[]>>(blank);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [searchInput, setSearchInput] = useState(filters.search);
    const showForm = (item?: Assessment) => {
        setEditing(item || null);
        setErrors({});
        setForm(
            item
                ? {
                      title: item.title,
                      description: item.description || '',
                      instructions: item.instructions || '',
                      category_id: item.category?.id?.toString() || '',
                      difficulty: item.difficulty,
                      passing_score: item.passing_score,
                      time_limit_minutes:
                          item.time_limit_minutes?.toString() || '',
                      maximum_attempts: item.maximum_attempts.toString(),
                      available_at: localDateTime(item.available_at),
                      due_at: localDateTime(item.due_at),
                      publish_at: localDateTime(item.publish_at),
                      allow_retake: item.allow_retake,
                      randomize_questions: item.randomize_questions,
                      randomize_answers: item.randomize_answers,
                      show_correct_answers: item.show_correct_answers,
                      applies_to_all_campaigns: item.applies_to_all_campaigns,
                      campaign_ids: item.campaigns.map(
                          (campaign) => campaign.id,
                      ),
                  }
                : blank,
        );
        setOpen(true);
    };
    const set = (key: string, value: string | boolean | number[]) =>
        setForm((current) => ({ ...current, [key]: value }));
    const save = () => {
        setProcessing(true);
        const options = {
            onError: (e: Record<string, string>) => setErrors(e),
            onSuccess: () => setOpen(false),
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        };

        if (editing) {
            router.put(`/management/assessments/${editing.id}`, form, options);
        } else {
            router.post('/management/assessments', form, options);
        }
    };
    const filter = (key: string, value: string) =>
        router.get(
            '/management/assessments',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    useEffect(() => {
        if (searchInput === filters.search) {
            return;
        }

        const timeout = window.setTimeout(
            () =>
                router.get(
                    '/management/assessments',
                    { ...filters, search: searchInput },
                    { preserveState: true, replace: true },
                ),
            450,
        );

        return () => window.clearTimeout(timeout);
    }, [searchInput, filters]);

    return (
        <>
            <Head title="Assessment Management" />
            <main className="assessment-admin min-h-full bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-[1400px] space-y-5">
                    <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-xs font-bold tracking-[.2em] text-[#b72822] uppercase">
                                Training & Assessments
                            </p>
                            <h1 className="mt-1 font-bold text-[#1b1d2a]">
                                Assessment Management
                            </h1>
                            <p className="mt-1 text-[#777b8e]">
                                Create, review, publish, and archive assessment
                                definitions.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2 sm:justify-end">
                            <Link href="/management/assessments/create">
                                <Button
                                    variant="contained"
                                    startIcon={<Plus size={18} />}
                                >
                                    Create Assessment
                                </Button>
                            </Link>
                        </div>
                    </header>
                    <section className="rounded-2xl border border-[#e6e7ec] bg-white p-4 shadow-sm">
                        <div className="mb-5 flex flex-col gap-3 sm:flex-row">
                            <TextField
                                size="small"
                                label="Search"
                                value={searchInput}
                                onChange={(event) =>
                                    setSearchInput(event.target.value)
                                }
                            />
                            <TextField
                                select
                                size="small"
                                label="Status"
                                value={filters.status}
                                onChange={(e) =>
                                    filter('status', e.target.value)
                                }
                                sx={{ minWidth: 150 }}
                            >
                                <MenuItem value="">All</MenuItem>
                                <MenuItem value="draft">Draft</MenuItem>
                                <MenuItem value="published">Published</MenuItem>
                                <MenuItem value="archived">Archived</MenuItem>
                            </TextField>
                            <TextField
                                select
                                size="small"
                                label="Category"
                                value={filters.category}
                                onChange={(e) =>
                                    filter('category', e.target.value)
                                }
                                sx={{ minWidth: 170 }}
                            >
                                <MenuItem value="">All categories</MenuItem>
                                {categories.map((category) => (
                                    <MenuItem
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                select
                                size="small"
                                label="Campaign"
                                value={filters.campaign}
                                onChange={(e) =>
                                    filter('campaign', e.target.value)
                                }
                                sx={{ minWidth: 170 }}
                            >
                                <MenuItem value="">All Campaigns</MenuItem>
                                {campaigns.map((campaign) => (
                                    <MenuItem
                                        key={campaign.id}
                                        value={campaign.id}
                                    >
                                        {campaign.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <DateTimeField
                                size="small"
                                type="date"
                                label="Created from"
                                value={filters.from}
                                onChange={(e) => filter('from', e.target.value)}
                                slotProps={{ inputLabel: { shrink: true } }}
                            />
                            <DateTimeField
                                size="small"
                                type="date"
                                label="Created to"
                                value={filters.to}
                                onChange={(e) => filter('to', e.target.value)}
                                slotProps={{ inputLabel: { shrink: true } }}
                            />
                            {Object.values(filters).some(Boolean) && (
                                <Button
                                    onClick={() =>
                                        router.get('/management/assessments')
                                    }
                                >
                                    Clear Filters
                                </Button>
                            )}
                        </div>
                        {Object.entries(filters).some(([, value]) =>
                            Boolean(value),
                        ) && (
                            <div className="mb-4 flex flex-wrap gap-2 text-xs">
                                {Object.entries(filters)
                                    .filter(([, value]) => Boolean(value))
                                    .map(([key, value]) => (
                                        <button
                                            key={key}
                                            onClick={() => filter(key, '')}
                                            className="rounded-full bg-[#f2f2f6] px-3 py-1"
                                        >
                                            {key}: {value} ×
                                        </button>
                                    ))}
                            </div>
                        )}
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[800px] text-left">
                                <thead>
                                    <tr className="border-b text-xs text-[#6f7282] uppercase">
                                        <th className="p-3">Assessment</th>
                                        <th className="p-3">Category</th>
                                        <th className="p-3">Campaign</th>
                                        <th className="p-3">Passing</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Completion</th>
                                        <th className="p-3">Owner</th>
                                        <th className="p-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {assessments.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-[#ededf1]"
                                        >
                                            <td className="p-3">
                                                <button
                                                    className="font-semibold text-[#202230] hover:text-[#ad2924]"
                                                    onClick={() =>
                                                        showForm(item)
                                                    }
                                                >
                                                    {item.title}
                                                </button>
                                                <div className="text-xs text-[#888b9b] capitalize">
                                                    {item.difficulty}
                                                </div>
                                                <div className="mt-1 text-xs text-[#777b8e]">
                                                    {item.publish_at &&
                                                        item.status ===
                                                            'draft' && (
                                                            <>
                                                                Publishes{' '}
                                                                {new Date(
                                                                    item.publish_at,
                                                                ).toLocaleString(
                                                                    'en-US',
                                                                    {
                                                                        timeZone:
                                                                            'Asia/Manila',
                                                                    },
                                                                )}
                                                                <br />
                                                            </>
                                                        )}
                                                    {item.available_at && (
                                                        <>
                                                            Available{' '}
                                                            {new Date(
                                                                item.available_at,
                                                            ).toLocaleString(
                                                                'en-US',
                                                                {
                                                                    timeZone:
                                                                        'Asia/Manila',
                                                                },
                                                            )}
                                                            <br />
                                                        </>
                                                    )}
                                                    {item.due_at && (
                                                        <>
                                                            Due{' '}
                                                            {new Date(
                                                                item.due_at,
                                                            ).toLocaleString(
                                                                'en-US',
                                                                {
                                                                    timeZone:
                                                                        'Asia/Manila',
                                                                },
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-3 text-sm">
                                                {item.category?.name || '—'}
                                            </td>
                                            <td className="p-3">
                                                <CampaignScopeBadge
                                                    all={
                                                        item.applies_to_all_campaigns
                                                    }
                                                    campaigns={item.campaigns}
                                                />
                                            </td>
                                            <td className="p-3 text-sm">
                                                {item.passing_score}%
                                            </td>
                                            <td className="p-3">
                                                <AssessmentStatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                            <td className="p-3 text-sm">
                                                <b>
                                                    {item.passed_count +
                                                        item.failed_count}{' '}
                                                    / {item.assignments_count}
                                                </b>{' '}
                                                Completed
                                                <div className="text-xs text-[#777b8e]">
                                                    {item.assignments_count
                                                        ? Math.round(
                                                              ((item.passed_count +
                                                                  item.failed_count) /
                                                                  item.assignments_count) *
                                                                  100,
                                                          )
                                                        : 0}
                                                    % · Assigned{' '}
                                                    {item.assigned_count} · In
                                                    Progress{' '}
                                                    {item.in_progress_count} ·
                                                    Pending{' '}
                                                    {item.pending_review_count}{' '}
                                                    · Passed {item.passed_count}{' '}
                                                    · Failed {item.failed_count}
                                                </div>
                                            </td>
                                            <td className="p-3 text-sm">
                                                {item.creator.name}
                                            </td>
                                            <td className="p-3">
                                                <AssessmentActions
                                                    item={item}
                                                    onEdit={showForm}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                    {!assessments.data.length && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="p-12 text-center text-[#888b9b]"
                                            >
                                                <BookOpenCheck className="mx-auto mb-3" />
                                                No assessments found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-5 flex items-center justify-between text-sm text-[#777b8e]">
                            <span>{assessments.total} assessment(s)</span>
                            <div className="flex gap-2">
                                <Button
                                    size="small"
                                    disabled={!assessments.prev_page_url}
                                    onClick={() =>
                                        assessments.prev_page_url &&
                                        router.get(assessments.prev_page_url)
                                    }
                                >
                                    Previous
                                </Button>
                                <span className="p-2">
                                    {assessments.current_page} /{' '}
                                    {assessments.last_page}
                                </span>
                                <Button
                                    size="small"
                                    disabled={!assessments.next_page_url}
                                    onClick={() =>
                                        assessments.next_page_url &&
                                        router.get(assessments.next_page_url)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
            <Dialog
                open={open}
                onClose={() => !processing && setOpen(false)}
                fullWidth
                maxWidth="md"
            >
                <DialogTitle>
                    {editing ? 'Edit Assessment' : 'Create Assessment'}
                </DialogTitle>
                <DialogContent>
                    <div className="mt-2 grid gap-4 sm:grid-cols-2">
                        <TextField
                            label="Title"
                            value={form.title}
                            onChange={(e) => set('title', e.target.value)}
                            error={!!errors.title}
                            helperText={errors.title}
                            className="sm:col-span-2"
                        />
                        <TextField
                            select
                            label="Category"
                            value={form.category_id}
                            onChange={(e) => set('category_id', e.target.value)}
                            error={!!errors.category_id}
                        >
                            <MenuItem value="">Uncategorized</MenuItem>
                            {categories.map((c) => (
                                <MenuItem key={c.id} value={c.id}>
                                    {c.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Difficulty"
                            value={form.difficulty}
                            onChange={(e) => set('difficulty', e.target.value)}
                        >
                            <MenuItem value="beginner">Beginner</MenuItem>
                            <MenuItem value="intermediate">
                                Intermediate
                            </MenuItem>
                            <MenuItem value="advanced">Advanced</MenuItem>
                        </TextField>
                        <TextField
                            multiline
                            minRows={2}
                            label="Description"
                            value={form.description}
                            onChange={(e) => set('description', e.target.value)}
                            className="sm:col-span-2"
                        />
                        <TextField
                            multiline
                            minRows={2}
                            label="Instructions"
                            value={form.instructions}
                            onChange={(e) =>
                                set('instructions', e.target.value)
                            }
                            className="sm:col-span-2"
                        />
                        <TextField
                            type="number"
                            label="Passing score (%)"
                            value={form.passing_score}
                            onChange={(e) =>
                                set('passing_score', e.target.value)
                            }
                            error={!!errors.passing_score}
                        />
                        <TextField
                            type="number"
                            label="Time limit (minutes)"
                            value={form.time_limit_minutes}
                            onChange={(e) =>
                                set('time_limit_minutes', e.target.value)
                            }
                        />
                        <TextField
                            type="number"
                            label="Maximum attempts"
                            value={form.maximum_attempts}
                            onChange={(e) =>
                                set('maximum_attempts', e.target.value)
                            }
                            error={!!errors.maximum_attempts}
                        />
                        <DateTimeField
                            type="datetime-local"
                            label="Publish at"
                            value={form.publish_at}
                            onChange={(e) => set('publish_at', e.target.value)}
                            helperText="Leave blank to publish manually. Times use Asia/Manila."
                            slotProps={{ inputLabel: { shrink: true } }}
                        />
                        <DateTimeField
                            type="datetime-local"
                            label="Available at"
                            value={form.available_at}
                            onChange={(e) =>
                                set('available_at', e.target.value)
                            }
                            slotProps={{ inputLabel: { shrink: true } }}
                        />
                        <DateTimeField
                            type="datetime-local"
                            label="Due at"
                            value={form.due_at}
                            onChange={(e) => set('due_at', e.target.value)}
                            error={!!errors.due_at}
                            helperText={errors.due_at}
                            slotProps={{ inputLabel: { shrink: true } }}
                        />
                        <div className="sm:col-span-2">
                            <CampaignScopeField
                                campaigns={campaigns}
                                all={Boolean(form.applies_to_all_campaigns)}
                                selected={(form.campaign_ids as number[]) || []}
                                onChange={(all, ids) =>
                                    setForm((current) => ({
                                        ...current,
                                        applies_to_all_campaigns: all,
                                        campaign_ids: ids,
                                    }))
                                }
                            />
                        </div>
                        <div className="sm:col-span-2">
                            {[
                                'allow_retake',
                                'randomize_questions',
                                'randomize_answers',
                                'show_correct_answers',
                            ].map((key) => (
                                <FormControlLabel
                                    key={key}
                                    control={
                                        <Checkbox
                                            checked={Boolean(form[key])}
                                            onChange={(e) =>
                                                set(key, e.target.checked)
                                            }
                                        />
                                    }
                                    label={key.replaceAll('_', ' ')}
                                />
                            ))}
                        </div>
                    </div>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={processing || !String(form.title).trim()}
                        onClick={save}
                    >
                        {processing ? 'Saving…' : 'Save Draft'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

AssessmentManagement.layout = {
    breadcrumbs: [
        { title: 'Back', href: '#back' },
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Assessments', href: '/management/assessments' },
    ],
};
