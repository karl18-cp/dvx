import { Head, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Banknote,
    CalendarClock,
    CircleDollarSign,
    Clock3,
    FileCheck2,
    FileClock,
    Landmark,
    PhilippinePeso,
    ReceiptText,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from 'lucide-react';
import Link from '@/components/page-link';
import type { Auth } from '@/types';

type Props = {
    asOf: string;
    metrics: {
        payroll_paid_cents: number;
        payroll_paid_count: number;
        pending_cents: number;
        pending_count: number;
        receivable_balance_cents: number;
        receivable_count: number;
        payable_balance_cents: number;
        payable_count: number;
    };
    monthActivity: {
        receipts_cents: number;
        supplier_payments_cents: number;
        expenses_cents: number;
        payroll_cents: number;
    };
    priorities: {
        draft_payrolls: number;
        approved_for_payment: number;
        overdue_receivables: number;
        overdue_payables: number;
        backpays_waiting: number;
        contracts_expiring: number;
    };
    upcoming: {
        id: number;
        direction: 'receivable' | 'payable';
        party_name: string;
        description: string;
        due_date: string;
        balance_cents: number;
        overdue: boolean;
    }[];
    recentActivity: {
        key: string;
        title: string;
        detail: string;
        amount_cents: number;
        occurred_at: string;
        href: string;
    }[];
};

const money = (cents: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(cents / 100);

const date = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'Asia/Manila',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00+08:00`));

const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));

const panel = 'rounded-2xl border border-slate-200/90 bg-white shadow-sm';

export default function AccountingDashboard({
    asOf,
    metrics,
    monthActivity,
    priorities,
    upcoming,
    recentActivity,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const activityMax = Math.max(
        1,
        monthActivity.receipts_cents,
        monthActivity.supplier_payments_cents,
        monthActivity.expenses_cents,
        monthActivity.payroll_cents,
    );
    const cards = [
        {
            label: 'Payroll paid this month',
            value: money(metrics.payroll_paid_cents),
            note: `${metrics.payroll_paid_count} paid ${metrics.payroll_paid_count === 1 ? 'payslip' : 'payslips'}`,
            icon: PhilippinePeso,
            href: '/accounting/workspace/payment-register',
            tone: 'bg-emerald-50 text-emerald-700',
        },
        {
            label: 'Pending accounting entries',
            value: money(metrics.pending_cents),
            note: `${metrics.pending_count} awaiting approval or payment`,
            icon: Clock3,
            href: '/accounting/workspace/payment-register',
            tone: 'bg-amber-50 text-amber-700',
        },
        {
            label: 'Outstanding receivables',
            value: money(metrics.receivable_balance_cents),
            note: `${metrics.receivable_count} open ${metrics.receivable_count === 1 ? 'record' : 'records'}`,
            icon: TrendingUp,
            href: '/accounting/workspace/receivables',
            tone: 'bg-sky-50 text-sky-700',
        },
        {
            label: 'Outstanding payables',
            value: money(metrics.payable_balance_cents),
            note: `${metrics.payable_count} open ${metrics.payable_count === 1 ? 'record' : 'records'}`,
            icon: TrendingDown,
            href: '/accounting/workspace/payables',
            tone: 'bg-rose-50 text-rose-700',
        },
    ];
    const work = [
        { label: 'Payroll drafts', count: priorities.draft_payrolls, icon: ReceiptText, href: '/accounting/workspace/payment-register' },
        { label: 'Ready for payment', count: priorities.approved_for_payment, icon: FileCheck2, href: '/accounting/workspace/payment-register' },
        { label: 'Overdue receivables', count: priorities.overdue_receivables, icon: AlertCircle, href: '/accounting/workspace/receivables' },
        { label: 'Overdue payables', count: priorities.overdue_payables, icon: CalendarClock, href: '/accounting/workspace/payables' },
        { label: 'Backpays waiting', count: priorities.backpays_waiting, icon: WalletCards, href: '/accounting/workspace/backpay' },
        { label: 'Contracts expiring', count: priorities.contracts_expiring, icon: FileClock, href: '/accounting/workspace/contracts' },
    ];
    const activity = [
        { label: 'Receipts recorded', value: monthActivity.receipts_cents, color: 'bg-emerald-500' },
        { label: 'Payroll paid', value: monthActivity.payroll_cents, color: 'bg-red-600' },
        { label: 'Operating expenses', value: monthActivity.expenses_cents, color: 'bg-amber-500' },
        { label: 'Supplier payments', value: monthActivity.supplier_payments_cents, color: 'bg-violet-500' },
    ];

    return (
        <>
            <Head title="Accounting Dashboard" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-6">
                    <header className="relative overflow-hidden rounded-3xl bg-[linear-gradient(120deg,#151120_0%,#361722_52%,#8d1d24_100%)] px-6 py-7 text-white shadow-lg sm:px-8">
                        <div className="absolute -top-24 -right-16 size-72 rounded-full border-[38px] border-white/[0.06]" />
                        <div className="absolute -right-4 -bottom-28 size-64 rounded-full bg-red-400/10 blur-2xl" />
                        <div className="relative flex flex-wrap items-end justify-between gap-5">
                            <div>
                                <p className="text-xs font-bold tracking-[0.22em] text-red-200 uppercase">Finance control center</p>
                                <h1 className="mt-3 text-2xl font-bold sm:text-3xl">Good day, {auth.user.name.split(' ')[0]}.</h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-200">Review money due, payroll work, and records that need your attention from one place.</p>
                            </div>
                            <div className="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                                <p className="text-[11px] font-bold tracking-wider text-red-100 uppercase">Books as of</p>
                                <p className="mt-1 font-semibold">{date(asOf)}</p>
                            </div>
                        </div>
                    </header>

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {cards.map((card) => (
                            <Link key={card.label} href={card.href} className={`${panel} group p-5 transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md`}>
                                <div className="flex items-start justify-between gap-4">
                                    <div className={`flex size-11 items-center justify-center rounded-2xl ${card.tone}`}><card.icon className="size-5" /></div>
                                    <ArrowRight className="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-red-600" />
                                </div>
                                <p className="mt-5 text-sm font-medium text-slate-500">{card.label}</p>
                                <p className="mt-1 truncate text-2xl font-bold tracking-tight text-slate-950">{card.value}</p>
                                <p className="mt-2 text-xs text-slate-500">{card.note}</p>
                            </Link>
                        ))}
                    </section>

                    <div className="grid gap-6 xl:grid-cols-[1.08fr_0.92fr]">
                        <section className={`${panel} overflow-hidden`}>
                            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                                <div><h2 className="text-lg font-bold text-slate-950">Accounting priorities</h2><p className="mt-1 text-sm text-slate-500">Items that may need action today</p></div>
                                <CircleDollarSign className="size-6 text-red-600" />
                            </div>
                            <div className="grid sm:grid-cols-2">
                                {work.map((item) => (
                                    <Link key={item.label} href={item.href} className="group flex items-center gap-4 border-b border-slate-100 px-6 py-4 odd:sm:border-r hover:bg-red-50/40">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 group-hover:bg-red-100 group-hover:text-red-700"><item.icon className="size-5" /></div>
                                        <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold text-slate-800">{item.label}</p><p className="mt-0.5 text-xs text-slate-500">Open workspace</p></div>
                                        <span className={`flex min-w-8 items-center justify-center rounded-full px-2 py-1 text-xs font-bold ${item.count > 0 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500'}`}>{item.count}</span>
                                    </Link>
                                ))}
                            </div>
                        </section>

                        <section className={`${panel} p-6`}>
                            <div className="flex items-start justify-between gap-4"><div><h2 className="text-lg font-bold text-slate-950">Monthly activity</h2><p className="mt-1 text-sm text-slate-500">Recorded transactions for {date(asOf).replace(/\d+,/, '')}</p></div><Landmark className="size-6 text-red-600" /></div>
                            <div className="mt-6 space-y-5">
                                {activity.map((row) => (
                                    <div key={row.label}>
                                        <div className="mb-2 flex items-center justify-between gap-4 text-sm"><span className="font-medium text-slate-600">{row.label}</span><span className="font-bold text-slate-900">{money(row.value)}</span></div>
                                        <div className="h-2.5 overflow-hidden rounded-full bg-slate-100"><div className={`h-full min-w-0 rounded-full ${row.color}`} style={{ width: row.value ? `${Math.max(5, (row.value / activityMax) * 100)}%` : '0%' }} /></div>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">These categories are shown separately so expenses, supplier payments, and payroll are never combined or double-counted.</p>
                        </section>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                        <section className={`${panel} overflow-hidden`}>
                            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h2 className="text-lg font-bold text-slate-950">Upcoming and overdue</h2><p className="mt-1 text-sm text-slate-500">Open receivables and payables ordered by due date</p></div><CalendarClock className="size-6 text-red-600" /></div>
                            {upcoming.length ? (
                                <div className="divide-y divide-slate-100">
                                    {upcoming.map((item) => (
                                        <Link key={item.id} href={`/accounting/workspace/${item.direction === 'receivable' ? 'receivables' : 'payables'}`} className="flex flex-wrap items-center gap-4 px-6 py-4 hover:bg-slate-50">
                                            <div className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${item.direction === 'receivable' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700'}`}>{item.direction === 'receivable' ? <TrendingUp className="size-5" /> : <TrendingDown className="size-5" />}</div>
                                            <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold text-slate-900">{item.party_name}</p><p className="truncate text-xs text-slate-500">{item.description}</p></div>
                                            <div className="text-right"><p className="text-sm font-bold text-slate-900">{money(item.balance_cents)}</p><p className={`mt-0.5 text-xs font-medium ${item.overdue ? 'text-red-600' : 'text-slate-500'}`}>{item.overdue ? 'Overdue · ' : 'Due '}{date(item.due_date)}</p></div>
                                        </Link>
                                    ))}
                                </div>
                            ) : <div className="flex min-h-48 flex-col items-center justify-center px-6 text-center"><Banknote className="size-9 text-slate-300" /><p className="mt-3 font-semibold text-slate-700">No open due items</p><p className="mt-1 text-sm text-slate-500">New receivables and payables will appear here.</p></div>}
                        </section>

                        <section className={`${panel} overflow-hidden`}>
                            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5"><div><h2 className="text-lg font-bold text-slate-950">Recent activity</h2><p className="mt-1 text-sm text-slate-500">Latest payroll and expense changes</p></div><ReceiptText className="size-6 text-red-600" /></div>
                            {recentActivity.length ? <div className="divide-y divide-slate-100">{recentActivity.map((item) => (
                                <Link key={item.key} href={item.href} className="flex items-center gap-3 px-6 py-4 hover:bg-slate-50"><div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700"><PhilippinePeso className="size-4" /></div><div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold text-slate-900">{item.title}</p><p className="truncate text-xs text-slate-500">{item.detail} · {dateTime(item.occurred_at)}</p></div><p className="text-sm font-bold text-slate-800">{money(item.amount_cents)}</p></Link>
                            ))}</div> : <div className="flex min-h-48 flex-col items-center justify-center px-6 text-center"><ReceiptText className="size-9 text-slate-300" /><p className="mt-3 font-semibold text-slate-700">No accounting activity yet</p></div>}
                        </section>
                    </div>
                </div>
            </main>
        </>
    );
}
