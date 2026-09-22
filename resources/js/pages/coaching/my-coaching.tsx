import { Head, Link } from '@inertiajs/react';
import { formatCoachingDate } from '@/lib/coaching-dates';

type RecordRow = {
    id: number;
    coaching_date: string;
    type: string;
    status: string;
    follow_up_date?: string;
    training_assignments_count: number;
    coach: { name: string };
    skill?: { name: string };
    assessment?: { title: string };
};
export default function MyCoaching({
    records,
}: {
    records: { data: RecordRow[] };
}) {
    const label = (v: string) =>
        v
            .split('_')
            .map((w) => w[0].toUpperCase() + w.slice(1))
            .join(' ');

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="My Coaching" />
            <div className="mx-auto max-w-5xl space-y-5">
                <header>
                    <h1>My Coaching</h1>
                    <p>
                        Review coaching feedback, action plans, and assigned
                        training.
                    </p>
                </header>
                <div className="space-y-3">
                    {records.data.map((record) => (
                        <Link
                            key={record.id}
                            href={`/my-coaching/${record.id}`}
                            className="block rounded-2xl border bg-white p-5 transition hover:border-red-300 hover:shadow-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold text-slate-500">
                                        {formatCoachingDate(
                                            record.coaching_date,
                                        )}{' '}
                                        · Coach: {record.coach.name}
                                    </p>
                                    <h2 className="mt-1 text-lg font-bold">
                                        {record.type}
                                    </h2>
                                    <p className="text-sm text-slate-600">
                                        {record.skill?.name ||
                                            'General Coaching'}
                                        {record.assessment
                                            ? ` · ${record.assessment.title}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">
                                        {label(record.status)}
                                    </span>
                                    <p className="mt-2 text-xs text-slate-500">
                                        {record.training_assignments_count}{' '}
                                        training items
                                    </p>
                                </div>
                            </div>
                        </Link>
                    ))}
                    {!records.data.length && (
                        <div className="rounded-2xl border bg-white p-8 text-center text-slate-500">
                            You have no coaching records.
                        </div>
                    )}
                </div>
            </div>
        </main>
    );
}
MyCoaching.layout = {
    breadcrumbs: [{ title: 'My Coaching', href: '/my-coaching' }],
};
