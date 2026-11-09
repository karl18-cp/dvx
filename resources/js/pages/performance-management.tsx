import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    History,
    Plus,
    Star,
    Target,
    TrendingUp,
    UserCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

type Person = {
    id: number;
    name: string;
    username: string;
    role: string;
    avatar: string | null;
};
type Acknowledgement = {
    id: number;
    typed_name: string;
    employee_comment: string | null;
    acknowledged_at: string;
} | null;
type Goal = {
    id: number;
    title: string;
    category: string;
    description: string | null;
    baseline_value: string | null;
    target_value: string | null;
    unit: string | null;
    weight: number;
    start_date: string;
    due_date: string;
    status: string;
    progress: number;
    outcome: string | null;
    creator: { id: number; name: string };
};
type Kpi = {
    name: string;
    target: string;
    actual: string;
    weight: number;
    rating: number;
    comment: string;
};
type Review = {
    id: number;
    period_month: string;
    status: string;
    overall_rating: string | null;
    kpis: Kpi[];
    strengths: string | null;
    development_areas: string | null;
    manager_summary: string | null;
    employee_comments: string | null;
    shared_at: string | null;
    finalized_at: string | null;
    reviewer: { id: number; name: string };
    acknowledgement: Acknowledgement;
};
type Meeting = {
    id: number;
    scheduled_at: string;
    held_at: string | null;
    status: string;
    agenda: string;
    manager_notes: string | null;
    employee_comments: string | null;
    action_items: string[] | null;
    next_meeting_date: string | null;
    manager: { id: number; name: string };
    acknowledgement: Acknowledgement;
};
type Plan = {
    id: number;
    source_review_id: number | null;
    title: string;
    reason: string;
    expectations: string;
    support_plan: string | null;
    success_criteria: string[];
    progress_updates: { note: string; recorded_at: string }[] | null;
    start_date: string;
    review_date: string;
    end_date: string;
    status: string;
    resolution_notes: string | null;
    manager: { id: number; name: string };
    acknowledgement: Acknowledgement;
};
type HistoryItem = {
    type: string;
    id: number;
    title: string;
    date: string | null;
    status: string;
    rating: string | null;
    acknowledged: boolean | null;
};
type Tab = 'overview' | 'goals' | 'reviews' | 'meetings' | 'plans' | 'history';

const date = (value: string | null, withTime = false) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              ...(withTime ? { timeStyle: 'short' as const } : {}),
              timeZone: 'Asia/Manila',
          }).format(new Date(value))
        : '—';
const statusColor = (status: string) =>
    status === 'completed' ||
    status === 'finalized' ||
    status === 'acknowledged'
        ? 'bg-emerald-50 text-emerald-700'
        : status === 'active' || status === 'shared' || status === 'scheduled'
          ? 'bg-blue-50 text-blue-700'
          : status === 'unsuccessful' || status === 'cancelled'
            ? 'bg-red-50 text-red-700'
            : 'bg-slate-100 text-slate-600';

