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
import { Edit3, Plus, Trash2, UsersRound, X } from 'lucide-react';
import { useState } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';

type Campaign = { id: number; name: string; abbreviation: string };
type Team = {
    id: number;
    name: string;
    campaignId: number;
    campaign: string;
    campaignAbbreviation: string;
    teamLeader: string | null;
    teamLeaderEmployeeId: string | null;
};

type TeamsProps = {
    teams: Team[];
    campaigns: Campaign[];
    filters: { campaign: number | null };
};

export default function Teams({ teams, campaigns, filters }: TeamsProps) {
    const confirmAction = useConfirmation();
    const [editing, setEditing] = useState<Team | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [name, setName] = useState('');
    const [campaignId, setCampaignId] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setName('');
        setCampaignId('');
        setErrors({});
        setModalOpen(true);
    };

    const openEdit = (team: Team) => {
        setEditing(team);
        setName(team.name);
        setCampaignId(String(team.campaignId));
        setErrors({});
        setModalOpen(true);
    };

    const save = () => {
        setProcessing(true);
        setErrors({});
        const data = { name: name.trim(), campaign_id: Number(campaignId) };
        const options = {
            preserveScroll: true,
            onError: (validationErrors: Record<string, string>) =>
                setErrors(validationErrors),
            onSuccess: () => setModalOpen(false),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.put(`/teams/${editing.id}`, data, options);
        } else {
            router.post('/teams', data, options);
        }
    };

    const remove = async (team: Team) => {
        if (!(await confirmAction(`Delete the ${team.name} team?`))) {
            return;
        }

        router.delete(`/teams/${team.id}`, { preserveScroll: true });
    };

    const filterByCampaign = (value: string) => {
        router.get(
            '/teams',
            value ? { campaign: Number(value) } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Teams" />
            <main className="min-h-full bg-[#f7f7fa] p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1400px] space-y-6">
                    <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-[#b72822] uppercase">
                                Management
                            </p>
                            <h1 className="text-3xl font-bold tracking-[-0.03em] text-[#1b1d2a] sm:text-4xl">
                                Team Management
                            </h1>
                            <p className="mt-2 text-sm text-[#777b8e] sm:text-base">
                                Add, edit, or remove campaign teams.
                            </p>
                        </div>
                        <Button
                            variant="contained"
                            startIcon={<Plus size={18} />}
                            onClick={openCreate}
                            disabled={campaigns.length === 0}
                        >
                            Add Team
                        </Button>
                    </header>

                    {campaigns.length === 0 && (
                        <Alert severity="info">
                            Create a campaign first before adding a team.
                        </Alert>
                    )}

                    <section className="overflow-hidden rounded-3xl border border-t-4 border-[#e6e7ec] border-t-[#ad2924] bg-white shadow-[0_16px_50px_rgba(25,27,38,0.06)]">
                        <div className="flex flex-col gap-4 border-b border-[#ededf1] p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div className="flex items-center gap-3">
                                <div className="grid size-11 place-items-center rounded-xl bg-[#fff0ee] text-[#bd2923]">
                                    <UsersRound size={22} />
                                </div>
                                <div>
                                    <h2 className="font-bold text-[#202230]">
                                        Team Directory
                                    </h2>
                                    <p className="text-sm text-[#888b9b]">
                                        {teams.length} teams match the current
                                        campaign filter
                                    </p>
                                </div>
                            </div>
                            <TextField
                                select
                                label="Campaign"
                                value={filters.campaign?.toString() ?? ''}
                                onChange={(event) =>
                                    filterByCampaign(event.target.value)
                                }
                                size="small"
                                sx={{ minWidth: { xs: '100%', sm: 260 } }}
                            >
                                <MenuItem value="">All Campaigns</MenuItem>
                                {campaigns.map((campaign) => (
                                    <MenuItem
                                        key={campaign.id}
                                        value={String(campaign.id)}
                                    >
                                        {campaign.name} ({campaign.abbreviation})
                                    </MenuItem>
                                ))}
                            </TextField>
                        </div>

                        <div className="overflow-x-auto p-5 sm:p-6">
                            <table className="w-full min-w-[850px] overflow-hidden rounded-2xl border border-[#e5e7ed] text-left">
                                <thead>
                                    <tr className="bg-[#fafafd] text-[11px] font-bold tracking-[0.07em] text-[#555869] uppercase">
                                        <th className="px-5 py-4">ID</th>
                                        <th className="px-5 py-4">Team Name</th>
                                        <th className="px-5 py-4">Campaign</th>
                                        <th className="px-5 py-4">
                                            Team Leader
                                        </th>
                                        <th className="px-5 py-4 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#e9eaf0]">
                                    {teams.map((team) => (
                                        <tr
                                            key={team.id}
                                            className="transition hover:bg-[#fcfaf9]"
                                        >
                                            <td className="px-5 py-4 font-mono text-sm text-[#7a7e90]">
                                                {team.id}
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-[#202230]">
                                                {team.name}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="rounded-lg bg-[#fff1ef] px-2.5 py-1.5 text-xs font-bold text-[#b62b25]">
                                                    {team.campaign}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-sm text-[#4f5262]">
                                                {team.teamLeader ? (
                                                    <>
                                                        <span className="font-semibold">
                                                            {team.teamLeader}
                                                        </span>
                                                        <span className="ml-2 font-mono text-xs text-[#999cab]">
                                                            {
                                                                team.teamLeaderEmployeeId
                                                            }
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-[#999cab]">
                                                        Not assigned
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-2">
                                                    <Tooltip title="Edit team">
                                                        <IconButton
                                                            onClick={() =>
                                                                openEdit(team)
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
                                                    <Tooltip title="Delete team">
                                                        <IconButton
                                                            onClick={() =>
                                                                remove(team)
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
                                    {teams.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-6 py-14 text-center text-sm text-[#888b9b]"
                                            >
                                                {filters.campaign
                                                    ? 'No teams match this campaign.'
                                                    : 'No teams have been created yet.'}
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
                        {editing ? 'Edit Team' : 'Add Team'}
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
                        {(errors.name || errors.campaign_id) && (
                            <Alert severity="error">
                                Please correct the highlighted team fields.
                            </Alert>
                        )}
                        <TextField
                            label="Team Name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            error={Boolean(errors.name)}
                            helperText={errors.name}
                            autoFocus
                        />
                        <TextField
                            select
                            label="Campaign"
                            value={campaignId}
                            onChange={(event) =>
                                setCampaignId(event.target.value)
                            }
                            error={Boolean(errors.campaign_id)}
                            helperText={
                                errors.campaign_id ??
                                'Every team must belong to one campaign.'
                            }
                        >
                            {campaigns.map((campaign) => (
                                <MenuItem
                                    key={campaign.id}
                                    value={String(campaign.id)}
                                >
                                    {campaign.name} ({campaign.abbreviation})
                                </MenuItem>
                            ))}
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
                        disabled={processing || !name.trim() || !campaignId}
                    >
                        {processing ? 'Saving...' : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

Teams.layout = { breadcrumbs: [{ title: 'Teams', href: '/teams' }] };
