import { Head, router, useForm } from '@inertiajs/react';
import { MenuItem, TextField } from '@mui/material';
import {
    CalendarCheck2,
    CalendarClock,
    CheckCircle2,
    Clock3,
    Search,
    ShieldCheck,
    Users,
    ArrowDownCircle,
    ArrowUpCircle,
    BookOpenCheck,
    Plus,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Link from '@/components/page-link';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

type Credit = {
    id: number;
    name: string;
    username: string;
    role: string;
    status: string;
    avatar: string;
    hire_date: string | null;
    eligibility_date: string | null;
    eligible: boolean;
    annual_credits: number;
    used_credits: number;
    pending_days: number;
    available_credits: number;
    cycle_start: string | null;
    cycle_end: string | null;
    days_until_eligible: number | null;
};

type Props = {
    today: string;
    canViewAll: boolean;
    canManage: boolean;
    filters: { search: string; eligibility: string };
    summary: {
        employees: number;
        eligible: number;
        available: number;
        used: number;
    };
    records: {
        data: Credit[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    ledger: null | {
        employee: {
            id: number;
            name: string;
            username: string;
            avatar: string;
        };
        balance: number;
        totals: Record<string, number>;
        entries: Array<{
            id: number;
            type: string;
            amount: number;
            balance: number;
            effective_date: string;
            cycle_start: string;
            cycle_end: string;
            reference: string | null;
            notes: string | null;
            automatic: boolean;
            created_by: string | null;
        }>;
    };
};

const displayDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              timeZone: 'Asia/Manila',
          }).format(new Date(`${value}T00:00:00+08:00`))
        : 'Not recorded';

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('');

export default function LeaveCredits({
    today,
    canViewAll,
    canManage,
    filters,
    summary,
    records,
    ledger,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [showEntry, setShowEntry] = useState(false);
    const entryForm = useForm({
        type: 'adjustment',
        amount: '',
        effective_date: today,
        reference: '',
        notes: '',
    });
    const visit = (next: {
        search?: string;
        eligibility?: string;
        page?: number;
        employee?: number;
    }) =>
        router.get(
            '/leave-credits',
            {
                search: next.search ?? search,
                eligibility: next.eligibility ?? filters.eligibility,
                page: next.page,
                employee: next.employee ?? ledger?.employee.id,
            },
            { preserveState: true, preserveScroll: true },
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        visit({ search, page: 1 });
    };
    const submitEntry = (event: FormEvent) => {
        event.preventDefault();
        if (!ledger) return;
        entryForm.post(`/leave-credits/${ledger.employee.id}/transactions`, {
            preserveScroll: true,
            onSuccess: () => {
                entryForm.reset('amount', 'reference', 'notes');
                setShowEntry(false);
            },
        });
    };
    const cards = [
        {
            label: 'Employees tracked',
            value: summary.employees,
            icon: Users,
            tone: 'bg-slate-100 text-slate-700',
        },
        {
            label: 'Currently eligible',
            value: summary.eligible,
            icon: ShieldCheck,
            tone: 'bg-emerald-50 text-emerald-700',
        },
        {
            label: 'Credits available',
            value: summary.available,
            icon: CalendarCheck2,
            tone: 'bg-sky-50 text-sky-700',
        },
        {
            label: 'Credits used',
            value: summary.used,
            icon: Clock3,
            tone: 'bg-amber-50 text-amber-700',
        },
    ];

    return (
        <>
            <Head title="Leave Credits" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-6">
                    <header className="relative overflow-hidden rounded-3xl bg-[linear-gradient(120deg,#181321_0%,#431923_58%,#9c2025_100%)] px-6 py-7 text-white shadow-lg sm:px-8">
                        <div className="absolute -top-20 -right-12 size-64 rounded-full border-[36px] border-white/[0.06]" />
                        <div className="relative flex flex-wrap items-end justify-between gap-5">
                            <div>
                                <p className="text-xs font-bold tracking-[0.22em] text-red-200 uppercase">
                                    Workforce benefits
                                </p>
                                <h1 className="mt-3 text-2xl font-bold sm:text-3xl">
                                    Leave Credits Tracker
                                </h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-200">
                                    Five paid leave credits become available
                                    automatically after one full year of service
                                    and renew every service year.
                                </p>
                            </div>
                            <div className="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                                <p className="text-[11px] font-bold tracking-wider text-red-100 uppercase">
                                    As of
                                </p>
                                <p className="mt-1 font-semibold">
                                    {displayDate(today)}
                                </p>
                            </div>
                        </div>
                    </header>

                    {canViewAll && (
                        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {cards.map((card) => (
                                <article
                                    key={card.label}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div
                                        className={`flex size-11 items-center justify-center rounded-2xl ${card.tone}`}
                                    >
                                        <card.icon className="size-5" />
                                    </div>
                                    <p className="mt-4 text-sm font-medium text-slate-500">
                                        {card.label}
                                    </p>
                                    <p className="mt-1 text-3xl font-bold text-slate-950">
                                        {card.value}
                                    </p>
                                </article>
                            ))}
                        </section>
                    )}

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-4 border-b border-slate-100 p-5 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-slate-950">
                                    {canViewAll
                                        ? 'Employee credit balances'
                                        : 'Leave credit balance'}
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Approved paid leave is deducted from the
                                    active service-year balance.
                                </p>
                            </div>
                            {canViewAll && (
                                <form
                                    onSubmit={submit}
                                    className="flex flex-col gap-3 sm:flex-row"
                                >
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Search employee or ID"
                                            className="h-11 min-w-64 rounded-xl border border-slate-300 bg-white pr-4 pl-10 text-sm outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                                        />
                                    </div>
                                    <TextField
                                        select
                                        size="small"
                                        label="Eligibility"
                                        value={filters.eligibility}
                                        onChange={(event) =>
                                            visit({
                                                eligibility: event.target.value,
                                                page: 1,
                                            })
                                        }
                                        sx={{ minWidth: 160 }}
                                    >
                                        {[
                                            ['all', 'All employees'],
                                            ['eligible', 'Eligible'],
                                            ['upcoming', 'Not yet eligible'],
                                        ].map(([value, label]) => (
                                            <MenuItem key={value} value={value}>
                                                {label}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                    <button
                                        type="submit"
                                        className="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white hover:bg-red-800"
                                    >
                                        Search
                                    </button>
                                </form>
                            )}
                        </div>

                        <div className="divide-y divide-slate-100">
                            {records.data.map((credit) => {
                                const percentage = credit.annual_credits
                                    ? Math.min(
                                          100,
                                          (credit.used_credits /
                                              credit.annual_credits) *
                                              100,
                                      )
                                    : 0;

                                return (
                                    <article
                                        key={credit.id}
                                        className="p-5 sm:p-6"
                                    >
                                        <div className="grid gap-5 xl:grid-cols-[minmax(230px,1.2fr)_1fr_1.25fr] xl:items-center">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <Avatar className="size-11 border border-slate-200">
                                                    <AvatarImage
                                                        src={credit.avatar}
                                                        alt={credit.name}
                                                    />
                                                    <AvatarFallback className="bg-red-50 font-bold text-red-700">
                                                        {initials(credit.name)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="truncate font-bold text-slate-950">
                                                        {credit.name}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                        {credit.username} ·{' '}
                                                        {credit.role ===
                                                        'team_leader'
                                                            ? 'Team Leader'
                                                            : 'Agent'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div>
                                                <p className="text-xs font-bold tracking-wide text-slate-400 uppercase">
                                                    Service eligibility
                                                </p>
                                                {credit.hire_date ? (
                                                    <>
                                                        <p className="mt-1 text-sm font-semibold text-slate-800">
                                                            Hired{' '}
                                                            {displayDate(
                                                                credit.hire_date,
                                                            )}
                                                        </p>
                                                        <p
                                                            className={`mt-1 text-xs font-medium ${credit.eligible ? 'text-emerald-700' : 'text-amber-700'}`}
                                                        >
                                                            {credit.eligible
                                                                ? `Eligible · Cycle ends ${displayDate(credit.cycle_end)}`
                                                                : `Eligible on ${displayDate(credit.eligibility_date)} · ${credit.days_until_eligible} days`}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <p className="mt-2 text-sm font-semibold text-red-700">
                                                        Hire date missing
                                                    </p>
                                                )}
                                            </div>
                                            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <div>
                                                        <p className="text-xs font-bold tracking-wide text-slate-400 uppercase">
                                                            Available balance
                                                        </p>
                                                        <p className="mt-1 text-2xl font-bold text-slate-950">
                                                            {
                                                                credit.available_credits
                                                            }{' '}
                                                            <span className="text-sm font-medium text-slate-500">
                                                                of{' '}
                                                                {
                                                                    credit.annual_credits
                                                                }{' '}
                                                                days
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ${credit.eligible ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'}`}
                                                    >
                                                        {credit.eligible ? (
                                                            <CheckCircle2 className="size-3.5" />
                                                        ) : (
                                                            <CalendarClock className="size-3.5" />
                                                        )}
                                                        {credit.eligible
                                                            ? 'Eligible'
                                                            : 'Waiting period'}
                                                    </span>
                                                </div>
                                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                                    <div
                                                        className="h-full rounded-full bg-red-600"
                                                        style={{
                                                            width: `${percentage}%`,
                                                        }}
                                                    />
                                                </div>
                                                <div className="mt-2 flex justify-between text-xs text-slate-500">
                                                    <span>
                                                        {credit.used_credits}{' '}
                                                        used
                                                    </span>
                                                    <span>
                                                        {credit.pending_days}{' '}
                                                        pending request days
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        visit({
                                                            employee: credit.id,
                                                            page: records.current_page,
                                                        })
                                                    }
                                                    className="mt-3 inline-flex items-center gap-2 text-sm font-bold text-red-700 hover:text-red-800"
                                                >
                                                    <BookOpenCheck className="size-4" />
                                                    View accrual ledger
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                            {!records.data.length && (
                                <div className="flex min-h-56 flex-col items-center justify-center px-6 text-center">
                                    <CalendarCheck2 className="size-10 text-slate-300" />
                                    <p className="mt-3 font-bold text-slate-700">
                                        No employees found
                                    </p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Adjust the search or eligibility filter.
                                    </p>
                                </div>
                            )}
                        </div>
                        {records.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-sm text-slate-500">
                                <span>
                                    Page {records.current_page} of{' '}
                                    {records.last_page}
                                </span>
                                <div className="flex gap-2">
                                    {records.prev_page_url ? (
                                        <Link
                                            href={records.prev_page_url}
                                            className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Previous
                                        </Link>
                                    ) : null}
                                    {records.next_page_url ? (
                                        <Link
                                            href={records.next_page_url}
                                            className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Next
                                        </Link>
                                    ) : null}
                                </div>
                            </div>
                        )}
                    </section>

                    {ledger && (
                        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-3">
                                    <Avatar className="size-11 border border-slate-200">
                                        <AvatarImage
                                            src={ledger.employee.avatar}
                                        />
                                        <AvatarFallback className="bg-red-50 font-bold text-red-700">
                                            {initials(ledger.employee.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <h2 className="text-lg font-bold text-slate-950">
                                            {ledger.employee.name}'s accrual
                                            ledger
                                        </h2>
                                        <p className="text-sm text-slate-500">
                                            {ledger.employee.username} · Running
                                            balance {ledger.balance} day(s)
                                        </p>
                                    </div>
                                </div>
                                {canManage && (
                                    <button
                                        type="button"
                                        onClick={() => setShowEntry(true)}
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 text-sm font-bold text-white hover:bg-red-800"
                                    >
                                        <Plus className="size-4" /> Record
                                        transaction
                                    </button>
                                )}
                            </div>

                            <div className="grid gap-3 border-b border-slate-100 bg-slate-50/70 p-5 sm:grid-cols-3 lg:grid-cols-6">
                                {[
                                    ['earned', 'Earned'],
                                    ['used', 'Used'],
                                    ['expired', 'Expired'],
                                    ['carried_over', 'Carried over'],
                                    ['paid_out', 'Paid out'],
                                    ['adjustment', 'Adjusted'],
                                ].map(([key, label]) => (
                                    <div
                                        key={key}
                                        className="rounded-xl border border-slate-200 bg-white p-3"
                                    >
                                        <p className="text-[11px] font-bold tracking-wide text-slate-400 uppercase">
                                            {label}
                                        </p>
                                        <p
                                            className={`mt-1 text-xl font-bold ${(ledger.totals[key] ?? 0) < 0 ? 'text-red-700' : 'text-emerald-700'}`}
                                        >
                                            {(ledger.totals[key] ?? 0) > 0
                                                ? '+'
                                                : ''}
                                            {ledger.totals[key] ?? 0}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase">
                                        <tr>
                                            <th className="px-5 py-3">
                                                Date / type
                                            </th>
                                            <th className="px-5 py-3">
                                                Reference
                                            </th>
                                            <th className="px-5 py-3">
                                                Service year
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Change
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Balance
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {ledger.entries.map((entry) => (
                                            <tr
                                                key={entry.id}
                                                className="align-top"
                                            >
                                                <td className="px-5 py-4">
                                                    <div className="flex items-start gap-2">
                                                        {entry.amount >= 0 ? (
                                                            <ArrowUpCircle className="mt-0.5 size-4 text-emerald-600" />
                                                        ) : (
                                                            <ArrowDownCircle className="mt-0.5 size-4 text-red-600" />
                                                        )}
                                                        <div>
                                                            <p className="font-bold text-slate-900 capitalize">
                                                                {entry.type.replace(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                            </p>
                                                            <p className="mt-0.5 text-xs text-slate-500">
                                                                {displayDate(
                                                                    entry.effective_date,
                                                                )}{' '}
                                                                ·{' '}
                                                                {entry.automatic
                                                                    ? 'Automatic'
                                                                    : (entry.created_by ??
                                                                      'Manual')}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="max-w-sm px-5 py-4">
                                                    <p className="font-medium text-slate-800">
                                                        {entry.reference ||
                                                            'No reference'}
                                                    </p>
                                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                                        {entry.notes || '—'}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-slate-600">
                                                    {displayDate(
                                                        entry.cycle_start,
                                                    )}{' '}
                                                    –{' '}
                                                    {displayDate(
                                                        entry.cycle_end,
                                                    )}
                                                </td>
                                                <td
                                                    className={`px-5 py-4 text-right font-bold ${entry.amount >= 0 ? 'text-emerald-700' : 'text-red-700'}`}
                                                >
                                                    {entry.amount > 0
                                                        ? '+'
                                                        : ''}
                                                    {entry.amount}
                                                </td>
                                                <td className="px-5 py-4 text-right font-bold text-slate-950">
                                                    {entry.balance}
                                                </td>
                                            </tr>
                                        ))}
                                        {!ledger.entries.length && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="px-5 py-12 text-center text-slate-500"
                                                >
                                                    No ledger activity yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}
                </div>
            </main>

            {showEntry && ledger && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                    onMouseDown={() => setShowEntry(false)}
                >
                    <div
                        className="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl"
                        onMouseDown={(event) => event.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                            <div>
                                <h2 className="text-xl font-bold text-slate-950">
                                    Record leave credit transaction
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {ledger.employee.name} · Current balance{' '}
                                    {ledger.balance} day(s)
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowEntry(false)}
                                className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                            >
                                <X className="size-5" />
                            </button>
                        </div>
                        <form onSubmit={submitEntry} className="space-y-4 p-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="space-y-1.5 text-sm font-semibold text-slate-700">
                                    Transaction type
                                    <select
                                        value={entryForm.data.type}
                                        onChange={(e) =>
                                            entryForm.setData(
                                                'type',
                                                e.target.value,
                                            )
                                        }
                                        className="h-12 w-full rounded-xl border border-slate-300 bg-white px-3 font-normal outline-none focus:border-red-500"
                                    >
                                        <option value="adjustment">
                                            Manual adjustment (+/-)
                                        </option>
                                        <option value="carried_over">
                                            Carried over
                                        </option>
                                        <option value="expired">Expired</option>
                                        <option value="paid_out">
                                            Paid out
                                        </option>
                                    </select>
                                </label>
                                <label className="space-y-1.5 text-sm font-semibold text-slate-700">
                                    Days
                                    <input
                                        type="number"
                                        step="0.25"
                                        required
                                        value={entryForm.data.amount}
                                        onChange={(e) =>
                                            entryForm.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        className="h-12 w-full rounded-xl border border-slate-300 px-3 font-normal outline-none focus:border-red-500"
                                        placeholder={
                                            entryForm.data.type === 'adjustment'
                                                ? 'Use + or -'
                                                : '0.00'
                                        }
                                    />
                                </label>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="space-y-1.5 text-sm font-semibold text-slate-700">
                                    Effective date
                                    <input
                                        type="date"
                                        required
                                        max={today}
                                        value={entryForm.data.effective_date}
                                        onChange={(e) =>
                                            entryForm.setData(
                                                'effective_date',
                                                e.target.value,
                                            )
                                        }
                                        className="h-12 w-full rounded-xl border border-slate-300 px-3 font-normal outline-none focus:border-red-500"
                                    />
                                </label>
                                <label className="space-y-1.5 text-sm font-semibold text-slate-700">
                                    Reference (optional)
                                    <input
                                        value={entryForm.data.reference}
                                        onChange={(e) =>
                                            entryForm.setData(
                                                'reference',
                                                e.target.value,
                                            )
                                        }
                                        className="h-12 w-full rounded-xl border border-slate-300 px-3 font-normal outline-none focus:border-red-500"
                                        placeholder="Memo or payout reference"
                                    />
                                </label>
                            </div>
                            <label className="block space-y-1.5 text-sm font-semibold text-slate-700">
                                Reason / notes
                                <textarea
                                    required
                                    rows={4}
                                    value={entryForm.data.notes}
                                    onChange={(e) =>
                                        entryForm.setData(
                                            'notes',
                                            e.target.value,
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 p-3 font-normal outline-none focus:border-red-500"
                                    placeholder="Document why this ledger entry is needed."
                                />
                            </label>
                            {Object.values(entryForm.errors).length > 0 && (
                                <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                                    {Object.values(entryForm.errors)[0]}
                                </p>
                            )}
                            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowEntry(false)}
                                    className="h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={entryForm.processing}
                                    className="h-11 rounded-xl bg-red-700 px-5 text-sm font-bold text-white disabled:opacity-50"
                                >
                                    {entryForm.processing
                                        ? 'Saving…'
                                        : 'Save transaction'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
