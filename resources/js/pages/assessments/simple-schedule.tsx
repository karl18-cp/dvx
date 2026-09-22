import { Head, Link, useForm } from '@inertiajs/react';
import { Alert, Button } from '@mui/material';
import { DateTimeField } from '@/components/date-time-field';

type Assessment = {
    id: number;
    title: string;
    publish_at?: string;
    available_at?: string;
    due_at?: string;
};
const localValue = (value?: string) =>
    value
        ? new Intl.DateTimeFormat('sv-SE', {
              timeZone: 'Asia/Manila',
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              hourCycle: 'h23',
          })
              .format(new Date(value))
              .replace(' ', 'T')
        : '';

export default function SimpleSchedule({
    assessment,
    readinessErrors,
}: {
    assessment: Assessment;
    readinessErrors: string[];
}) {
    const form = useForm({
        publish_at: localValue(assessment.publish_at),
        available_at: localValue(assessment.available_at),
        due_at: localValue(assessment.due_at),
    });
    const submit = () =>
        form.patch(`/management/assessments/${assessment.id}/simple-schedule`);

    return (
        <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-6">
            <Head title={`Schedule ${assessment.title}`} />
            <div className="mx-auto max-w-2xl space-y-5">
                <header>
                    <p className="text-xs font-bold tracking-[.18em] text-[#b72822] uppercase">
                        Publish / Schedule
                    </p>
                    <h1 className="text-2xl font-bold">
                        Schedule {assessment.title}
                    </h1>
                    <p className="text-sm text-slate-600">
                        Times use Asia/Manila.
                    </p>
                </header>
                {readinessErrors.length > 0 && (
                    <Alert severity="warning">
                        Return to Review and fix {readinessErrors.length} item
                        {readinessErrors.length === 1 ? '' : 's'} before
                        scheduling.
                    </Alert>
                )}
                <section className="grid gap-4 rounded-2xl border bg-white p-5 shadow-sm">
                    <DateTimeField
                        required
                        type="datetime-local"
                        label="Publish At"
                        value={form.data.publish_at}
                        error={Boolean(form.errors.publish_at)}
                        helperText={form.errors.publish_at}
                        slotProps={{ inputLabel: { shrink: true } }}
                        onChange={(event) =>
                            form.setData('publish_at', event.target.value)
                        }
                    />
                    <DateTimeField
                        type="datetime-local"
                        label="Available From"
                        value={form.data.available_at}
                        error={Boolean(form.errors.available_at)}
                        helperText={form.errors.available_at || 'Optional'}
                        slotProps={{ inputLabel: { shrink: true } }}
                        onChange={(event) =>
                            form.setData('available_at', event.target.value)
                        }
                    />
                    <DateTimeField
                        type="datetime-local"
                        label="Due Date"
                        value={form.data.due_at}
                        error={Boolean(form.errors.due_at)}
                        helperText={form.errors.due_at || 'Optional'}
                        slotProps={{ inputLabel: { shrink: true } }}
                        onChange={(event) =>
                            form.setData('due_at', event.target.value)
                        }
                    />
                </section>
                <div className="flex justify-end gap-2">
                    <Link
                        href={`/management/assessments/${assessment.id}/review`}
                    >
                        <Button>Back to Review</Button>
                    </Link>
                    <Button
                        variant="contained"
                        disabled={
                            form.processing ||
                            readinessErrors.length > 0 ||
                            !form.data.publish_at
                        }
                        onClick={submit}
                    >
                        {form.processing
                            ? 'Scheduling...'
                            : 'Schedule Assessment'}
                    </Button>
                </div>
            </div>
        </main>
    );
}

SimpleSchedule.layout = {
    breadcrumbs: [
        { title: 'Assessments', href: '/management/assessments' },
        { title: 'Schedule', href: '#' },
    ],
};
