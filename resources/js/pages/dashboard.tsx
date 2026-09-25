import { Head, Link, usePage } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
} from '@mui/material';
import {
    ArrowUpRight,
    Award,
    Cake,
    CalendarDays,
    Megaphone,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { Auth } from '@/types';

type Person = { id: number; name: string; avatar: string };
type Celebration = Person & { date: string; daysAway: number; years?: number };
type Leader = Person & { username: string | null; points: number };
type Announcement = {
    id: number;
    title: string;
    body: string;
    author_name: string;
    created_at: string;
};
type Props = {
    teamLeaderSummary?: {
        teams: number;
        members: number;
        pendingRequests: number;
        openTasks: number;
    } | null;
    today: string;
    leaders: Leader[];
    birthdays: Celebration[];
    anniversaries: Celebration[];
    announcements: Announcement[];
};
const dateLabel = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: 'Asia/Manila',
    }).format(
        new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value),
    );
const panel = 'min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm';

function PersonAvatar({ person }: { person: Person }) {
    return (
        <Avatar className="size-10 shrink-0 border border-white shadow-sm">
            <AvatarImage src={person.avatar} alt={person.name} />
            <AvatarFallback className="bg-red-100 font-bold text-red-800">
                {person.name
                    .split(' ')
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((word) => word[0])
                    .join('')}
            </AvatarFallback>
        </Avatar>
    );
}

function Celebrations({
    title,
    items,
    anniversary = false,
}: {
    title: string;
    items: Celebration[];
    anniversary?: boolean;
}) {
    return (
        <section className={panel}>
            <div className="flex items-center gap-3 border-b border-slate-100 p-5">
                <span className="rounded-xl bg-red-50 p-3 text-red-800">
                    {anniversary ? <Award size={20} /> : <Cake size={20} />}
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="font-bold text-slate-900">{title}</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Today and the next 29 days
                    </p>
                </div>
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
                    {items.length}
                </span>
            </div>
            <div className="max-h-80 space-y-2 overflow-y-auto p-4">
                {items.map((person) => (
                    <div
                        key={person.id}
                        className={`flex items-center gap-3 rounded-xl p-3 ${person.daysAway === 0 ? 'border border-red-100 bg-red-50' : 'bg-slate-50'}`}
                    >
                        <PersonAvatar person={person} />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold break-words text-slate-800">
                                {person.name}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {anniversary
                                    ? `${person.years} ${person.years === 1 ? 'year' : 'years'} with Divertex`
                                    : 'Birthday'}{' '}
                                · {dateLabel(person.date)}
                            </p>
                        </div>
                        <span
                            className={`shrink-0 text-xs font-semibold ${person.daysAway === 0 ? 'text-red-700' : 'text-slate-500'}`}
                        >
                            {person.daysAway === 0
                                ? 'Today'
                                : person.daysAway === 1
                                  ? 'Tomorrow'
                                  : `In ${person.daysAway} days`}
                        </span>
                    </div>
                ))}
                {!items.length && (
                    <p className="px-3 py-9 text-center text-sm text-slate-500">
                        No{' '}
                        {anniversary ? 'employment anniversaries' : 'birthdays'}{' '}
                        in the next 30 days.
                    </p>
                )}
            </div>
        </section>
    );
}

