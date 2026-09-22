import { Head, Link, useForm } from '@inertiajs/react';
import { Button, MenuItem, TextField } from '@mui/material';
import CoachingExportButton from '@/components/coaching-export-button';
import { DateTimeField } from '@/components/date-time-field';
import { coachingDateValue } from '@/lib/coaching-dates';

type Ref = { id: number; name: string };
type Assignment = {
    id: number;
    is_required: boolean;
    material: { id: number; title: string; type: string; skill?: Ref };
    progress: { completed_at?: string; completion_percentage: string }[];
};
type Coaching = {
    id: number;
    team_name?: string;
    campaign_name?: string;
    employee: Ref & { username: string };
    coach: Ref;
    skill?: Ref;
    assessment?: { id: number; title: string };
    call_evaluation?: {
        id: number;
        percentage: string;
        finalized_at: string;
        scorecard_name: string;
    };
    type: string;
    status: string;
    coaching_date: string;
    follow_up_date?: string;
    summary: string;
    strengths?: string;
    areas_for_improvement?: string;
    action_plan?: string;
    acknowledged_at?: string;
    completed_at?: string;
    training_assignments: Assignment[];
};

export default function CoachingShow({
    coaching,
    materials,
    can_manage,
}: {
    coaching: Coaching;
    materials: { id: number; title: string; type: string }[];
    can_manage: boolean;
}) {
    const locked = coaching.status === 'completed' || !can_manage;
    const form = useForm({
        type: coaching.type,
        coaching_date: coachingDateValue(coaching.coaching_date),
        summary: coaching.summary,
        strengths: coaching.strengths || '',
        areas_for_improvement: coaching.areas_for_improvement || '',
        action_plan: coaching.action_plan || '',
        follow_up_date: coachingDateValue(coaching.follow_up_date),
        status: coaching.status,
    });
    const training = useForm({ material_ids: [] as number[] });
    const completion = useForm({ completion: '' });
    const hasIncompleteRequiredTraining = coaching.training_assignments.some(
        (assignment) =>
            assignment.is_required &&
            !assignment.progress.some((progress) => progress.completed_at),
    );
    const label = (v: string) =>
        v
            .split('_')
            .map((w) => w[0].toUpperCase() + w.slice(1))
            .join(' ');

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title={`Coaching: ${coaching.employee.name}`} />
            <div className="mx-auto max-w-5xl space-y-5">
                <Link
                    className="font-semibold text-red-700"
                    href="/management/coaching"
                >
                    ← Back to Coaching
                </Link>
                <section className="rounded-2xl border bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-bold tracking-wider text-red-700 uppercase">
                                Employee Coaching Record
                            </p>
                            <h1 className="mt-1">{coaching.employee.name}</h1>
                            <p>
                                {coaching.employee.username} ·{' '}
                                {coaching.team_name || 'Unassigned'} · Coach:{' '}
                                {coaching.coach.name}
                            </p>
                        </div>
                        <CoachingExportButton recordId={coaching.id} />
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-sm font-bold">
                            {label(coaching.status)}
                        </span>
                    </div>
                    <div className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-3">
                        <span>
                            <b>Skill</b>
                            <br />
                            {coaching.skill?.name || 'General'}
                        </span>
                        <span>
                            <b>Assessment</b>
                            <br />
                            {coaching.assessment?.title || 'None'}
                        </span>
                        <span>
                            <b>Acknowledged</b>
                            <br />
                            {coaching.acknowledged_at ? 'Yes' : 'Not yet'}
                        </span>
                    </div>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-lg font-bold">Source</h2>
                    {coaching.call_evaluation ? (
                        <div className="mt-2">
                            <p>
                                QA Evaluation ·{' '}
                                {coaching.call_evaluation.scorecard_name} ·{' '}
                                {coaching.call_evaluation.percentage}%
                            </p>
                            <Link
                                className="mt-3 inline-block font-semibold text-red-700"
                                href={`/management/call-evaluations/${coaching.call_evaluation.id}`}
                            >
                                View Evaluation
                            </Link>
                        </div>
                    ) : coaching.assessment ? (
                        <p className="mt-2">
                            Assessment · {coaching.assessment.title}
                        </p>
                    ) : (
                        <p className="mt-2">Manual Coaching</p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="mb-4 text-lg font-bold">
                        Coaching Feedback & Action Plan
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <TextField
                            disabled={locked}
                            label="Coaching Type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        />
                        <DateTimeField
                            disabled={locked}
                            label="Coaching Date"
                            type="date"
                            slotProps={{ inputLabel: { shrink: true } }}
                            value={form.data.coaching_date}
                            onChange={(e) =>
                                form.setData('coaching_date', e.target.value)
                            }
                        />
                        <TextField
                            disabled={locked}
                            className="sm:col-span-2"
                            multiline
                            minRows={2}
                            label="Summary"
                            value={form.data.summary}
                            onChange={(e) =>
                                form.setData('summary', e.target.value)
                            }
                        />
                        <TextField
                            disabled={locked}
                            multiline
                            minRows={2}
                            label="Strengths"
                            value={form.data.strengths}
                            onChange={(e) =>
                                form.setData('strengths', e.target.value)
                            }
                        />
                        <TextField
                            disabled={locked}
                            multiline
                            minRows={2}
                            label="Areas for Improvement"
                            value={form.data.areas_for_improvement}
                            onChange={(e) =>
                                form.setData(
                                    'areas_for_improvement',
                                    e.target.value,
                                )
                            }
                        />
                        <TextField
                            disabled={locked}
                            className="sm:col-span-2"
                            multiline
                            minRows={2}
                            label="Action Plan"
                            value={form.data.action_plan}
                            onChange={(e) =>
                                form.setData('action_plan', e.target.value)
                            }
                        />
                        <DateTimeField
                            disabled={locked}
                            label="Follow-up Date"
                            type="date"
                            slotProps={{ inputLabel: { shrink: true } }}
                            value={form.data.follow_up_date}
                            onChange={(e) =>
                                form.setData('follow_up_date', e.target.value)
                            }
                        />
                        <TextField
                            disabled={locked}
                            select
                            label="Status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            <MenuItem value="open">Open</MenuItem>
                            <MenuItem value="follow_up_required">
                                Follow-up Required
                            </MenuItem>
                        </TextField>
                    </div>
                    {!locked && (
                        <div className="mt-4 flex justify-end">
                            <Button
                                variant="contained"
                                onClick={() =>
                                    form.put(
                                        `/management/coaching/${coaching.id}`,
                                    )
                                }
                            >
                                Save Changes
                            </Button>
                        </div>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-lg font-bold">Assigned Training</h2>
                    <div className="mt-3 space-y-2">
                        {coaching.training_assignments.length ? (
                            coaching.training_assignments.map((a) => (
                                <div
                                    key={a.id}
                                    className="flex items-center justify-between rounded-xl border p-3"
                                >
                                    <div>
                                        <b>{a.material.title}</b>
                                        <p className="text-sm text-slate-600 capitalize">
                                            {a.material.type} ·{' '}
                                            {a.material.skill?.name ||
                                                'General'}
                                        </p>
                                    </div>
                                    <span
                                        className={`rounded-full px-3 py-1 text-xs font-bold ${a.progress[0]?.completed_at ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}
                                    >
                                        {a.progress[0]?.completed_at
                                            ? 'Completed'
                                            : 'Pending'}
                                    </span>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-slate-500">
                                No training assigned.
                            </p>
                        )}
                    </div>
                    {!locked && (
                        <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                            <TextField
                                className="flex-1"
                                select
                                label="Add Training Materials"
                                value={training.data.material_ids.map(String)}
                                slotProps={{ select: { multiple: true } }}
                                onChange={(e) =>
                                    training.setData(
                                        'material_ids',
                                        (typeof e.target.value === 'string'
                                            ? e.target.value.split(',')
                                            : e.target.value
                                        ).map(Number),
                                    )
                                }
                            >
                                {materials.map((m) => (
                                    <MenuItem key={m.id} value={String(m.id)}>
                                        {m.title} — {m.type}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <Button
                                variant="outlined"
                                disabled={!training.data.material_ids.length}
                                onClick={() =>
                                    training.post(
                                        `/management/coaching/${coaching.id}/training`,
                                    )
                                }
                            >
                                Assign Training
                            </Button>
                        </div>
                    )}
                </section>
                {!locked && (
                    <section className="flex items-center justify-between rounded-2xl border border-green-200 bg-green-50 p-5">
                        <div>
                            <h2 className="font-bold">Complete Coaching</h2>
                            <p className="text-sm text-slate-600">
                                {hasIncompleteRequiredTraining
                                    ? 'The employee must complete all required training before this record can be completed.'
                                    : 'All requirements are complete. Completing this record will make it read-only.'}
                            </p>
                            {completion.errors.completion && (
                                <p className="mt-1 text-sm font-semibold text-red-700">
                                    {completion.errors.completion}
                                </p>
                            )}
                        </div>
                        <Button
                            color="success"
                            variant="contained"
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Complete this coaching record? It will become read-only.',
                                    )
                                ) {
                                    completion.post(
                                        `/management/coaching/${coaching.id}/complete`,
                                    );
                                }
                            }}
                            disabled={
                                hasIncompleteRequiredTraining ||
                                completion.processing
                            }
                        >
                            Mark Completed
                        </Button>
                    </section>
                )}
            </div>
        </main>
    );
}
CoachingShow.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Coaching', href: '/management/coaching' },
        { title: 'Record', href: '#' },
    ],
};
