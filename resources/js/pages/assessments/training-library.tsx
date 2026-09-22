import { Head, Link, router, useForm } from '@inertiajs/react';
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
import { ListChecks } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    CampaignScopeBadge,
    CampaignScopeField,
} from '@/components/campaign-scope-field';
import type { CampaignOption } from '@/components/campaign-scope-field';
type Ref = { id: number; name: string };
type Item = {
    id: number;
    title: string;
    type: string;
    status: string;
    attachments_count: number;
    duration_seconds?: number;
    description?: string;
    content?: string;
    original_filename?: string;
    skill?: Ref | null;
    applies_to_all_campaigns: boolean;
    campaigns?: CampaignOption[];
};
export default function TrainingLibrary({
    materials,
    categories,
    skills,
    campaigns,
    filters,
    target_assessment_id,
}: {
    materials: { data: Item[]; total: number };
    categories: Ref[];
    skills: Ref[];
    campaigns: CampaignOption[];
    filters: Record<string, string>;
    target_assessment_id?: number;
}) {
    const [open, setOpen] = useState(false),
        [search, setSearch] = useState(filters.search || ''),
        [preview, setPreview] = useState<Item | null>(null),
        [selected, setSelected] = useState<number[]>([]),
        [required, setRequired] = useState(true),
        [threshold, setThreshold] = useState('90');
    const form = useForm({
        title: '',
        description: '',
        type: 'written',
        category_id: '',
        skill_id: '',
        content: '',
        duration_seconds: '',
        file: null as File | null,
        applies_to_all_campaigns: true,
        campaign_ids: [] as number[],
    });
    useEffect(() => {
        if (search === (filters.search || '')) {
            return;
        }

        const t = setTimeout(
            () =>
                router.get(
                    '/management/training-library',
                    { ...filters, search, assessment: target_assessment_id },
                    { preserveState: true, replace: true },
                ),
            400,
        );

        return () => clearTimeout(t);
    }, [search, filters, target_assessment_id]);
    const filter = (k: string, v: string) =>
        router.get(
            '/management/training-library',
            { ...filters, [k]: v, assessment: target_assessment_id },
            { preserveState: true, replace: true },
        );

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="Training Library" />
            <div className="mx-auto max-w-7xl">
                <header className="mb-5 flex justify-between">
                    <div>
                        <h1>Training Library</h1>
                        <p>Reusable lessons and media for assessments.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/management/question-bank">
                            <Button
                                size="small"
                                variant="outlined"
                                startIcon={<ListChecks size={16} />}
                            >
                                Question Bank
                            </Button>
                        </Link>
                        <Button
                            variant="contained"
                            onClick={() => setOpen(true)}
                        >
                            Create Material
                        </Button>
                    </div>
                </header>
                {target_assessment_id && (
                    <div className="mb-4 flex items-center gap-3 rounded-xl border bg-white p-3">
                        <Checkbox
                            checked={required}
                            onChange={(e) => setRequired(e.target.checked)}
                        />
                        <span>Required Before Quiz</span>
                        <TextField
                            size="small"
                            type="number"
                            label="Completion %"
                            value={threshold}
                            onChange={(e) => setThreshold(e.target.value)}
                        />
                        <Button
                            variant="contained"
                            disabled={!selected.length}
                            onClick={() =>
                                router.post(
                                    `/management/assessments/${target_assessment_id}/training-library`,
                                    {
                                        material_ids: selected,
                                        is_required: required,
                                        required_completion_percentage:
                                            Number(threshold),
                                    },
                                )
                            }
                        >
                            Add Selected Training ({selected.length})
                        </Button>
                    </div>
                )}
                <div className="mb-4 grid gap-2 md:grid-cols-6">
                    <TextField
                        size="small"
                        label="Search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Select
                        label="Campaign"
                        value={filters.campaign}
                        refs={campaigns}
                        onChange={(v) => filter('campaign', v)}
                    />
                    <Select
                        label="Category"
                        value={filters.category}
                        refs={categories}
                        onChange={(v) => filter('category', v)}
                    />
                    <Select
                        label="Skill"
                        value={filters.skill}
                        refs={skills}
                        onChange={(v) => filter('skill', v)}
                    />
                    <Select
                        label="Type"
                        value={filters.type}
                        values={[
                            'video',
                            'audio',
                            'image',
                            'document',
                            'written',
                        ]}
                        onChange={(v) => filter('type', v)}
                    />
                    <Select
                        label="Status"
                        value={filters.status}
                        values={['active', 'archived']}
                        onChange={(v) => filter('status', v)}
                    />
                </div>
                <div className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="w-full min-w-[1050px] table-fixed text-left">
                        <thead>
                            <tr>
                                {target_assessment_id && (
                                    <th className="w-20 px-4 py-3">Select</th>
                                )}
                                <th className="w-[31%] px-4 py-3">Title</th>
                                <th className="w-20 px-4 py-3">Type</th>
                                <th className="w-36 px-4 py-3">Skill</th>
                                <th className="w-32 px-4 py-3">Campaign</th>
                                <th className="w-24 px-4 py-3">Duration</th>
                                <th className="w-32 px-4 py-3">Used In</th>
                                <th className="w-24 px-4 py-3">Status</th>
                                <th className="w-64 px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {materials.data.map((m) => (
                                <tr className="border-t" key={m.id}>
                                    {target_assessment_id && (
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                disabled={m.status !== 'active'}
                                                checked={selected.includes(
                                                    m.id,
                                                )}
                                                onChange={() =>
                                                    setSelected((current) =>
                                                        current.includes(m.id)
                                                            ? current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      m.id,
                                                              )
                                                            : [
                                                                  ...current,
                                                                  m.id,
                                                              ],
                                                    )
                                                }
                                            />
                                        </td>
                                    )}
                                    <td className="px-4 py-3 font-medium">
                                        {m.title}
                                    </td>
                                    <td className="px-4 py-3 capitalize">
                                        {m.type}
                                    </td>
                                    <td className="px-4 py-3">
                                        {m.skill?.name || 'General'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <CampaignScopeBadge
                                            all={m.applies_to_all_campaigns}
                                            campaigns={m.campaigns}
                                        />
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {m.duration_seconds
                                            ? `${Math.ceil(m.duration_seconds / 60)} min`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {m.attachments_count} assessments
                                    </td>
                                    <td className="px-4 py-3 capitalize">
                                        {m.status}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1">
                                            <Button
                                                size="small"
                                                onClick={() => setPreview(m)}
                                            >
                                                Preview
                                            </Button>
                                            <Button
                                                size="small"
                                                onClick={() =>
                                                    router.post(
                                                        `/management/training-library/${m.id}/duplicate`,
                                                    )
                                                }
                                            >
                                                Duplicate
                                            </Button>
                                            {m.status === 'active' && (
                                                <Button
                                                    size="small"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/management/training-library/${m.id}/archive`,
                                                        )
                                                    }
                                                >
                                                    Archive
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <Dialog
                open={Boolean(preview)}
                onClose={() => setPreview(null)}
                fullWidth
                maxWidth="md"
            >
                <DialogTitle>Lesson Preview</DialogTitle>
                <DialogContent dividers>
                    {preview && (
                        <article>
                            <div className="mb-4 flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700 capitalize">
                                    {preview.type}
                                </span>
                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {preview.skill?.name || 'General Skill'}
                                </span>
                            </div>
                            <h2 className="text-xl font-bold text-slate-900">
                                {preview.title}
                            </h2>
                            {preview.description && (
                                <p className="mt-2 text-sm whitespace-pre-wrap text-slate-600">
                                    {preview.description}
                                </p>
                            )}
                            {preview.type === 'written' ? (
                                <div className="mt-5 max-h-[55vh] overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-5 whitespace-pre-wrap text-slate-800">
                                    {preview.content}
                                </div>
                            ) : preview.type === 'video' ? (
                                <video
                                    className="mt-5 max-h-[55vh] w-full rounded-xl bg-black"
                                    controls
                                    preload="metadata"
                                    src={`/management/training-library/${preview.id}/preview`}
                                />
                            ) : preview.type === 'audio' ? (
                                <audio
                                    className="mt-5 w-full"
                                    controls
                                    preload="metadata"
                                    src={`/management/training-library/${preview.id}/preview`}
                                />
                            ) : preview.type === 'image' ? (
                                <img
                                    className="mt-5 max-h-[55vh] w-full rounded-xl object-contain"
                                    src={`/management/training-library/${preview.id}/preview`}
                                    alt={preview.title}
                                />
                            ) : (
                                <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5 text-center">
                                    <p className="mb-3 text-sm text-slate-600">
                                        {preview.original_filename ||
                                            'Document preview'}
                                    </p>
                                    <Button
                                        component="a"
                                        href={`/management/training-library/${preview.id}/preview`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        variant="outlined"
                                    >
                                        Open Document
                                    </Button>
                                </div>
                            )}
                        </article>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setPreview(null)}>Close</Button>
                </DialogActions>
            </Dialog>
            <Dialog open={open} onClose={() => setOpen(false)} fullWidth>
                <DialogTitle>Create Training Material</DialogTitle>
                <DialogContent>
                    <div className="mt-2 grid gap-3">
                        <TextField
                            label="Title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <Select
                            label="Type"
                            value={form.data.type}
                            values={[
                                'video',
                                'audio',
                                'image',
                                'document',
                                'written',
                            ]}
                            onChange={(v) => form.setData('type', v)}
                        />
                        {form.data.type === 'written' ? (
                            <TextField
                                multiline
                                minRows={5}
                                label="Written lesson"
                                value={form.data.content}
                                onChange={(e) =>
                                    form.setData('content', e.target.value)
                                }
                            />
                        ) : (
                            <div>
                                <span className="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Material file
                                </span>
                                <div className="flex min-h-14 items-center gap-3 rounded-lg border border-slate-300 bg-white p-2 transition focus-within:border-red-600 focus-within:ring-2 focus-within:ring-red-100 hover:border-slate-400">
                                    <Button
                                        component="label"
                                        variant="outlined"
                                        size="small"
                                        sx={{
                                            flexShrink: 0,
                                            borderRadius: '8px',
                                            fontWeight: 700,
                                            textTransform: 'none',
                                        }}
                                    >
                                        Browse file
                                        <input
                                            hidden
                                            type="file"
                                            onChange={(e) =>
                                                form.setData(
                                                    'file',
                                                    e.target.files?.[0] || null,
                                                )
                                            }
                                        />
                                    </Button>
                                    <span
                                        className={`min-w-0 flex-1 truncate text-sm ${form.data.file ? 'font-medium text-slate-800' : 'text-slate-500'}`}
                                        title={form.data.file?.name}
                                    >
                                        {form.data.file?.name ||
                                            'No file selected'}
                                    </span>
                                </div>
                                <p className="mt-1.5 text-xs text-slate-500">
                                    Select a {form.data.type} file to upload.
                                </p>
                            </div>
                        )}
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
                    <Button
                        variant="contained"
                        onClick={() =>
                            form.post('/management/training-library', {
                                forceFormData: true,
                                onSuccess: () => setOpen(false),
                            })
                        }
                    >
                        Save
                    </Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}
function Select({
    label,
    value = '',
    refs = [],
    values = [],
    onChange,
}: {
    label: string;
    value?: string;
    refs?: Ref[];
    values?: string[];
    onChange: (v: string) => void;
}) {
    return (
        <TextField
            select
            size="small"
            label={label}
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <MenuItem value="">All</MenuItem>
            {refs.map((r) => (
                <MenuItem key={r.id} value={r.id}>
                    {r.name}
                </MenuItem>
            ))}
            {values.map((v) => (
                <MenuItem key={v} value={v}>
                    {v.charAt(0).toUpperCase() + v.slice(1)}
                </MenuItem>
            ))}
        </TextField>
    );
}
TrainingLibrary.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Training Library', href: '/management/training-library' },
    ],
};
