import { Head, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { CalendarDays, Pencil, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import TrainingAllowanceDetails, {
    type Allowance,
    money,
} from '@/components/training-allowance-details';

type Trainee = {
    id: number;
    name: string;
    username: string;
    training_campaign_id: number;
    training_status?: string;
};
type Plan = {
    id: number;
    name: string;
    campaign_id: number;
    campaign: { name: string };
    start_date: string;
    weekdays: number[];
    phases: { days: number; rate_cents: number }[];
    first_allowance_day: number;
    trainees: Trainee[];
};
type Props = {
    plans: Plan[];
    campaigns: { id: number; name: string }[];
    trainees: Trainee[];
    enrollments: { user_id: number; training_plan_id: number }[];
};
type PlanForm = {
    name: string;
    campaign_id: string;
    start_date: string;
    weekdays: number[];
    phases: { days: number; rate: string }[];
    first_allowance_day: number;
    allowance_basis: string;
    trainee_ids: number[];
};
const phaseNames = ['Oral training', 'Call training', 'Nesting'];
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const defaults = (): PlanForm => ({
    name: '',
    campaign_id: '',
    start_date: '',
    weekdays: [1, 2, 3, 4, 5],
    phases: [
        { days: 3, rate: '' },
        { days: 5, rate: '' },
        { days: 2, rate: '' },
    ],
    first_allowance_day: 10,
    allowance_basis: 'attended',
    trainee_ids: [],
});

function AllowanceReport({
    plan,
    onClose,
}: {
    plan: Plan;
    onClose: () => void;
}) {
    const [person, setPerson] = useState(plan.trainees[0]?.id ?? 0);
    const [allowance, setAllowance] = useState<Allowance | null>(null);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!person) return;
        const controller = new AbortController();
        fetch(`/management/training-plans/${plan.id}/trainees/${person}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok)
                    throw new Error(
                        'Unable to load the allowance. Please reopen this report.',
                    );
                return response.json();
            })
            .then(setAllowance)
            .catch((e) => {
                if (e.name !== 'AbortError') setError(e.message);
            });
        return () => controller.abort();
    }, [person, plan.id]);
    return (
        <Dialog open onClose={onClose} fullWidth maxWidth="lg">
            <DialogTitle className="flex items-center justify-between">
                Allowance progress · {plan.name}
                <IconButton
                    aria-label="Close allowance report"
                    onClick={onClose}
                >
                    <X />
                </IconButton>
            </DialogTitle>
            <DialogContent dividers>
                <TextField
                    select
                    fullWidth
                    label="Trainee"
                    value={person || ''}
                    onChange={(e) => {
                        setAllowance(null);
                        setError('');
                        setPerson(Number(e.target.value));
                    }}
                    sx={{ mb: 3 }}
                >
                    {plan.trainees.map((t) => (
                        <MenuItem key={t.id} value={t.id}>
                            {t.name} · {t.username}
                        </MenuItem>
                    ))}
                </TextField>
                {!person ? (
                    <Alert severity="info">
                        Enroll trainees to see their allowance progress.
                    </Alert>
                ) : error ? (
                    <Alert severity="error">{error}</Alert>
                ) : allowance ? (
                    <TrainingAllowanceDetails
                        allowance={allowance}
                        threshold={plan.first_allowance_day}
                    />
                ) : (
                    <div className="p-10 text-center">
                        <CircularProgress />
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function TrainingPlans({
    plans,
    campaigns,
    trainees,
    enrollments,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Plan | null>(null);
    const [report, setReport] = useState<Plan | null>(null);
    const [search, setSearch] = useState('');
    const [traineeSearch, setTraineeSearch] = useState('');
    const [saved, setSaved] = useState('');
    const form = useForm<PlanForm>(defaults());
    const begin = (plan: Plan | null) => {
        setEditing(plan);
        form.clearErrors();
        setTraineeSearch('');
        form.setData(
            plan
                ? {
                      name: plan.name,
                      campaign_id: String(plan.campaign_id),
                      start_date: plan.start_date.slice(0, 10),
                      weekdays: plan.weekdays,
                      phases: plan.phases.map((p) => ({
                          days: p.days,
                          rate: (p.rate_cents / 100).toFixed(2),
                      })),
                      first_allowance_day: plan.first_allowance_day,
                      allowance_basis: 'attended',
                      trainee_ids: plan.trainees.map((t) => t.id),
                  }
                : defaults(),
        );
        setOpen(true);
    };
    const candidates = [
        ...new Map(
            [...trainees, ...(editing?.trainees ?? [])].map((t) => [t.id, t]),
        ).values(),
    ].filter(
        (t) =>
            t.training_campaign_id === Number(form.data.campaign_id) &&
            !enrollments.some(
                (e) => e.user_id === t.id && e.training_plan_id !== editing?.id,
            ),
    );
    const totalDays = form.data.phases.reduce(
        (sum, p) => sum + Number(p.days),
        0,
    );
    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setSaved(
                    editing
                        ? 'Training plan updated.'
                        : 'Training plan created.',
                );
            },
        };
        if (editing)
            form.put(`/management/training-plans/${editing.id}`, options);
        else form.post('/management/training-plans', options);
    };
    return (
        <>
            <Head title="Training Plans" />
            <main className="space-y-6 p-4 sm:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                            Training & Development
                        </p>
                        <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                            <CalendarDays className="text-red-700" />
                            Training Plans
                        </h1>
                        <p className="mt-2 text-slate-500">
                            Plan oral training, calls, and nesting. Track
                            attendance-based allowances.
                        </p>
                    </div>
                    <Button
                        variant="contained"
                        startIcon={<Plus size={18} />}
                        onClick={() => begin(null)}
                    >
                        Create training plan
                    </Button>
                </header>
                {saved && (
                    <Alert severity="success" onClose={() => setSaved('')}>
                        {saved}
                    </Alert>
                )}
                <TextField
                    size="small"
                    label="Search plans or campaigns"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    sx={{ minWidth: 280 }}
                />
                <div className="grid gap-5 xl:grid-cols-2">
                    {plans
                        .filter((p) =>
                            `${p.name} ${p.campaign.name}`
                                .toLowerCase()
                                .includes(search.toLowerCase()),
                        )
                        .map((plan) => (
                            <section
                                key={plan.id}
                                className="space-y-5 rounded-2xl border border-t-4 border-slate-200 border-t-red-700 bg-white p-6 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-xl font-bold">
                                            {plan.name}
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {plan.campaign.name} · Starts{' '}
                                            {plan.start_date.slice(0, 10)}
                                        </p>
                                    </div>
                                    <IconButton
                                        aria-label={`Edit ${plan.name}`}
                                        onClick={() => begin(plan)}
                                    >
                                        <Pencil size={18} />
                                    </IconButton>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    {plan.phases.map((phase, i) => (
                                        <div
                                            key={i}
                                            className="rounded-xl bg-red-50/70 p-4"
                                        >
                                            <p className="text-sm font-semibold">
                                                {phaseNames[i]}
                                            </p>
                                            <p className="mt-2 text-lg font-bold">
                                                {phase.days} days
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {money(phase.rate_cents)} /
                                                attended day
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <p className="text-sm text-slate-500">
                                    {plan.weekdays
                                        .map((d) => weekdays[d - 1])
                                        .join(', ')}{' '}
                                    · {plan.trainees.length} enrolled · First
                                    allowance after {plan.first_allowance_day}{' '}
                                    attended days
                                </p>
                                <Button
                                    variant="outlined"
                                    onClick={() => setReport(plan)}
                                >
                                    View allowance progress
                                </Button>
                            </section>
                        ))}
                </div>
                {!plans.length && (
                    <div className="rounded-2xl border border-dashed border-red-200 bg-white p-12 text-center text-slate-500">
                        Create a plan and enroll trainees from its campaign to
                        get started.
                    </div>
                )}
            </main>
            <Dialog
                open={open}
                onClose={() => !form.processing && setOpen(false)}
                fullWidth
                maxWidth="lg"
                slotProps={{ paper: { sx: { borderRadius: 4 } } }}
            >
                <DialogTitle className="flex items-center justify-between">
                    {editing ? 'Edit training plan' : 'Create training plan'}
                    <IconButton
                        aria-label="Close training plan"
                        disabled={form.processing}
                        onClick={() => setOpen(false)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent
                    dividers
                    sx={{ '& .MuiOutlinedInput-root': { borderRadius: 2 } }}
                >
                    <div className="space-y-6">
                        <div className="grid gap-5 md:grid-cols-3">
                            <TextField
                                label="Plan name"
                                required
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            <TextField
                                select
                                required
                                label="Campaign"
                                value={form.data.campaign_id}
                                onChange={(e) => {
                                    form.setData({
                                        ...form.data,
                                        campaign_id: e.target.value,
                                        trainee_ids: [],
                                    });
                                }}
                            >
                                {campaigns.map((c) => (
                                    <MenuItem key={c.id} value={c.id}>
                                        {c.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                type="date"
                                required
                                label="Training starts"
                                slotProps={{ inputLabel: { shrink: true } }}
                                value={form.data.start_date}
                                onChange={(e) =>
                                    form.setData('start_date', e.target.value)
                                }
                            />
                        </div>
                        <section>
                            <h3 className="mb-2 font-semibold">
                                Training weekdays
                            </h3>
                            <div className="flex flex-wrap gap-2">
                                {weekdays.map((day, i) => (
                                    <label
                                        key={day}
                                        className="rounded-xl border border-slate-200 pr-3 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.weekdays.includes(
                                                i + 1,
                                            )}
                                            onChange={(e) =>
                                                form.setData(
                                                    'weekdays',
                                                    e.target.checked
                                                        ? [
                                                              ...form.data
                                                                  .weekdays,
                                                              i + 1,
                                                          ].sort()
                                                        : form.data.weekdays.filter(
                                                              (d) =>
                                                                  d !== i + 1,
                                                          ),
                                                )
                                            }
                                        />
                                        {day}
                                    </label>
                                ))}
                            </div>
                            <p className="mt-2 text-xs text-slate-500">
                                Training day counts skip unselected weekdays.
                                Attendance time-in and time-out still use each
                                trainee’s assigned campaign schedule.
                            </p>
                        </section>
                        <div className="grid gap-4 md:grid-cols-3">
                            {form.data.phases.map((phase, i) => (
                                <section
                                    key={i}
                                    className="space-y-5 rounded-2xl border border-red-100 bg-red-50/50 p-5"
                                >
                                    <h3 className="font-bold">
                                        {i + 1}. {phaseNames[i]}
                                    </h3>
                                    <TextField
                                        fullWidth
                                        label="Training days"
                                        type="number"
                                        value={phase.days}
                                        onChange={(e) =>
                                            form.setData(
                                                'phases',
                                                form.data.phases.map((p, j) =>
                                                    j === i
                                                        ? {
                                                              ...p,
                                                              days: Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : p,
                                                ),
                                            )
                                        }
                                        slotProps={{
                                            htmlInput: { min: 0, max: 180 },
                                        }}
                                    />
                                    <TextField
                                        fullWidth
                                        label="Daily allowance (PHP)"
                                        type="number"
                                        value={phase.rate}
                                        onChange={(e) =>
                                            form.setData(
                                                'phases',
                                                form.data.phases.map((p, j) =>
                                                    j === i
                                                        ? {
                                                              ...p,
                                                              rate: e.target
                                                                  .value,
                                                          }
                                                        : p,
                                                ),
                                            )
                                        }
                                        slotProps={{
                                            htmlInput: { min: 0, step: '0.01' },
                                        }}
                                    />
                                </section>
                            ))}
                        </div>
                        <section className="rounded-2xl border border-slate-200 p-5">
                            <h3 className="mb-4 font-bold">
                                First allowance release
                            </h3>
                            <TextField
                                type="number"
                                label="After this many attended days"
                                value={form.data.first_allowance_day}
                                onChange={(e) =>
                                    form.setData(
                                        'first_allowance_day',
                                        Number(e.target.value),
                                    )
                                }
                                slotProps={{
                                    htmlInput: { min: 1, max: totalDays },
                                }}
                                sx={{ minWidth: 280 }}
                            />
                            <p className="mt-3 text-sm text-slate-500">
                                {totalDays} planned training days. Only
                                completed attendance with positive worked hours
                                earns the daily rate. The first allowance totals
                                the first {form.data.first_allowance_day}{' '}
                                qualifying days; it is not an automatic payment.
                            </p>
                        </section>
                        <section>
                            <h3 className="mb-3 font-bold">
                                Enroll trainees · {form.data.trainee_ids.length}{' '}
                                selected
                            </h3>
                            <TextField
                                fullWidth
                                size="small"
                                label="Search trainees"
                                value={traineeSearch}
                                onChange={(e) =>
                                    setTraineeSearch(e.target.value)
                                }
                            />
                            <div className="mt-3 max-h-52 overflow-auto rounded-xl border border-slate-200">
                                {candidates
                                    .filter((t) =>
                                        `${t.name} ${t.username}`
                                            .toLowerCase()
                                            .includes(
                                                traineeSearch.toLowerCase(),
                                            ),
                                    )
                                    .map((t) => (
                                        <label
                                            key={t.id}
                                            className="flex items-center gap-2 border-b border-slate-100 px-3 py-2"
                                        >
                                            <Checkbox
                                                checked={form.data.trainee_ids.includes(
                                                    t.id,
                                                )}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'trainee_ids',
                                                        e.target.checked
                                                            ? [
                                                                  ...form.data
                                                                      .trainee_ids,
                                                                  t.id,
                                                              ]
                                                            : form.data.trainee_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      t.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            <span>
                                                {t.name}{' '}
                                                <span className="text-sm text-slate-500">
                                                    {t.username}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                {!candidates.length && (
                                    <p className="p-5 text-sm text-slate-500">
                                        Select a campaign with active,
                                        unenrolled trainees.
                                    </p>
                                )}
                            </div>
                        </section>
                        {editing && (
                            <Alert severity="info">
                                Editing dates, phases, or rates recalculates
                                allowance estimates. Existing trainees cannot be
                                removed after the plan starts. Changes are
                                logged.
                            </Alert>
                        )}
                        {Object.entries(form.errors).map(([key, error]) => (
                            <Alert key={key} severity="error">
                                {error}
                            </Alert>
                        ))}
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        {form.processing ? 'Saving…' : 'Save training plan'}
                    </Button>
                </DialogActions>
            </Dialog>
            {report && (
                <AllowanceReport
                    key={report.id}
                    plan={report}
                    onClose={() => setReport(null)}
                />
            )}
        </>
    );
}
TrainingPlans.layout = {
    breadcrumbs: [
        { title: 'Training Plans', href: '/management/training-plans' },
    ],
};
