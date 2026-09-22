import { Head, router } from '@inertiajs/react';
import { DateTimeField } from '@/components/date-time-field';

type Assessment = {
    id: number;
    title: string;
    status: string;
    publish_at?: string;
    available_at?: string;
    due_at?: string;
};
type PageData = {
    data: Assessment[];
    prev_page_url?: string;
    next_page_url?: string;
};
const display = (value?: string) =>
    value
        ? new Date(value).toLocaleString('en-US', {
              timeZone: 'Asia/Manila',
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

export default function AssessmentSchedule({
    assessments,
    filters,
}: {
    assessments: PageData;
    filters: { from: string; to: string };
}) {
    const filter = (key: string, value: string) =>
        router.get(
            '/management/assessments/schedule',
            { ...filters, [key]: value },
            { preserveState: true },
        );

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="Assessment Schedule" />
            <div className="mx-auto max-w-5xl">
                <h1 className="text-2xl font-bold">Assessment Schedule</h1>
                <p className="mb-5 text-[#777b8e]">
                    Chronological publishing, availability, and deadline
                    overview.
                </p>
                <div className="mb-4 flex gap-3">
                    <DateTimeField
                        type="date"
                        value={filters.from}
                        onChange={(e) => filter('from', e.target.value)}
                        className="rounded-lg border p-2"
                    />
                    <DateTimeField
                        type="date"
                        value={filters.to}
                        onChange={(e) => filter('to', e.target.value)}
                        className="rounded-lg border p-2"
                    />
                </div>
                <div className="space-y-3">
                    {assessments.data.map((a) => (
                        <article
                            key={a.id}
                            className="rounded-2xl border bg-white p-4"
                        >
                            <div className="flex justify-between">
                                <strong>{a.title}</strong>
                                <span className="capitalize">{a.status}</span>
                            </div>
                            <div className="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                                <span>Publish: {display(a.publish_at)}</span>
                                <span>
                                    Available: {display(a.available_at)}
                                </span>
                                <span>Due: {display(a.due_at)}</span>
                            </div>
                        </article>
                    ))}
                    {!assessments.data.length && (
                        <div className="rounded-2xl border bg-white p-10 text-center">
                            No assessments scheduled.
                        </div>
                    )}
                </div>
            </div>
        </main>
    );
}

AssessmentSchedule.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Schedule', href: '/management/assessments/schedule' },
    ],
};