export default function Dashboard({
    teamLeaderSummary,
    today,
    leaders,
    birthdays,
    anniversaries,
    announcements,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [viewing, setViewing] = useState<Announcement | null>(null);

    return (
        <>
            <Head title="Dashboard" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-6">
                    <header className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#211629] via-[#571d27] to-[#a82027] p-6 text-white sm:p-8">
                        <div className="pointer-events-none absolute -top-24 -right-16 size-80 rounded-full border-[40px] border-white/5" />
                        <div className="relative flex flex-wrap items-center justify-between gap-5">
                            <div>
                                <p className="text-xs font-bold tracking-[0.2em] text-red-200 uppercase">
                                    Your Divertex workspace
                                </p>
                                <h1 className="mt-3 text-2xl font-bold sm:text-3xl">
                                    Welcome back, {auth.user.name.split(' ')[0]}
                                    .
                                </h1>
                                <p className="mt-3 max-w-xl text-sm leading-6 text-red-100">
                                    Celebrate your team’s achievements and
                                    milestones, and stay up to date with what’s
                                    happening.
                                </p>
                            </div>
                            <span className="flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm">
                                <CalendarDays size={17} />
                                {new Intl.DateTimeFormat('en-US', {
                                    dateStyle: 'full',
                                    timeZone: 'Asia/Manila',
                                }).format(new Date(`${today}T00:00:00+08:00`))}
                            </span>
                        </div>
                    </header>
                    <section className={`${panel} overflow-hidden`}>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:p-6">
                            <div className="flex items-center gap-3">
                                <span className="rounded-xl bg-red-50 p-3 text-red-800">
                                    <Megaphone size={22} />
                                </span>
                                <div>
                                    <h2 className="text-lg font-bold text-slate-900">
                                        Latest announcements
                                    </h2>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Updates from your admins
                                    </p>
                                </div>
                            </div>
                            <Link
                                href="/announcements"
                                className="flex items-center gap-1 text-xs font-bold text-red-800"
                            >
                                View all <ArrowUpRight size={15} />
                            </Link>
                        </div>
                        <div className="grid gap-4 p-4 sm:p-6 lg:grid-cols-2">
                            {announcements.map((item) => (
                                <article
                                    key={item.id}
                                    className="flex min-w-0 flex-col rounded-xl border border-red-100 bg-red-50/30 p-5"
                                >
                                    <p className="text-xs text-slate-500">
                                        {dateLabel(item.created_at)} ·{' '}
                                        {item.author_name}
                                    </p>
                                    <h3 className="mt-3 text-lg font-bold break-words text-slate-900">
                                        {item.title}
                                    </h3>
                                    <p className="mt-2 mb-4 line-clamp-3 text-sm leading-6 break-words whitespace-pre-wrap text-slate-600">
                                        {item.body}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => setViewing(item)}
                                        className="mt-auto self-start rounded py-1 text-sm font-bold text-red-800 focus-visible:outline-2 focus-visible:outline-red-600"
                                        aria-label={`Read ${item.title}`}
                                    >
                                        Read announcement{' '}
                                        <span aria-hidden="true">→</span>
                                    </button>
                                </article>
                            ))}
                            {!announcements.length && (
                                <p className="py-10 text-center text-sm text-slate-500 lg:col-span-2">
                                    No announcements yet. Company updates will
                                    appear here when published.
                                </p>
                            )}
                        </div>
                    </section>
                    {teamLeaderSummary && (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                [
                                    teamLeaderSummary.teams,
                                    'Your teams',
                                    '/my-team',
                                ],
                                [
                                    teamLeaderSummary.members,
                                    'Active team members',
                                    '/my-team',
                                ],
                                [
                                    teamLeaderSummary.pendingRequests,
                                    'Leaves awaiting your review',
                                    '/leave-requests?scope=team&status=needs_review',
                                ],
                                [
                                    teamLeaderSummary.openTasks,
                                    'Your open tasks',
                                    '/task-tracker',
                                ],
                            ].map(([value, label, href]) => (
                                <Link
                                    key={label}
                                    href={String(href)}
                                    className={`${panel} p-5 transition hover:border-red-300`}
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
                    )}
                    <div className="grid items-start gap-6 xl:grid-cols-[1.05fr_1fr]">
                        <section
                            className={`${panel} flex flex-col self-stretch overflow-hidden`}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5 sm:p-6">
                                <div className="flex items-center gap-3">
                                    <span className="rounded-xl bg-amber-50 p-3 text-amber-600">
                                        <Trophy size={22} />
                                    </span>
                                    <div>
                                        <h2 className="text-lg font-bold text-slate-900">
                                            Top 5 performers
                                        </h2>
                                        <p className="mt-1 text-xs text-slate-500">
                                            All-time ranking points
                                        </p>
                                    </div>
                                </div>
                                <Link
                                    href="/ranking"
                                    className="flex items-center gap-1 text-xs font-bold text-red-800"
                                >
                                    Full ranking <ArrowUpRight size={15} />
                                </Link>
                            </div>
                            <div
                                className={`flex-1 space-y-3 p-4 sm:p-6 ${!leaders.length ? 'flex items-center justify-center' : ''}`}
                            >
                                {leaders.map((person, index) => {
                                    const rank =
                                        leaders.findIndex(
                                            (entry) =>
                                                entry.points === person.points,
                                        ) + 1;

                                    return (
                                        <div
                                            key={person.id}
                                            className={`flex items-center gap-3 rounded-xl border p-4 ${index === 0 ? 'border-amber-200 bg-gradient-to-r from-amber-50 to-white' : 'border-slate-100 bg-slate-50/70'}`}
                                        >
                                            <span
                                                className={`w-6 shrink-0 text-center text-lg font-extrabold ${rank === 1 ? 'text-amber-600' : 'text-slate-400'}`}
                                            >
                                                {rank}
                                            </span>
                                            <PersonAvatar person={person} />
                                            <div className="min-w-0 flex-1">
                                                <h3 className="text-sm font-bold break-words text-slate-800">
                                                    {person.name}
                                                </h3>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {person.username ||
                                                        'Employee'}
                                                </p>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <p className="text-xl font-extrabold text-red-800">
                                                    {person.points.toLocaleString()}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    points
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                                {!leaders.length && (
                                    <div className="py-14 text-center">
                                        <Trophy
                                            className="mx-auto mb-4 text-amber-400"
                                            size={34}
                                        />
                                        <p className="font-semibold text-slate-700">
                                            The leaderboard starts here
                                        </p>
                                        <p className="mt-2 text-sm text-slate-500">
                                            Employees appear after earning
                                            points from ranking-enabled forms.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>
                        <div className="min-w-0 space-y-6">
                            <Celebrations
                                title="Employment anniversaries"
                                items={anniversaries}
                                anniversary
                            />
                            <Celebrations
                                title="Upcoming birthdays"
                                items={birthdays}
                            />
                        </div>
                    </div>
                </div>
            </main>
            <Dialog
                open={!!viewing}
                onClose={() => setViewing(null)}
                fullWidth
                maxWidth="md"
                scroll="paper"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '90dvh' } },
                }}
            >
                <DialogTitle sx={{ fontWeight: 800, overflowWrap: 'anywhere' }}>
                    {viewing?.title}
                </DialogTitle>
                <DialogContent dividers>
                    {viewing && (
                        <>
                            <p className="mb-4 text-xs text-slate-500">
                                {viewing.author_name} ·{' '}
                                {dateLabel(viewing.created_at)}
                            </p>
                            <p className="text-sm leading-7 break-words whitespace-pre-wrap text-slate-700">
                                {viewing.body}
                            </p>
                        </>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setViewing(null)}>Close</Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
