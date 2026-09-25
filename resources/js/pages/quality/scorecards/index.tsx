import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Button,
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
import { useConfirmation } from '@/hooks/use-confirmation';

type Scorecard = {
    id: number;
    name: string;
    description: string | null;
    applies_to_all_campaigns: boolean;
    campaigns: CampaignOption[];
    status: string;
    passing_score: string;
    categories_count: number;
    criteria_count: number;
    updated_at: string;
};
type PageLink = { url: string | null; label: string; active: boolean };

export default function ScorecardIndex({
    scorecards,
    campaigns,
    filters,
}: {
    scorecards: { data: Scorecard[]; links: PageLink[]; total: number };
    campaigns: CampaignOption[];
    filters: { search?: string; status?: string; campaign?: number };
}) {
    const confirmAction = useConfirmation();
    const [search, setSearch] = useState(filters.search || '');
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        description: '',
        passing_score: 80,
        applies_to_all_campaigns: true,
        campaign_ids: [] as number[],
    });
    useEffect(() => {
        if (search === (filters.search || '')) {
            return;
        }

        const timer = setTimeout(
            () =>
                router.get(
                    '/management/qa-scorecards',
                    { ...filters, search },
                    { preserveState: true, replace: true },
                ),
            400,
        );

        return () => clearTimeout(timer);
    }, [search, filters]);
    const filter = (key: string, value: string) =>
        router.get(
            '/management/qa-scorecards',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    const submit = () =>
        form.post('/management/qa-scorecards', {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-[13px]">
            <Head title="QA Scorecards" />
            <div className="mx-auto max-w-7xl">
                <header className="mb-5 flex items-center justify-between">
                    <div>
                        <p className="font-bold tracking-[.2em] text-red-700 uppercase">
                            Quality &amp; Coaching
                        </p>
                        <h1 className="text-2xl font-bold">QA Scorecards</h1>
                        <p className="text-slate-600">
                            Configure reusable human evaluation standards.
                        </p>
                    </div>
                    <Button variant="contained" onClick={() => setOpen(true)}>
                        New Scorecard
                    </Button>
                </header>
                <section className="mb-4 grid grid-cols-1 gap-3 rounded-xl border bg-white p-4 md:grid-cols-4">
                    <TextField
                        size="small"
                        label="Search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <TextField
                        select
                        size="small"
                        label="Campaign"
                        value={filters.campaign || ''}
                        onChange={(event) =>
                            filter('campaign', event.target.value)
                        }
                    >
                        <MenuItem value="">All Campaigns</MenuItem>
                        {campaigns.map((campaign) => (
                            <MenuItem key={campaign.id} value={campaign.id}>
                                {campaign.name}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        select
                        size="small"
                        label="Status"
                        value={filters.status || ''}
                        onChange={(event) =>
                            filter('status', event.target.value)
                        }
                    >
                        <MenuItem value="">All Statuses</MenuItem>
                        <MenuItem value="draft">Draft</MenuItem>
                        <MenuItem value="active">Active</MenuItem>
                        <MenuItem value="archived">Archived</MenuItem>
                    </TextField>
                    <Button
                        variant="outlined"
                        onClick={() => router.get('/management/qa-scorecards')}
                    >
                        Clear Filters
                    </Button>
                </section>
                <section className="overflow-hidden rounded-xl border bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 text-left text-xs text-slate-600 uppercase">
                                <tr>
                                    {[
                                        'Scorecard',
                                        'Campaign Scope',
                                        'Status',
                                        'Passing',
                                        'Sections',
                                        'Check Items',
                                        'Updated',
                                        'Actions',
                                    ].map((heading) => (
                                        <th key={heading} className="px-4 py-3">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {scorecards.data.map((scorecard) => (
                                    <tr key={scorecard.id} className="border-t">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/management/qa-scorecards/${scorecard.id}/builder`}
                                                className="inline-flex items-center gap-1 text-sm font-bold text-red-700 hover:underline"
                                                title="Open Scorecard Builder"
                                            >
                                                {scorecard.name}
                                                <span aria-hidden="true">
                                                    →
                                                </span>
                                            </Link>
                                            <span className="line-clamp-1 text-xs text-slate-500">
                                                {scorecard.description}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <CampaignScopeBadge
                                                all={
                                                    scorecard.applies_to_all_campaigns
                                                }
                                                campaigns={scorecard.campaigns}
                                            />
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {scorecard.status}
                                        </td>
                                        <td className="px-4 py-3">
                                            {Number(scorecard.passing_score)}%
                                        </td>
                                        <td className="px-4 py-3">
                                            {scorecard.categories_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            {scorecard.criteria_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            {new Date(
                                                scorecard.updated_at,
                                            ).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Link
                                                    className="font-semibold text-red-700"
                                                    href={`/management/qa-scorecards/${scorecard.id}/builder`}
                                                >
                                                    Edit
                                                </Link>
                                                <Link
                                                    className="font-semibold text-red-700"
                                                    href={`/management/qa-scorecards/${scorecard.id}/preview`}
                                                >
                                                    Preview
                                                </Link>
                                                <button
                                                    className="font-semibold text-red-700"
                                                    onClick={() =>
                                                        router.post(
                                                            `/management/qa-scorecards/${scorecard.id}/duplicate`,
                                                        )
                                                    }
                                                >
                                                    Duplicate
                                                </button>
                                                {scorecard.status ===
                                                    'draft' && (
                                                    <button
                                                        className="font-semibold text-green-700"
                                                        onClick={() =>
                                                            router.post(
                                                                `/management/qa-scorecards/${scorecard.id}/activate`,
                                                            )
                                                        }
                                                    >
                                                        Activate
                                                    </button>
                                                )}
                                                {scorecard.status !==
                                                    'archived' && (
                                                    <button
                                                        className="font-semibold text-slate-700"
                                                        onClick={async () =>
                                                            (await confirmAction(
                                                                'Archive this Scorecard?',
                                                            )) &&
                                                            router.post(
                                                                `/management/qa-scorecards/${scorecard.id}/archive`,
                                                            )
                                                        }
                                                    >
                                                        Archive
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!scorecards.data.length && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="p-8 text-center text-slate-500"
                                        >
                                            No Scorecards match the selected
                                            filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
                <nav className="mt-4 flex flex-wrap gap-1">
                    {scorecards.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            className={`rounded border px-3 py-1.5 ${link.active ? 'bg-red-700 text-white' : 'bg-white'}`}
                            onClick={() =>
                                link.url &&
                                router.get(
                                    link.url,
                                    {},
                                    { preserveState: true },
                                )
                            }
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            </div>
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle>New QA Scorecard</DialogTitle>
                <DialogContent sx={{ pt: 1.5, pb: 2 }}>
                    <div className="grid gap-4">
                        <TextField
                            fullWidth
                            size="small"
                            label="Name"
                            value={form.data.name}
                            error={!!form.errors.name}
                            helperText={form.errors.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <TextField
                            fullWidth
                            multiline
                            minRows={2}
                            size="small"
                            label="Description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <TextField
                            fullWidth
                            size="small"
                            type="number"
                            label="Passing Score (%)"
                            value={form.data.passing_score}
                            error={!!form.errors.passing_score}
                            helperText={form.errors.passing_score}
                            onChange={(event) =>
                                form.setData(
                                    'passing_score',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <CampaignScopeField
                            campaigns={campaigns}
                            all={form.data.applies_to_all_campaigns}
                            selected={form.data.campaign_ids}
                            onChange={(all, ids) => {
                                form.setData('applies_to_all_campaigns', all);
                                form.setData('campaign_ids', ids);
                            }}
                        />
                        {form.errors.campaign_ids && (
                            <p className="text-red-700">
                                {form.errors.campaign_ids}
                            </p>
                        )}
                    </div>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2.5, pt: 1.5, gap: 1 }}>
                    <Button onClick={() => setOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        Create Draft
                    </Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}

ScorecardIndex.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'QA Scorecards', href: '/management/qa-scorecards' },
    ],
};
