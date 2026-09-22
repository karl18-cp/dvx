import { Head, router } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    TextField,
} from '@mui/material';
import {
    ArrowRightLeft,
    CheckCheck,
    Save,
    Search,
    UsersRound,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type Team = {
    id: number;
    name: string;
    campaign: string;
    campaignAbbreviation: string;
    leaderId: number | null;
    memberIds: number[];
};

type TeamLeader = {
    id: number;
    employeeId: string;
    name: string;
    email: string;
    teams: string[];
};

type Agent = {
    id: number;
    employeeId: string;
    name: string;
    email: string;
    currentTeamId: number | null;
    currentTeam: string | null;
    currentCampaign: string | null;
};

type Props = { teams: Team[]; teamLeaders: TeamLeader[]; agents: Agent[] };

export default function TeamAssigning({ teams, teamLeaders, agents }: Props) {
    const [teamId, setTeamId] = useState('');
    const [leaderId, setLeaderId] = useState('');
    const [selectedAgentIds, setSelectedAgentIds] = useState<number[]>([]);
    const [search, setSearch] = useState('');
    const [processing, setProcessing] = useState(false);
    const [transferringId, setTransferringId] = useState<number | null>(null);
    const [transferCandidate, setTransferCandidate] = useState<Agent | null>(
        null,
    );
    const [transferTeamId, setTransferTeamId] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const selectedTeam = teams.find((team) => team.id === Number(teamId));

    const changeTeam = (value: string) => {
        const team = teams.find((candidate) => candidate.id === Number(value));
        setTeamId(value);
        setLeaderId(team?.leaderId ? String(team.leaderId) : '');
        setSelectedAgentIds(team?.memberIds ?? []);
        setErrors({});
    };

    const visibleAgents = useMemo(() => {
        const query = search.trim().toLowerCase();

        return agents.filter(
            (agent) =>
                agent.currentTeamId === selectedTeam?.id &&
                (!query ||
                    agent.name.toLowerCase().includes(query) ||
                    agent.employeeId.toLowerCase().includes(query) ||
                    agent.email.toLowerCase().includes(query)),
        );
    }, [agents, search, selectedTeam?.id]);

    const canSelect = () => Boolean(selectedTeam);

    const toggleAgent = (agent: Agent) => {
        if (!canSelect()) {
            return;
        }

        setSelectedAgentIds((current) =>
            current.includes(agent.id)
                ? current.filter((id) => id !== agent.id)
                : [...current, agent.id],
        );
    };

    const selectVisible = () => {
        const availableIds = selectedTeam
            ? visibleAgents.map((agent) => agent.id)
            : [];
        setSelectedAgentIds((current) => [
            ...new Set([...current, ...availableIds]),
        ]);
    };

    const save = () => {
        if (!teamId || !leaderId) {
            return;
        }

        const transfers = agents.filter(
            (agent) =>
                selectedAgentIds.includes(agent.id) &&
                agent.currentTeamId &&
                agent.currentTeamId !== selectedTeam?.id,
        );

        if (
            transfers.length > 0 &&
            !window.confirm(
                `Transfer ${transfers.length} selected agent(s) to ${selectedTeam?.name}? Their current team membership will be replaced. Historical assessment and coaching records will not be changed.`,
            )
        ) {
            return;
        }

        setProcessing(true);
        setErrors({});
        router.post(
            '/team-assigning',
            {
                team_id: Number(teamId),
                team_leader_id: Number(leaderId),
                agent_ids: selectedAgentIds,
            },
            {
                preserveScroll: true,
                onError: (validationErrors) => setErrors(validationErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const openTransfer = (agent: Agent) => {
        setTransferCandidate(agent);
        setTransferTeamId('');
    };

    const closeTransfer = () => {
        if (transferringId !== null) {
            return;
        }

        setTransferCandidate(null);
        setTransferTeamId('');
    };

    const submitTransfer = () => {
        if (!transferCandidate || !transferTeamId) {
            return;
        }

        setTransferringId(transferCandidate.id);
        router.post(
            '/team-assigning/transfer',
            {
                agent_id: transferCandidate.id,
                team_id: Number(transferTeamId),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setTransferringId(null);
                    setTransferCandidate(null);
                    setTransferTeamId('');
                },
            },
        );
    };

    return (
        <>
            <Head title="Team Assigning" />
            <main className="min-h-full bg-[#f7f7fa] p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-6">
                    <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-[#b72822] uppercase">
                                Management
                            </p>
                            <h1 className="text-3xl font-bold tracking-[-0.03em] text-[#1b1d2a] sm:text-4xl">
                                Team Assigning
                            </h1>
                            <p className="mt-2 text-sm text-[#777b8e] sm:text-base">
                                Assign one leader and selected agents to a team.
                            </p>
                        </div>
                        <Button
                            variant="contained"
                            startIcon={<Save size={17} />}
                            onClick={save}
                            disabled={processing || !teamId || !leaderId}
                        >
                            {processing ? 'Saving...' : 'Save Assignment'}
                        </Button>
                    </header>

                    {errors.agent_ids && (
                        <Alert severity="error">{errors.agent_ids}</Alert>
                    )}
                    {(errors.team_id || errors.team_leader_id) && (
                        <Alert severity="error">
                            Please select a valid team and team leader.
                        </Alert>
                    )}

                    <section className="rounded-3xl border border-t-4 border-[#e6e7ec] border-t-[#ad2924] bg-white p-5 shadow-[0_16px_50px_rgba(25,27,38,0.06)] sm:p-7">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="grid size-11 place-items-center rounded-xl bg-[#fff0ee] text-[#bd2923]">
                                <UsersRound size={22} />
                            </div>
                            <div>
                                <h2 className="font-bold text-[#202230]">
                                    Team Assignment
                                </h2>
                                <p className="text-sm text-[#888b9b]">
                                    Team leaders can manage multiple teams;
                                    agents belong to only one.
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 lg:grid-cols-2">
                            <article className="rounded-2xl border border-[#f0cbc7] bg-[#fffafa] p-5 sm:p-6">
                                <h3 className="text-lg font-bold text-[#202230]">
                                    Team
                                </h3>
                                <p className="mt-1 mb-5 text-sm text-[#777b8e]">
                                    Choose the team you want to manage.
                                </p>
                                <TextField
                                    select
                                    fullWidth
                                    label="Select Team"
                                    value={teamId}
                                    onChange={(event) =>
                                        changeTeam(event.target.value)
                                    }
                                >
                                    {teams.map((team) => (
                                        <MenuItem
                                            key={team.id}
                                            value={String(team.id)}
                                        >
                                            {team.name} · {team.campaign}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                {selectedTeam && (
                                    <p className="mt-3 text-xs font-semibold text-[#ad2924]">
                                        Campaign: {selectedTeam.campaign} (
                                        {selectedTeam.campaignAbbreviation})
                                    </p>
                                )}
                            </article>

                            <article className="rounded-2xl border border-[#f0cbc7] bg-[#fffafa] p-5 sm:p-6">
                                <h3 className="text-lg font-bold text-[#202230]">
                                    Team Leader
                                </h3>
                                <p className="mt-1 mb-5 text-sm text-[#777b8e]">
                                    One leader per team; a leader may handle
                                    multiple teams.
                                </p>
                                <TextField
                                    select
                                    fullWidth
                                    label="Select Team Leader"
                                    value={leaderId}
                                    onChange={(event) =>
                                        setLeaderId(event.target.value)
                                    }
                                    disabled={!selectedTeam}
                                >
                                    {teamLeaders.map((leader) => (
                                        <MenuItem
                                            key={leader.id}
                                            value={String(leader.id)}
                                        >
                                            <div>
                                                <div className="font-semibold">
                                                    {leader.name} ·{' '}
                                                    {leader.employeeId}
                                                </div>
                                                <div className="text-xs text-[#888b9b]">
                                                    {leader.teams.length
                                                        ? `Currently leads: ${leader.teams.join(', ')}`
                                                        : 'No teams assigned'}
                                                </div>
                                            </div>
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </article>
                        </div>

                        <article className="mt-5 rounded-2xl border border-[#f0cbc7] bg-[#fffafa] p-5 sm:p-6">
                            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                                <div>
                                    <h3 className="text-lg font-bold text-[#202230]">
                                        Agents
                                    </h3>
                                    <p className="mt-1 text-sm text-[#777b8e]">
                                        Only agents currently assigned to the
                                        selected team are shown.
                                    </p>
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <label className="relative block sm:w-64">
                                        <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[#a1a4b2]" />
                                        <input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Search agents..."
                                            className="h-11 w-full rounded-xl border border-[#e1e2e8] bg-white pr-4 pl-11 text-sm outline-none"
                                        />
                                    </label>
                                    <Button
                                        variant="outlined"
                                        startIcon={<CheckCheck size={16} />}
                                        onClick={selectVisible}
                                        disabled={!selectedTeam}
                                    >
                                        Select Visible
                                    </Button>
                                    <Button
                                        color="inherit"
                                        startIcon={<X size={16} />}
                                        onClick={() => setSelectedAgentIds([])}
                                        disabled={!selectedTeam}
                                    >
                                        Clear
                                    </Button>
                                    <span className="inline-flex h-11 items-center justify-center rounded-xl bg-[#59616e] px-3 text-xs font-bold text-white">
                                        {selectedAgentIds.length} selected
                                    </span>
                                </div>
                            </div>

                            <div className="mt-5 overflow-x-auto rounded-2xl border border-[#e5e7ed] bg-white">
                                <table className="w-full min-w-[850px] text-left">
                                    <thead>
                                        <tr className="bg-[#fafafd] text-[11px] font-bold tracking-[0.06em] text-[#555869] uppercase">
                                            <th className="w-14 px-4 py-4"></th>
                                            <th className="px-4 py-4">Agent</th>
                                            <th className="px-4 py-4">Email</th>
                                            <th className="px-4 py-4">
                                                Current Campaign
                                            </th>
                                            <th className="px-4 py-4">
                                                Current Team
                                            </th>
                                            <th className="px-4 py-4 text-right">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#e9eaf0]">
                                        {!selectedTeam ? (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-6 py-12 text-center text-sm text-[#888b9b]"
                                                >
                                                    Select a team to load
                                                    agents.
                                                </td>
                                            </tr>
                                        ) : visibleAgents.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={6}
                                                    className="px-6 py-12 text-center text-sm text-[#888b9b]"
                                                >
                                                    {search.trim()
                                                        ? 'No agents in this team match your search.'
                                                        : 'No agents are currently assigned to this team.'}
                                                </td>
                                            </tr>
                                        ) : (
                                            visibleAgents.map((agent) => {
                                                const available = canSelect();
                                                const willTransfer = Boolean(
                                                    selectedAgentIds.includes(
                                                        agent.id,
                                                    ) &&
                                                        agent.currentTeamId &&
                                                        agent.currentTeamId !==
                                                            selectedTeam.id,
                                                );

                                                return (
                                                    <tr
                                                        key={agent.id}
                                                        onClick={() =>
                                                            toggleAgent(agent)
                                                        }
                                                        className={`${available ? 'cursor-pointer hover:bg-[#fcfaf9]' : 'cursor-not-allowed bg-slate-50 opacity-65'}`}
                                                    >
                                                        <td className="px-4 py-3">
                                                            <Checkbox
                                                                checked={selectedAgentIds.includes(
                                                                    agent.id,
                                                                )}
                                                                disabled={
                                                                    !available
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-[#202230]">
                                                                {agent.name}
                                                            </div>
                                                            <div className="font-mono text-xs text-[#bd2923]">
                                                                {
                                                                    agent.employeeId
                                                                }
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-[#626576]">
                                                            {agent.email}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm">
                                                            {agent.currentCampaign ??
                                                                'Unassigned'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {agent.currentTeam ? (
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        className={`rounded-full px-3 py-1.5 text-xs font-bold ${agent.currentTeamId === selectedTeam.id ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}
                                                                    >
                                                                        {
                                                                            agent.currentTeam
                                                                        }
                                                                    </span>
                                                                    {willTransfer && (
                                                                        <span className="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">
                                                                            Will transfer
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <span className="text-sm text-[#999cab]">
                                                                    Unassigned
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td
                                                            className="px-4 py-3 text-right"
                                                            onClick={(event) =>
                                                                event.stopPropagation()
                                                            }
                                                        >
                                                            <Button
                                                                size="small"
                                                                variant="outlined"
                                                                startIcon={
                                                                    <ArrowRightLeft
                                                                        size={15}
                                                                    />
                                                                }
                                                                disabled={
                                                                    transferringId !==
                                                                    null
                                                                }
                                                                onClick={() =>
                                                                    openTransfer(
                                                                        agent,
                                                                    )
                                                                }
                                                            >
                                                                Transfer
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                </div>
            </main>

            <Dialog
                open={Boolean(transferCandidate)}
                onClose={closeTransfer}
                fullWidth
                maxWidth="xs"
            >
                <DialogTitle sx={{ fontWeight: 800 }}>
                    Transfer Employee
                </DialogTitle>
                <DialogContent sx={{ pt: '12px !important' }}>
                    <p className="mb-5 text-sm font-medium text-slate-700">
                        Select the team you want to transfer{' '}
                        <strong>
                            {transferCandidate?.name.split(/\s+/)[0]}
                        </strong>{' '}
                        to.
                    </p>
                    {transferCandidate?.currentTeam && (
                        <p className="mb-4 text-xs text-slate-500">
                            Current team: {transferCandidate.currentTeam}
                        </p>
                    )}
                    <TextField
                        select
                        fullWidth
                        label="Destination Team"
                        value={transferTeamId}
                        onChange={(event) =>
                            setTransferTeamId(event.target.value)
                        }
                    >
                        {teams
                            .filter(
                                (team) =>
                                    team.id !== transferCandidate?.currentTeamId,
                            )
                            .map((team) => (
                                <MenuItem key={team.id} value={String(team.id)}>
                                    {team.name} · {team.campaign}
                                </MenuItem>
                            ))}
                    </TextField>
                    <p className="mt-4 text-xs text-slate-500">
                        Previous assessment and coaching records will keep their
                        original team and campaign.
                    </p>
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2.5 }}>
                    <Button
                        onClick={closeTransfer}
                        disabled={transferringId !== null}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        onClick={submitTransfer}
                        disabled={!transferTeamId || transferringId !== null}
                    >
                        {transferringId !== null
                            ? 'Transferring...'
                            : 'Confirm Transfer'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

TeamAssigning.layout = {
    breadcrumbs: [{ title: 'Team Assigning', href: '/team-assigning' }],
};
