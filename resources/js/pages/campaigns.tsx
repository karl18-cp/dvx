import { Head, router } from '@inertiajs/react';
import {
    Alert,
    Box,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
    Tooltip,
} from '@mui/material';
import { Edit3, Megaphone, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

type Campaign = {
    id: number;
    name: string;
    abbreviation: string;
    description?: string;
    is_active: boolean;
    teams_count: number;
    assessments_count: number;
    training_materials_count: number;
};

type CampaignsProps = {
    campaigns: Campaign[];
};

export default function Campaigns({ campaigns }: CampaignsProps) {
    const [editing, setEditing] = useState<Campaign | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [name, setName] = useState('');
    const [abbreviation, setAbbreviation] = useState('');
    const [description, setDescription] = useState('');
    const [active, setActive] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setName('');
        setAbbreviation('');
        setDescription('');
        setActive(true);
        setErrors({});
        setModalOpen(true);
    };

    const openEdit = (campaign: Campaign) => {
        setEditing(campaign);
        setName(campaign.name);
        setAbbreviation(campaign.abbreviation);
        setDescription(campaign.description || '');
        setActive(campaign.is_active);
        setErrors({});
        setModalOpen(true);
    };

    const save = () => {
        setProcessing(true);
        setErrors({});
        const options = {
            preserveScroll: true,
            onError: (validationErrors: Record<string, string>) =>
                setErrors(validationErrors),
            onSuccess: () => setModalOpen(false),
            onFinish: () => setProcessing(false),
        };
        const data = {
            name: name.trim(),
            abbreviation: abbreviation.trim().toUpperCase(),
            description: description.trim() || null,
            is_active: active,
        };

        if (editing) {
            router.put(`/campaigns/${editing.id}`, data, options);
        } else {
            router.post('/campaigns', data, options);
        }
    };

    const remove = (campaign: Campaign) => {
        if (!window.confirm(`Delete the ${campaign.name} campaign?`)) {
            return;
        }

        router.delete(`/campaigns/${campaign.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Campaigns" />
            <main className="min-h-full bg-[#f7f7fa] p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1400px] space-y-6">
                    <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-[#b72822] uppercase">
                                Management
                            </p>
                            <h1 className="text-3xl font-bold tracking-[-0.03em] text-[#1b1d2a] sm:text-4xl">
                                Campaign Management
                            </h1>
                            <p className="mt-2 text-sm text-[#777b8e] sm:text-base">
                                Manage Campaigns, status, Teams, and content.
                            </p>
                        </div>
                        <Button
                            variant="contained"
                            startIcon={<Plus size={18} />}
                            onClick={openCreate}
                        >
                            Add Campaign
                        </Button>
                    </header>

                    <section className="overflow-hidden rounded-3xl border border-t-4 border-[#e6e7ec] border-t-[#ad2924] bg-white shadow-[0_16px_50px_rgba(25,27,38,0.06)]">
                        <div className="flex items-center gap-3 border-b border-[#ededf1] p-5 sm:p-6">
                            <div className="grid size-11 place-items-center rounded-xl bg-[#fff0ee] text-[#bd2923]">
                                <Megaphone size={22} />
                            </div>
                            <div>
                                <h2 className="font-bold text-[#202230]">
                                    Campaign Directory
                                </h2>
                                <p className="text-sm text-[#888b9b]">
                                    {campaigns.length} campaigns fetched from
                                    the database
                                </p>
                            </div>
                        </div>

                        <div className="overflow-x-auto p-5 sm:p-6">
                            <table className="w-full min-w-[650px] overflow-hidden rounded-2xl border border-[#e5e7ed] text-left">
                                <thead>
                                    <tr className="bg-[#fafafd] text-[11px] font-bold tracking-[0.07em] text-[#555869] uppercase">
                                        <th className="px-5 py-4">ID</th>
                                        <th className="px-5 py-4">
                                            Campaign Name
                                        </th>
                                        <th className="px-5 py-4">
                                            Abbreviation
                                        </th>
                                        <th className="px-5 py-4">Status</th>
                                        <th className="px-5 py-4">Teams</th>
                                        <th className="px-5 py-4">Content</th>
                                        <th className="px-5 py-4 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#e9eaf0]">
                                    {campaigns.map((campaign) => (
                                        <tr
                                            key={campaign.id}
                                            className="transition hover:bg-[#fcfaf9]"
                                        >
                                            <td className="px-5 py-4 font-mono text-sm text-[#7a7e90]">
                                                {campaign.id}
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-[#202230]">
                                                {campaign.name}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="rounded-lg bg-[#fff1ef] px-2.5 py-1.5 text-xs font-bold text-[#b62b25]">
                                                    {campaign.abbreviation}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                {campaign.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <a
                                                    className="font-semibold text-red-700"
                                                    href={`/teams?campaign=${campaign.id}`}
                                                >
                                                    {campaign.teams_count} teams
                                                </a>
                                            </td>
                                            <td className="px-5 py-4">
                                                {campaign.assessments_count +
                                                    campaign.training_materials_count}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-2">
                                                    <Tooltip title="Edit campaign">
                                                        <IconButton
                                                            onClick={() =>
                                                                openEdit(
                                                                    campaign,
                                                                )
                                                            }
                                                            sx={{
                                                                width: 36,
                                                                height: 36,
                                                                color: '#1769d2',
                                                                border: '1px solid #a8ccff',
                                                            }}
                                                        >
                                                            <Edit3 size={17} />
                                                        </IconButton>
                                                    </Tooltip>
                                                    <Tooltip title="Delete campaign">
                                                        <IconButton
                                                            onClick={() =>
                                                                remove(campaign)
                                                            }
                                                            sx={{
                                                                width: 36,
                                                                height: 36,
                                                                color: '#d92d20',
                                                                border: '1px solid #ffc0bb',
                                                            }}
                                                        >
                                                            <Trash2 size={17} />
                                                        </IconButton>
                                                    </Tooltip>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {campaigns.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-6 py-14 text-center text-sm text-[#888b9b]"
                                            >
                                                No campaigns have been created
                                                yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>

            <Dialog
                open={modalOpen}
                onClose={() => !processing && setModalOpen(false)}
                fullWidth
                maxWidth="xs"
                slotProps={{
                    paper: { sx: { overflow: 'hidden', borderRadius: 3 } },
                }}
            >
                <Box
                    sx={{
                        position: 'relative',
                        px: 3.5,
                        py: 2.5,
                        pr: 9,
                        color: 'white',
                        background: 'linear-gradient(120deg,#481b24,#ae2c25)',
                    }}
                >
                    <DialogTitle sx={{ p: 0, fontWeight: 800 }}>
                        {editing ? 'Edit Campaign' : 'Add Campaign'}
                    </DialogTitle>
                    <IconButton
                        aria-label="Close"
                        onClick={() => setModalOpen(false)}
                        sx={{
                            position: 'absolute',
                            right: 20,
                            top: 15,
                            color: 'white',
                            bgcolor: 'rgba(255,255,255,.14)',
                        }}
                    >
                        <X size={20} />
                    </IconButton>
                </Box>
                <DialogContent sx={{ pt: '28px !important' }}>
                    <Box sx={{ display: 'grid', gap: 2.5 }}>
                        {(errors.name || errors.abbreviation) && (
                            <Alert severity="error">
                                Please correct the highlighted campaign fields.
                            </Alert>
                        )}
                        <TextField
                            label="Campaign Name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            error={Boolean(errors.name)}
                            helperText={errors.name}
                            autoFocus
                        />
                        <TextField
                            label="Abbreviation"
                            value={abbreviation}
                            onChange={(event) =>
                                setAbbreviation(event.target.value)
                            }
                            error={Boolean(errors.abbreviation)}
                            helperText={
                                errors.abbreviation ?? 'Maximum 30 characters'
                            }
                            slotProps={{ htmlInput: { maxLength: 30 } }}
                        />
                        <TextField
                            multiline
                            minRows={2}
                            label="Description"
                            value={description}
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                        />
                        <TextField
                            select
                            label="Status"
                            value={active ? '1' : '0'}
                            onChange={(event) =>
                                setActive(event.target.value === '1')
                            }
                        >
                            <MenuItem value="1">Active</MenuItem>
                            <MenuItem value="0">Inactive</MenuItem>
                        </TextField>
                    </Box>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2.5 }}>
                    <Button
                        color="inherit"
                        onClick={() => setModalOpen(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        onClick={save}
                        disabled={
                            processing || !name.trim() || !abbreviation.trim()
                        }
                    >
                        {processing ? 'Saving...' : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

Campaigns.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: '/campaigns' }],
};