export default function PerformanceManagement({
    employees,
    selectedEmployee,
    goals,
    reviews,
    meetings,
    plans,
    history,
    summary,
    canManage,
    isSelf,
    statusMessage,
}: {
    employees: Person[];
    selectedEmployee: Person;
    goals: Goal[];
    reviews: Review[];
    meetings: Meeting[];
    plans: Plan[];
    history: HistoryItem[];
    summary: {
        active_goals: number;
        average_goal_progress: number;
        latest_rating: string | null;
        open_pips: number;
        next_meeting: string | null;
        pending_acknowledgements: number;
    };
    canManage: boolean;
    isSelf: boolean;
    statusMessage?: string;
}) {
    const [tab, setTab] = useState<Tab>('overview');
    const [modal, setModal] = useState<string | null>(null);
    const [selected, setSelected] = useState<
        Goal | Review | Meeting | Plan | null
    >(null);
    const open = (name: string, record: typeof selected = null) => {
        setSelected(record);
        setModal(name);
    };
    const close = () => {
        setModal(null);
        setSelected(null);
    };
    const transitionReview = (review: Review, action: 'share' | 'finalize') =>
        router.patch(
            `/performance/reviews/${review.id}/transition`,
            { action },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Performance Management" />
            <main className="min-h-full bg-gradient-to-br from-rose-50/70 via-slate-50 to-white p-4 sm:p-6 lg:p-8">
                <header className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                            Growth & accountability
                        </p>
                        <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold text-slate-950">
                            <TrendingUp className="text-red-700" /> Performance
                            Management
                        </h1>
                        <p className="mt-2 text-slate-600">
                            Goals, KPI appraisals, one-on-ones, improvement
                            plans, and acknowledgement history in one employee
                            file.
                        </p>
                    </div>
                    {employees.length > 1 && (
                        <label className="min-w-72 text-sm font-semibold text-slate-700">
                            Employee performance file
                            <select
                                value={selectedEmployee.id}
                                onChange={(event) =>
                                    router.get(
                                        '/performance',
                                        { employee: event.target.value },
                                        { preserveState: false },
                                    )
                                }
                                className="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 font-normal text-slate-900"
                            >
                                {employees.map((employee) => (
                                    <option
                                        key={employee.id}
                                        value={employee.id}
                                    >
                                        {employee.name} · {employee.username}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                </header>

                {statusMessage && (
                    <div className="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {statusMessage}
                    </div>
                )}

                <section className="mb-5 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                    <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-red-100 to-amber-100 text-lg font-bold text-red-800">
                        {selectedEmployee.avatar ? (
                            <img
                                src={selectedEmployee.avatar}
                                alt=""
                                className="size-full object-cover"
                            />
                        ) : (
                            selectedEmployee.name
                                .split(' ')
                                .slice(0, 2)
                                .map((word) => word[0])
                                .join('')
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <h2 className="text-xl font-bold text-slate-950">
                            {selectedEmployee.name}
                        </h2>
                        <p className="text-sm text-slate-500">
                            {selectedEmployee.username} ·{' '}
                            {selectedEmployee.role.replaceAll('_', ' ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => open('goal')}
                            className="inline-flex min-h-10 items-center gap-2 rounded-xl border border-red-200 px-4 font-semibold text-red-700 hover:bg-red-50"
                        >
                            <Plus size={17} /> Add goal
                        </button>
                        {canManage && (
                            <button
                                onClick={() => open('review')}
                                className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-red-700 px-4 font-semibold text-white hover:bg-red-800"
                            >
                                <ClipboardCheck size={17} /> Monthly review
                            </button>
                        )}
                    </div>
                </section>

                <nav className="mb-5 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
                    {(
                        [
                            ['overview', 'Overview'],
                            ['goals', 'Goals'],
                            ['reviews', 'KPI Reviews'],
                            ['meetings', 'One-on-Ones'],
                            ['plans', 'Improvement Plans'],
                            ['history', 'Appraisal History'],
                        ] as [Tab, string][]
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => setTab(value)}
                            className={`min-h-10 shrink-0 rounded-xl px-4 text-sm font-semibold transition ${tab === value ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>

                {tab === 'overview' && (
                    <Overview
                        summary={summary}
                        goals={goals}
                        reviews={reviews}
                        meetings={meetings}
                        plans={plans}
                        setTab={setTab}
                    />
                )}
                {tab === 'goals' && (
                    <GoalsSection
                        goals={goals}
                        onUpdate={(goal) => open('goal-progress', goal)}
                    />
                )}
                {tab === 'reviews' && (
                    <ReviewsSection
                        reviews={reviews}
                        canManage={canManage}
                        isSelf={isSelf}
                        onEdit={(review: Review) => open('review', review)}
                        onShare={(review: Review) =>
                            transitionReview(review, 'share')
                        }
                        onFinalize={(review: Review) =>
                            transitionReview(review, 'finalize')
                        }
                        onAcknowledge={(review: Review) =>
                            open('ack-review', review)
                        }
                    />
                )}
                {tab === 'meetings' && (
                    <MeetingsSection
                        meetings={meetings}
                        canManage={canManage}
                        isSelf={isSelf}
                        onAdd={() => open('meeting')}
                        onUpdate={(meeting: Meeting) =>
                            open('meeting-update', meeting)
                        }
                        onAcknowledge={(meeting: Meeting) =>
                            open('ack-meeting', meeting)
                        }
                    />
                )}
                {tab === 'plans' && (
                    <PlansSection
                        plans={plans}
                        canManage={canManage}
                        isSelf={isSelf}
                        onAdd={() => open('plan')}
                        onUpdate={(plan: Plan) => open('plan-update', plan)}
                        onAcknowledge={(plan: Plan) => open('ack-pip', plan)}
                    />
                )}
                {tab === 'history' && <HistorySection history={history} />}
            </main>

            {modal === 'goal' && (
                <GoalForm employee={selectedEmployee} onClose={close} />
            )}
            {modal === 'goal-progress' && selected && (
                <GoalProgressForm goal={selected as Goal} onClose={close} />
            )}
            {modal === 'review' && (
                <ReviewForm
                    employee={selectedEmployee}
                    review={(selected as Review | null) ?? undefined}
                    onClose={close}
                />
            )}
            {modal === 'meeting' && (
                <MeetingForm employee={selectedEmployee} onClose={close} />
            )}
            {modal === 'meeting-update' && selected && (
                <MeetingUpdateForm
                    meeting={selected as Meeting}
                    onClose={close}
                />
            )}
            {modal === 'plan' && (
                <PlanForm
                    employee={selectedEmployee}
                    reviews={reviews}
                    onClose={close}
                />
            )}
            {modal === 'plan-update' && selected && (
                <PlanUpdateForm plan={selected as Plan} onClose={close} />
            )}
            {modal?.startsWith('ack-') && selected && (
                <AcknowledgementForm
                    type={modal.replace('ack-', '')}
                    record={selected as Review | Meeting | Plan}
                    employee={selectedEmployee}
                    onClose={close}
                />
            )}
        </>
    );
}

function Overview({ summary, goals, reviews, meetings, plans, setTab }: any) {
    const cards = [
        [
            'Active goals',
            summary.active_goals,
            `${summary.average_goal_progress}% average progress`,
            Target,
            'goals',
        ],
        [
            'Latest appraisal',
            summary.latest_rating
                ? `${summary.latest_rating} / 5`
                : 'Not rated',
            `${reviews.length} monthly reviews`,
            Star,
            'reviews',
        ],
        [
            'Next one-on-one',
            summary.next_meeting
                ? date(summary.next_meeting, true)
                : 'Not scheduled',
            `${meetings.length} meeting records`,
            CalendarClock,
            'meetings',
        ],
        [
            'Open improvement plans',
            summary.open_pips,
            `${plans.length} total plans`,
            TrendingUp,
            'plans',
        ],
    ];
    return (
        <div className="space-y-5">
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value, detail, Icon, target]) => {
                    const CardIcon = Icon as typeof Target;
                    return (
                        <button
                            key={String(label)}
                            onClick={() => setTab(target)}
                            className="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <CardIcon className="text-red-700" size={23} />
                            <p className="mt-4 text-sm text-slate-500">
                                {String(label)}
                            </p>
                            <p className="mt-1 text-2xl font-bold text-slate-950">
                                {String(value)}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {String(detail)}
                            </p>
                        </button>
                    );
                })}
            </section>
            {summary.pending_acknowledgements > 0 && (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p className="flex items-center gap-2 font-bold">
                        <UserCheck size={20} />{' '}
                        {summary.pending_acknowledgements} record(s) awaiting
                        acknowledgement
                    </p>
                    <p className="mt-1 text-sm">
                        Open the related review, meeting, or improvement plan to
                        read and acknowledge it.
                    </p>
                </div>
            )}
            <GoalsSection
                goals={goals.slice(0, 3)}
                onUpdate={() => setTab('goals')}
                compact
            />
        </div>
    );
}

function GoalsSection({
    goals,
    onUpdate,
    compact = false,
}: {
    goals: Goal[];
    onUpdate: (g: Goal) => void;
    compact?: boolean;
}) {
    return (
        <Section
            title={compact ? 'Priority goals' : 'Employee goals'}
            subtitle="Measurable targets and progress toward agreed outcomes"
        >
            <div className="grid gap-4 lg:grid-cols-2">
                {goals.map((goal) => (
                    <article
                        key={goal.id}
                        className="rounded-xl border border-slate-200 p-5"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <span className="text-xs font-bold tracking-wide text-red-700 uppercase">
                                    {goal.category}
                                </span>
                                <h3 className="mt-1 font-bold text-slate-950">
                                    {goal.title}
                                </h3>
                            </div>
                            <Status status={goal.status} />
                        </div>
                        {goal.description && (
                            <p className="mt-2 text-sm text-slate-600">
                                {goal.description}
                            </p>
                        )}
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                className="h-full rounded-full bg-red-600"
                                style={{ width: `${goal.progress}%` }}
                            />
                        </div>
                        <div className="mt-2 flex justify-between text-xs text-slate-500">
                            <span>{goal.progress}% complete</span>
                            <span>Due {date(goal.due_date)}</span>
                        </div>
                        {(goal.target_value || goal.weight > 0) && (
                            <p className="mt-3 text-xs text-slate-500">
                                Target: {goal.target_value ?? '—'}{' '}
                                {goal.unit ?? ''} · Weight {goal.weight}%
                            </p>
                        )}
                        {goal.status === 'active' && (
                            <button
                                onClick={() => onUpdate(goal)}
                                className="mt-4 text-sm font-bold text-red-700 hover:underline"
                            >
                                Update progress →
                            </button>
                        )}
                    </article>
                ))}
                {!goals.length && (
                    <Empty text="No performance goals have been added." />
                )}
            </div>
        </Section>
    );
}

function ReviewsSection({
    reviews,
    canManage,
    isSelf,
    onEdit,
    onShare,
    onFinalize,
    onAcknowledge,
}: any) {
    return (
        <Section
            title="Monthly KPI reviews"
            subtitle="Weighted KPI results, coaching context, and final appraisal records"
        >
            <div className="space-y-4">
                {reviews.map((review: Review) => (
                    <article
                        key={review.id}
                        className="rounded-xl border border-slate-200 p-5"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-sm text-slate-500">
                                    {date(review.period_month)}
                                </p>
                                <h3 className="text-lg font-bold text-slate-950">
                                    Monthly KPI appraisal
                                </h3>
                                <p className="text-sm text-slate-500">
                                    Reviewed by {review.reviewer.name}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Status status={review.status} />
                                <span className="rounded-xl bg-amber-50 px-3 py-2 text-lg font-bold text-amber-700">
                                    {review.overall_rating ?? '—'} / 5
                                </span>
                            </div>
                        </div>
                        <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {review.kpis.map((kpi, index) => (
                                <div
                                    key={index}
                                    className="rounded-lg bg-slate-50 p-3"
                                >
                                    <p className="font-semibold text-slate-900">
                                        {kpi.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        Actual {kpi.actual || '—'} / Target{' '}
                                        {kpi.target || '—'}
                                    </p>
                                    <p className="mt-1 text-sm font-bold text-red-700">
                                        {kpi.rating}/5 · {kpi.weight}%
                                    </p>
                                </div>
                            ))}
                        </div>
                        {review.manager_summary && (
                            <p className="mt-4 text-sm text-slate-700">
                                <b>Manager summary:</b> {review.manager_summary}
                            </p>
                        )}
                        {review.acknowledgement && (
                            <p className="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-700">
                                <CheckCircle2 size={16} /> Acknowledged{' '}
                                {date(
                                    review.acknowledgement.acknowledged_at,
                                    true,
                                )}
                            </p>
                        )}
                        <div className="mt-4 flex flex-wrap gap-2">
                            {canManage && review.status === 'draft' && (
                                <>
                                    <Action onClick={() => onEdit(review)}>
                                        Edit draft
                                    </Action>
                                    <Action
                                        primary
                                        onClick={() => onShare(review)}
                                    >
                                        Share for acknowledgement
                                    </Action>
                                </>
                            )}
                            {canManage && review.status === 'acknowledged' && (
                                <Action
                                    primary
                                    onClick={() => onFinalize(review)}
                                >
                                    Finalize appraisal
                                </Action>
                            )}
                            {isSelf &&
                                review.status === 'shared' &&
                                !review.acknowledgement && (
                                    <Action
                                        primary
                                        onClick={() => onAcknowledge(review)}
                                    >
                                        Read & acknowledge
                                    </Action>
                                )}
                        </div>
                    </article>
                ))}
                {!reviews.length && (
                    <Empty text="No monthly KPI reviews yet." />
                )}
            </div>
        </Section>
    );
}

function MeetingsSection({
    meetings,
    canManage,
    isSelf,
    onAdd,
    onUpdate,
    onAcknowledge,
}: any) {
    return (
        <Section
            title="One-on-one meetings"
            subtitle="Scheduled conversations, discussion notes, and agreed action items"
            action={
                canManage ? (
                    <Action primary onClick={onAdd}>
                        <Plus size={15} /> Schedule meeting
                    </Action>
                ) : undefined
            }
        >
            <div className="space-y-4">
                {meetings.map((meeting: Meeting) => (
                    <article
                        key={meeting.id}
                        className="rounded-xl border border-slate-200 p-5"
                    >
                        <div className="flex flex-wrap justify-between gap-3">
                            <div>
                                <h3 className="font-bold text-slate-950">
                                    {date(meeting.scheduled_at, true)}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    With {meeting.manager.name}
                                </p>
                            </div>
                            <Status status={meeting.status} />
                        </div>
                        <p className="mt-3 text-sm text-slate-700">
                            <b>Agenda:</b> {meeting.agenda}
                        </p>
                        {meeting.manager_notes && (
                            <p className="mt-2 text-sm text-slate-700">
                                <b>Meeting notes:</b> {meeting.manager_notes}
                            </p>
                        )}
                        {meeting.action_items?.length ? (
                            <ul className="mt-3 list-inside list-disc text-sm text-slate-600">
                                {meeting.action_items.map((item, i) => (
                                    <li key={i}>{item}</li>
                                ))}
                            </ul>
                        ) : null}
                        {meeting.acknowledgement && (
                            <p className="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-700">
                                <CheckCircle2 size={16} /> Acknowledged{' '}
                                {date(
                                    meeting.acknowledgement.acknowledged_at,
                                    true,
                                )}
                            </p>
                        )}
                        <div className="mt-4 flex gap-2">
                            {canManage && meeting.status === 'scheduled' && (
                                <Action onClick={() => onUpdate(meeting)}>
                                    Update / complete
                                </Action>
                            )}
                            {isSelf &&
                                meeting.status === 'completed' &&
                                !meeting.acknowledgement && (
                                    <Action
                                        primary
                                        onClick={() => onAcknowledge(meeting)}
                                    >
                                        Acknowledge notes
                                    </Action>
                                )}
                        </div>
                    </article>
                ))}
                {!meetings.length && (
                    <Empty text="No one-on-one meetings recorded." />
                )}
            </div>
        </Section>
    );
}

function PlansSection({
    plans,
    canManage,
    isSelf,
    onAdd,
    onUpdate,
    onAcknowledge,
}: any) {
    return (
        <Section
            title="Performance improvement plans"
            subtitle="Clear expectations, support, checkpoints, and documented outcomes"
            action={
                canManage ? (
                    <Action primary onClick={onAdd}>
                        <Plus size={15} /> Create PIP
                    </Action>
                ) : undefined
            }
        >
            <div className="space-y-4">
                {plans.map((plan: Plan) => (
                    <article
                        key={plan.id}
                        className="rounded-xl border border-slate-200 p-5"
                    >
                        <div className="flex flex-wrap justify-between gap-3">
                            <div>
                                <h3 className="font-bold text-slate-950">
                                    {plan.title}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    {date(plan.start_date)} –{' '}
                                    {date(plan.end_date)} · Review{' '}
                                    {date(plan.review_date)}
                                </p>
                            </div>
                            <Status status={plan.status} />
                        </div>
                        <p className="mt-3 text-sm text-slate-700">
                            <b>Reason:</b> {plan.reason}
                        </p>
                        <p className="mt-2 text-sm text-slate-700">
                            <b>Expected improvement:</b> {plan.expectations}
                        </p>
                        <div className="mt-3 rounded-lg bg-slate-50 p-3">
                            <p className="text-xs font-bold text-slate-500 uppercase">
                                Success criteria
                            </p>
                            <ul className="mt-1 list-inside list-disc text-sm text-slate-700">
                                {plan.success_criteria.map((item, i) => (
                                    <li key={i}>{item}</li>
                                ))}
                            </ul>
                        </div>
                        {plan.progress_updates?.length ? (
                            <p className="mt-3 text-sm text-slate-600">
                                Latest update:{' '}
                                {plan.progress_updates.at(-1)?.note}
                            </p>
                        ) : null}
                        {plan.acknowledgement && (
                            <p className="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-700">
                                <CheckCircle2 size={16} /> Acknowledged{' '}
                                {date(
                                    plan.acknowledgement.acknowledged_at,
                                    true,
                                )}
                            </p>
                        )}
                        <div className="mt-4 flex gap-2">
                            {canManage && plan.status === 'active' && (
                                <Action onClick={() => onUpdate(plan)}>
                                    Record progress / resolve
                                </Action>
                            )}
                            {isSelf &&
                                [
                                    'active',
                                    'completed',
                                    'unsuccessful',
                                ].includes(plan.status) &&
                                !plan.acknowledgement && (
                                    <Action
                                        primary
                                        onClick={() => onAcknowledge(plan)}
                                    >
                                        Acknowledge plan
                                    </Action>
                                )}
                        </div>
                    </article>
                ))}
                {!plans.length && (
                    <Empty text="No performance improvement plans." />
                )}
            </div>
        </Section>
    );
}

function HistorySection({ history }: { history: HistoryItem[] }) {
    return (
        <Section
            title="Appraisal history"
            subtitle="Finalized and closed records retained as the employee’s performance history"
        >
            <div className="space-y-2">
                {history.map((item) => (
                    <div
                        key={`${item.type}-${item.id}`}
                        className="flex items-center gap-4 rounded-xl border border-slate-200 p-4"
                    >
                        <div className="flex size-10 items-center justify-center rounded-full bg-slate-100">
                            <History size={18} />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold text-slate-950">
                                {item.title}
                            </p>
                            <p className="text-xs text-slate-500">
                                {item.type.replaceAll('_', ' ')} ·{' '}
                                {date(item.date, true)}
                            </p>
                        </div>
                        {item.rating && (
                            <span className="font-bold text-amber-700">
                                {item.rating}/5
                            </span>
                        )}
                        <Status status={item.status} />
                    </div>
                ))}
                {!history.length && (
                    <Empty text="Finalized appraisals and closed records will appear here." />
                )}
            </div>
        </Section>
    );
}

function GoalForm({
    employee,
    onClose,
}: {
    employee: Person;
    onClose: () => void;
}) {
    const form = useForm({
        employee_id: employee.id,
        title: '',
        category: 'performance',
        description: '',
        baseline_value: '',
        target_value: '',
        unit: '',
        weight: 0,
        start_date: new Date().toISOString().slice(0, 10),
        due_date: '',
    });
    return (
        <Modal title="Add performance goal" onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/performance/goals', { onSuccess: onClose });
                }}
                className="space-y-4"
            >
                <Field label="Goal title" error={form.errors.title}>
                    <input
                        required
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Category">
                        <select
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                        >
                            {[
                                'performance',
                                'quality',
                                'productivity',
                                'attendance',
                                'development',
                                'leadership',
                            ].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Weight (%)">
                        <input
                            type="number"
                            min="0"
                            max="100"
                            value={form.data.weight}
                            onChange={(e) =>
                                form.setData('weight', Number(e.target.value))
                            }
                        />
                    </Field>
                </div>
                <Field label="Description">
                    <textarea
                        rows={3}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Baseline">
                        <input
                            type="number"
                            step="0.01"
                            value={form.data.baseline_value}
                            onChange={(e) =>
                                form.setData('baseline_value', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Target">
                        <input
                            type="number"
                            step="0.01"
                            value={form.data.target_value}
                            onChange={(e) =>
                                form.setData('target_value', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Unit">
                        <input
                            value={form.data.unit}
                            onChange={(e) =>
                                form.setData('unit', e.target.value)
                            }
                            placeholder="%, calls, score"
                        />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Start date">
                        <input
                            required
                            type="date"
                            value={form.data.start_date}
                            onChange={(e) =>
                                form.setData('start_date', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Due date" error={form.errors.due_date}>
                        <input
                            required
                            type="date"
                            value={form.data.due_date}
                            onChange={(e) =>
                                form.setData('due_date', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <Submit processing={form.processing}>Add goal</Submit>
            </form>
        </Modal>
    );
}

function GoalProgressForm({
    goal,
    onClose,
}: {
    goal: Goal;
    onClose: () => void;
}) {
    const form = useForm({
        progress: goal.progress,
        status: goal.status,
        outcome: goal.outcome ?? '',
    });
    return (
        <Modal title="Update goal progress" onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(`/performance/goals/${goal.id}`, {
                        onSuccess: onClose,
                    });
                }}
                className="space-y-4"
            >
                <p className="font-semibold text-slate-900">{goal.title}</p>
                <Field label={`Progress: ${form.data.progress}%`}>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        value={form.data.progress}
                        onChange={(e) =>
                            form.setData('progress', Number(e.target.value))
                        }
                    />
                </Field>
                <Field label="Status">
                    <select
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </Field>
                <Field label="Outcome / update">
                    <textarea
                        rows={4}
                        value={form.data.outcome}
                        onChange={(e) =>
                            form.setData('outcome', e.target.value)
                        }
                    />
                </Field>
                <Submit processing={form.processing}>Save progress</Submit>
            </form>
        </Modal>
    );
}

function ReviewForm({
    employee,
    review,
    onClose,
}: {
    employee: Person;
    review?: Review;
    onClose: () => void;
}) {
    const blank: Kpi = {
        name: '',
        target: '',
        actual: '',
        weight: 0,
        rating: 3,
        comment: '',
    };
    const form = useForm({
        employee_id: employee.id,
        period_month:
            review?.period_month?.slice(0, 7) ??
            new Date().toISOString().slice(0, 7),
        overall_rating: Number(review?.overall_rating ?? 3),
        kpis: review?.kpis ?? [{ ...blank }],
        strengths: review?.strengths ?? '',
        development_areas: review?.development_areas ?? '',
        manager_summary: review?.manager_summary ?? '',
    });
    const updateKpi = (
        index: number,
        field: keyof Kpi,
        value: string | number,
    ) =>
        form.setData(
            'kpis',
            form.data.kpis.map((kpi, i) =>
                i === index ? { ...kpi, [field]: value } : kpi,
            ),
        );
    return (
        <Modal
            title={review ? 'Edit KPI review' : 'Monthly KPI review'}
            onClose={onClose}
            wide
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    const options = { onSuccess: onClose };
                    review
                        ? form.patch(
                              `/performance/reviews/${review.id}`,
                              options,
                          )
                        : form.post('/performance/reviews', options);
                }}
                className="space-y-4"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Review month"
                        error={form.errors.period_month}
                    >
                        <input
                            disabled={Boolean(review)}
                            type="month"
                            value={form.data.period_month}
                            onChange={(e) =>
                                form.setData('period_month', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Overall rating (1–5)">
                        <input
                            type="number"
                            min="1"
                            max="5"
                            step="0.01"
                            value={form.data.overall_rating}
                            onChange={(e) =>
                                form.setData(
                                    'overall_rating',
                                    Number(e.target.value),
                                )
                            }
                        />
                    </Field>
                </div>
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="font-bold text-slate-900">
                            KPI results
                        </h3>
                        <Action
                            onClick={() =>
                                form.setData('kpis', [
                                    ...form.data.kpis,
                                    { ...blank },
                                ])
                            }
                        >
                            <Plus size={14} /> KPI
                        </Action>
                    </div>
                    {form.data.kpis.map((kpi, index) => (
                        <div
                            key={index}
                            className="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-5"
                        >
                            <input
                                className="sm:col-span-2"
                                placeholder="KPI name"
                                value={kpi.name}
                                onChange={(e) =>
                                    updateKpi(index, 'name', e.target.value)
                                }
                            />
                            <input
                                placeholder="Target"
                                value={kpi.target}
                                onChange={(e) =>
                                    updateKpi(index, 'target', e.target.value)
                                }
                            />
                            <input
                                placeholder="Actual"
                                value={kpi.actual}
                                onChange={(e) =>
                                    updateKpi(index, 'actual', e.target.value)
                                }
                            />
                            <div className="flex gap-2">
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    title="Weight"
                                    value={kpi.weight}
                                    onChange={(e) =>
                                        updateKpi(
                                            index,
                                            'weight',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                                <input
                                    type="number"
                                    min="1"
                                    max="5"
                                    step="0.1"
                                    title="Rating"
                                    value={kpi.rating}
                                    onChange={(e) =>
                                        updateKpi(
                                            index,
                                            'rating',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </div>
                        </div>
                    ))}
                </div>
                <Field label="Strengths">
                    <textarea
                        rows={3}
                        value={form.data.strengths}
                        onChange={(e) =>
                            form.setData('strengths', e.target.value)
                        }
                    />
                </Field>
                <Field label="Development areas">
                    <textarea
                        rows={3}
                        value={form.data.development_areas}
                        onChange={(e) =>
                            form.setData('development_areas', e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Manager summary"
                    error={form.errors.manager_summary}
                >
                    <textarea
                        required
                        rows={4}
                        value={form.data.manager_summary}
                        onChange={(e) =>
                            form.setData('manager_summary', e.target.value)
                        }
                    />
                </Field>
                <Submit processing={form.processing}>
                    {review ? 'Update draft' : 'Save draft'}
                </Submit>
            </form>
        </Modal>
    );
}

function MeetingForm({
    employee,
    onClose,
}: {
    employee: Person;
    onClose: () => void;
}) {
    const form = useForm({
        employee_id: employee.id,
        scheduled_at: '',
        agenda: '',
        manager_notes: '',
        action_items: [] as string[],
        next_meeting_date: '',
    });
    const [items, setItems] = useState('');
    return (
        <Modal title="Schedule one-on-one" onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        action_items: items
                            .split('\n')
                            .map((x) => x.trim())
                            .filter(Boolean),
                    }));
                    form.post('/performance/meetings', { onSuccess: onClose });
                }}
                className="space-y-4"
            >
                <Field label="Date and time" error={form.errors.scheduled_at}>
                    <input
                        required
                        type="datetime-local"
                        value={form.data.scheduled_at}
                        onChange={(e) =>
                            form.setData('scheduled_at', e.target.value)
                        }
                    />
                </Field>
                <Field label="Agenda">
                    <textarea
                        required
                        rows={4}
                        value={form.data.agenda}
                        onChange={(e) => form.setData('agenda', e.target.value)}
                    />
                </Field>
                <Field label="Initial notes (optional)">
                    <textarea
                        rows={3}
                        value={form.data.manager_notes}
                        onChange={(e) =>
                            form.setData('manager_notes', e.target.value)
                        }
                    />
                </Field>
                <Field label="Action items (one per line)">
                    <textarea
                        rows={3}
                        value={items}
                        onChange={(e) => setItems(e.target.value)}
                    />
                </Field>
                <Submit processing={form.processing}>Schedule meeting</Submit>
            </form>
        </Modal>
    );
}

function MeetingUpdateForm({
    meeting,
    onClose,
}: {
    meeting: Meeting;
    onClose: () => void;
}) {
    const form = useForm({
        scheduled_at: meeting.scheduled_at.slice(0, 16),
        agenda: meeting.agenda,
        manager_notes: meeting.manager_notes ?? '',
        action_items: meeting.action_items ?? [],
        next_meeting_date: meeting.next_meeting_date ?? '',
        status: meeting.status,
    });
    const [items, setItems] = useState((meeting.action_items ?? []).join('\n'));
    return (
        <Modal title="Update one-on-one" onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        action_items: items
                            .split('\n')
                            .map((x) => x.trim())
                            .filter(Boolean),
                    }));
                    form.patch(`/performance/meetings/${meeting.id}`, {
                        onSuccess: onClose,
                    });
                }}
                className="space-y-4"
            >
                <Field label="Status">
                    <select
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </Field>
                <Field label="Date and time">
                    <input
                        required
                        type="datetime-local"
                        value={form.data.scheduled_at}
                        onChange={(e) =>
                            form.setData('scheduled_at', e.target.value)
                        }
                    />
                </Field>
                <Field label="Agenda">
                    <textarea
                        required
                        rows={3}
                        value={form.data.agenda}
                        onChange={(e) => form.setData('agenda', e.target.value)}
                    />
                </Field>
                <Field label="Manager notes">
                    <textarea
                        rows={5}
                        value={form.data.manager_notes}
                        onChange={(e) =>
                            form.setData('manager_notes', e.target.value)
                        }
                    />
                </Field>
                <Field label="Action items (one per line)">
                    <textarea
                        rows={4}
                        value={items}
                        onChange={(e) => setItems(e.target.value)}
                    />
                </Field>
                <Field label="Next meeting">
                    <input
                        type="date"
                        value={form.data.next_meeting_date}
                        onChange={(e) =>
                            form.setData('next_meeting_date', e.target.value)
                        }
                    />
                </Field>
                <Submit processing={form.processing}>Save meeting</Submit>
            </form>
        </Modal>
    );
}

function PlanForm({
    employee,
    reviews,
    onClose,
}: {
    employee: Person;
    reviews: Review[];
    onClose: () => void;
}) {
    const form = useForm({
        employee_id: employee.id,
        source_review_id: '',
        title: '',
        reason: '',
        expectations: '',
        support_plan: '',
        success_criteria: [] as string[],
        start_date: new Date().toISOString().slice(0, 10),
        review_date: '',
        end_date: '',
    });
    const [criteria, setCriteria] = useState('');
    return (
        <Modal title="Create improvement plan" onClose={onClose} wide>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        source_review_id: data.source_review_id || null,
                        success_criteria: criteria
                            .split('\n')
                            .map((x) => x.trim())
                            .filter(Boolean),
                    }));
                    form.post('/performance/plans', { onSuccess: onClose });
                }}
                className="space-y-4"
            >
                <Field label="Plan title">
                    <input
                        required
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                </Field>
                <Field label="Related KPI review (optional)">
                    <select
                        value={form.data.source_review_id}
                        onChange={(e) =>
                            form.setData('source_review_id', e.target.value)
                        }
                    >
                        <option value="">No linked review</option>
                        {reviews.map((review) => (
                            <option key={review.id} value={review.id}>
                                {date(review.period_month)} ·{' '}
                                {review.overall_rating}/5
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Reason for the plan">
                    <textarea
                        required
                        rows={4}
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                    />
                </Field>
                <Field label="Required performance expectations">
                    <textarea
                        required
                        rows={4}
                        value={form.data.expectations}
                        onChange={(e) =>
                            form.setData('expectations', e.target.value)
                        }
                    />
                </Field>
                <Field label="Support and resources">
                    <textarea
                        rows={3}
                        value={form.data.support_plan}
                        onChange={(e) =>
                            form.setData('support_plan', e.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Success criteria (one per line)"
                    error={form.errors.success_criteria}
                >
                    <textarea
                        required
                        rows={4}
                        value={criteria}
                        onChange={(e) => setCriteria(e.target.value)}
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Start">
                        <input
                            required
                            type="date"
                            value={form.data.start_date}
                            onChange={(e) =>
                                form.setData('start_date', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Review">
                        <input
                            required
                            type="date"
                            value={form.data.review_date}
                            onChange={(e) =>
                                form.setData('review_date', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="End">
                        <input
                            required
                            type="date"
                            value={form.data.end_date}
                            onChange={(e) =>
                                form.setData('end_date', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <Submit processing={form.processing}>Activate plan</Submit>
            </form>
        </Modal>
    );
}

function PlanUpdateForm({
    plan,
    onClose,
}: {
    plan: Plan;
    onClose: () => void;
}) {
    const form = useForm({
        status: plan.status,
        progress_note: '',
        resolution_notes: plan.resolution_notes ?? '',
        review_date: plan.review_date,
        end_date: plan.end_date,
    });
    return (
        <Modal title="Update improvement plan" onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(`/performance/plans/${plan.id}`, {
                        onSuccess: onClose,
                    });
                }}
                className="space-y-4"
            >
                <Field label="Status">
                    <select
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="active">Active</option>
                        <option value="completed">
                            Completed successfully
                        </option>
                        <option value="unsuccessful">Unsuccessful</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </Field>
                <Field label="Progress update">
                    <textarea
                        rows={4}
                        value={form.data.progress_note}
                        onChange={(e) =>
                            form.setData('progress_note', e.target.value)
                        }
                    />
                </Field>
                <Field label="Resolution notes">
                    <textarea
                        rows={4}
                        value={form.data.resolution_notes}
                        onChange={(e) =>
                            form.setData('resolution_notes', e.target.value)
                        }
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Review date">
                        <input
                            type="date"
                            value={form.data.review_date}
                            onChange={(e) =>
                                form.setData('review_date', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="End date">
                        <input
                            type="date"
                            value={form.data.end_date}
                            onChange={(e) =>
                                form.setData('end_date', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <Submit processing={form.processing}>Save plan update</Submit>
            </form>
        </Modal>
    );
}

function AcknowledgementForm({
    type,
    record,
    employee,
    onClose,
}: {
    type: string;
    record: Review | Meeting | Plan;
    employee: Person;
    onClose: () => void;
}) {
    const form = useForm({ typed_name: '', employee_comment: '' });
    return (
        <Modal title="Acknowledge performance record" onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/performance/${type}/${record.id}/acknowledge`, {
                        onSuccess: onClose,
                    });
                }}
                className="space-y-4"
            >
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p className="font-bold">
                        Acknowledgement is not automatic agreement.
                    </p>
                    <p className="mt-1">
                        It confirms that you received and reviewed this record.
                        Your comment becomes part of the permanent appraisal
                        history.
                    </p>
                </div>
                <Field
                    label={`Type your full name: ${employee.name}`}
                    error={form.errors.typed_name}
                >
                    <input
                        required
                        value={form.data.typed_name}
                        onChange={(e) =>
                            form.setData('typed_name', e.target.value)
                        }
                    />
                </Field>
                <Field label="Employee comment (optional)">
                    <textarea
                        rows={4}
                        value={form.data.employee_comment}
                        onChange={(e) =>
                            form.setData('employee_comment', e.target.value)
                        }
                    />
                </Field>
                <Submit processing={form.processing}>
                    Record acknowledgement
                </Submit>
            </form>
        </Modal>
    );
}

function Section({
    title,
    subtitle,
    action,
    children,
}: {
    title: string;
    subtitle: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-slate-950">
                        {title}
                    </h2>
                    <p className="text-sm text-slate-500">{subtitle}</p>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
function Modal({
    title,
    onClose,
    children,
    wide = false,
}: {
    title: string;
    onClose: () => void;
    children: ReactNode;
    wide?: boolean;
}) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-3 backdrop-blur-sm"
            onMouseDown={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                className={`flex max-h-[94vh] w-full flex-col overflow-hidden rounded-3xl bg-white shadow-2xl ${wide ? 'max-w-4xl' : 'max-w-2xl'}`}
            >
                <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <h2 className="text-xl font-bold text-slate-950">
                        {title}
                    </h2>
                    <button
                        onClick={onClose}
                        aria-label="Close"
                        className="flex size-10 items-center justify-center rounded-full hover:bg-slate-100"
                    >
                        <X />
                    </button>
                </header>
                <div className="overflow-y-auto p-6">{children}</div>
            </div>
        </div>
    );
}
function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="block text-sm font-semibold text-slate-700">
            {label}
            <div className="mt-1.5 [&_input]:min-h-11 [&_input]:w-full [&_input]:rounded-xl [&_input]:border [&_input]:border-slate-300 [&_input]:px-3 [&_select]:min-h-11 [&_select]:w-full [&_select]:rounded-xl [&_select]:border [&_select]:border-slate-300 [&_select]:bg-white [&_select]:px-3 [&_textarea]:w-full [&_textarea]:rounded-xl [&_textarea]:border [&_textarea]:border-slate-300 [&_textarea]:p-3">
                {children}
            </div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </label>
    );
}
function Submit({
    processing,
    children,
}: {
    processing: boolean;
    children: ReactNode;
}) {
    return (
        <div className="flex justify-end border-t border-slate-100 pt-4">
            <button
                disabled={processing}
                className="min-h-11 rounded-xl bg-red-700 px-5 font-bold text-white hover:bg-red-800 disabled:opacity-50"
            >
                {processing ? 'Saving…' : children}
            </button>
        </div>
    );
}
function Action({
    onClick,
    children,
    primary = false,
}: {
    onClick: () => void;
    children: ReactNode;
    primary?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-bold ${primary ? 'bg-red-700 text-white hover:bg-red-800' : 'border border-slate-300 text-slate-700 hover:bg-slate-50'}`}
        >
            {children}
        </button>
    );
}
function Status({ status }: { status: string }) {
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold capitalize ${statusColor(status)}`}
        >
            {status.replaceAll('_', ' ')}
        </span>
    );
}
function Empty({ text }: { text: string }) {
    return (
        <div className="col-span-full rounded-xl border border-dashed border-slate-300 px-5 py-12 text-center text-slate-500">
            {text}
        </div>
    );
}
