import { Head, Link } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { CalendarDays, Users, X } from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

type Member = {
    id: number;
    name: string;
    employeeId: string;
    avatar: string;
    role: string;
    status: string;
    teamId: number;
    team: string;
    schedule: {
        name: string;
        days: {
            day: number;
            no_schedule: boolean;
            time_in: string | null;
            time_out: string | null;
            break_start: string | null;
            break_end: string | null;
        }[];
    } | null;
};
type Props = {
    summary: {
        teams: number;
        members: number;
        pendingRequests: number;
        openTasks: number;
    };
    teams: { id: number; name: string; campaign: string | null }[];
    members: Member[];
};
const days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];
export default function MyTeam({ summary, teams, members }: Props) {
    const [search, setSearch] = useState('');
    const [team, setTeam] = useState('all');
    const [viewing, setViewing] = useState<Member | null>(null);
    const visible = members.filter(
        (member) =>
            (team === 'all' || String(member.teamId) === team) &&
            `${member.name} ${member.employeeId}`
                .toLowerCase()
                .includes(search.toLowerCase()),
    );

    return (
        <>
            <Head title="My Team" />
            <main className="min-w-0 flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
                <header>
                    <p className="text-xs font-bold tracking-[0.2em] text-red-700 uppercase">
                        Team leader workspace
                    </p>
                    <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                        <Users className="text-red-700" />
                        My Team
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        Your assigned teams, people, and working schedules.
                    </p>
                </header>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        [summary.teams, 'Assigned teams', '/my-team'],
                        [summary.members, 'Active team members', '/my-team'],
                        [
                            summary.pendingRequests,
                            'Leaves awaiting your review',
                            '/leave-requests?scope=team&status=needs_review',
                        ],
                        [summary.openTasks, 'Your open tasks', '/task-tracker'],
                    ].map(([value, label, href]) => (
                        <Link
                            key={label}
                            href={String(href)}
                            className="rounded-2xl border border-red-100 bg-white p-5 shadow-sm transition hover:border-red-300"
                        >
                            <p className="text-3xl font-bold text-red-800">
                                {value}
                            </p>
                            <p className="mt-2 text-sm text-slate-500">
                                {label}
                            </p>
                        </Link>
                    ))}
                </div>
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5">
                        <h2 className="font-bold">Team directory</h2>
                        <div className="flex flex-wrap gap-3">
                            <TextField
                                size="small"
                                label="Search name or ID"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <TextField
                                select
                                size="small"
                                label="Team"
                                value={team}
                                onChange={(e) => setTeam(e.target.value)}
                                sx={{ minWidth: 180 }}
                            >
                                <MenuItem value="all">All my teams</MenuItem>
                                {teams.map((item) => (
                                    <MenuItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name} · {item.campaign}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </div>
                    </div>
                    <div className="max-h-[65vh] overflow-auto">
                        <table className="w-full min-w-[620px] text-left text-sm">
                            <thead className="sticky top-0 bg-red-50 text-xs text-red-800">
                                <tr>
                                    {[
                                        'Employee',
                                        'Team',
                                        'Status',
                                        'Schedule',
                                    ].map((label) => (
                                        <th key={label} className="p-4">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {visible.map((member) => (
                                    <tr key={member.id}>
                                        <td className="p-4">
                                            <div className="flex items-center gap-3">
                                                <Avatar>
                                                    <AvatarImage
                                                        src={member.avatar}
                                                        alt={member.name}
                                                    />
                                                    <AvatarFallback>
                                                        {member.name
                                                            .split(' ')
                                                            .slice(0, 2)
                                                            .map(
                                                                (word) =>
                                                                    word[0],
                                                            )
                                                            .join('')}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="font-semibold">
                                                        {member.name}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {member.employeeId} ·{' '}
                                                        {member.role.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="p-4">{member.team}</td>
                                        <td className="p-4 capitalize">
                                            {member.status}
                                        </td>
                                        <td className="p-4">
                                            {member.schedule ? (
                                                <Button
                                                    size="small"
                                                    startIcon={
                                                        <CalendarDays
                                                            size={16}
                                                        />
                                                    }
                                                    onClick={() =>
                                                        setViewing(member)
                                                    }
                                                >
                                                    {member.schedule.name}
                                                </Button>
                                            ) : (
                                                <span className="text-slate-400">
                                                    Not assigned
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {!visible.length && (
                            <p className="p-12 text-center text-sm text-slate-500">
                                {teams.length
                                    ? 'No matching team members.'
                                    : 'No teams assigned yet. An admin can assign your team in Team Assigning.'}
                            </p>
                        )}
                    </div>
                </section>
            </main>
            <Dialog
                open={!!viewing}
                onClose={() => setViewing(null)}
                fullWidth
                maxWidth="md"
                aria-labelledby="team-schedule-title"
            >
                <DialogTitle
                    id="team-schedule-title"
                    className="flex items-center justify-between"
                >
                    {viewing?.name} · Schedule
                    <IconButton
                        aria-label="Close schedule"
                        onClick={() => setViewing(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent>
                    <p className="mb-4 text-sm text-slate-500">
                        {viewing?.schedule?.name} · Times in Asia/Manila
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[560px] text-left text-sm">
                            <thead>
                                <tr>
                                    {[
                                        'Day',
                                        'Time in',
                                        'Time out',
                                        'Break start',
                                        'Break end',
                                    ].map((label) => (
                                        <th className="p-3" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {viewing?.schedule?.days.map((day) => (
                                    <tr
                                        key={day.day}
                                        className="border-t border-slate-100"
                                    >
                                        <td className="p-3 font-semibold">
                                            {days[day.day - 1]}
                                        </td>
                                        {day.no_schedule ? (
                                            <td
                                                colSpan={4}
                                                className="p-3 text-slate-400"
                                            >
                                                No schedule
                                            </td>
                                        ) : (
                                            [
                                                day.time_in,
                                                day.time_out,
                                                day.break_start,
                                                day.break_end,
                                            ].map((time, index) => (
                                                <td key={index} className="p-3">
                                                    {time?.slice(0, 5) || '—'}
                                                </td>
                                            ))
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

MyTeam.layout = { breadcrumbs: [{ title: 'My Team', href: '/my-team' }] };
