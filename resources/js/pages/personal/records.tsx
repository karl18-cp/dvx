import { Head, router } from '@inertiajs/react';
import { Button } from '@mui/material';
import PageLink from '@/components/page-link';

type RecordRow = {
    id: number;
    created_at: string;
    sanction_name?: string;
    sanction_description?: string;
    punishment?: string;
    notes?: string;
    issuer_name?: string;
    average?: string;
    quality?: number;
    productivity?: number;
    attendance?: number;
    communication?: number;
    professionalism?: number;
    comments?: string;
    reviewer_name?: string;
    form_title?: string;
    points?: number;
    answers?: {
        id: string;
        label: string;
        value: string | number | string[] | null;
    }[];
};
const tabs = {
    sanctions: 'Sanctions',
    ratings: 'Satisfaction ratings',
    responses: 'Form responses',
};
export default function MyRecords({
    tab,
    records,
}: {
    tab: keyof typeof tabs;
    records: {
        data: RecordRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <main className="space-y-6 p-4 sm:p-6 lg:p-8">
            <Head title="My Records" />
            <header>
                <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                    Your workspace
                </p>
                <h1 className="mt-2 text-3xl font-bold">My Records</h1>
                <p className="mt-2 text-slate-500">
                    Review your sanctions, satisfaction ratings, and submitted
                    forms.
                </p>
            </header>
            <div className="flex flex-wrap gap-3">
                {(Object.keys(tabs) as (keyof typeof tabs)[]).map((value) => (
                    <Button
                        key={value}
                        aria-pressed={tab === value}
                        variant={tab === value ? 'contained' : 'outlined'}
                        onClick={() =>
                            router.get('/my-records', { tab: value })
                        }
                    >
                        {tabs[value]}
                    </Button>
                ))}
            </div>
            <section className="space-y-4">
                {records.data.map((row) => (
                    <article
                        key={row.id}
                        className="rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white p-5 shadow-sm sm:p-6"
                    >
                        <div className="flex flex-wrap justify-between gap-3">
                            <h2 className="text-lg font-bold">
                                {row.sanction_name ||
                                    row.form_title ||
                                    `Average score: ${row.average} / 5`}
                            </h2>
                            <time className="text-sm text-slate-500">
                                {new Date(row.created_at).toLocaleString(
                                    'en-US',
                                    {
                                        timeZone: 'Asia/Manila',
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    },
                                )}
                            </time>
                        </div>
                        {tab === 'sanctions' && (
                            <div className="mt-4 space-y-3">
                                <p className="font-semibold text-red-800">
                                    {row.punishment}
                                </p>
                                <p className="whitespace-pre-wrap">
                                    {row.sanction_description ||
                                        'No description'}
                                </p>
                                <p className="whitespace-pre-wrap text-slate-600">
                                    {row.notes || 'No additional notes'}
                                </p>
                                <p className="text-sm text-slate-500">
                                    Issued by {row.issuer_name}
                                </p>
                            </div>
                        )}
                        {tab === 'ratings' && (
                            <div className="mt-4 space-y-4">
                                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                                    {(
                                        [
                                            'quality',
                                            'productivity',
                                            'attendance',
                                            'communication',
                                            'professionalism',
                                        ] as const
                                    ).map((category) => (
                                        <div
                                            key={category}
                                            className="rounded-xl bg-red-50 p-3"
                                        >
                                            <dt className="text-sm capitalize">
                                                {category}
                                            </dt>
                                            <dd className="mt-2 text-xl font-bold text-red-800">
                                                {row[category]} / 5
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <p className="whitespace-pre-wrap">
                                    {row.comments || 'No comments'}
                                </p>
                                <p className="text-sm text-slate-500">
                                    Reviewed by {row.reviewer_name}
                                </p>
                            </div>
                        )}
                        {tab === 'responses' && (
                            <div className="mt-4">
                                <p className="mb-3 text-sm text-red-800">
                                    Ranking points: {row.points}
                                </p>
                                <details>
                                    <summary className="cursor-pointer font-semibold">
                                        View my answers
                                    </summary>
                                    <dl className="mt-3 space-y-4">
                                        {row.answers?.map((answer) => (
                                            <div key={answer.id}>
                                                <dt className="font-medium">
                                                    {answer.label}
                                                </dt>
                                                <dd className="mt-1 break-words whitespace-pre-wrap text-slate-600">
                                                    {Array.isArray(answer.value)
                                                        ? answer.value.join(
                                                              ', ',
                                                          ) || 'No answer'
                                                        : (answer.value ??
                                                          'No answer')}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </details>
                            </div>
                        )}
                    </article>
                ))}
                {!records.data.length && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                        No {tabs[tab].toLowerCase()} to show.
                    </div>
                )}
            </section>
            <nav aria-label="Records pages" className="flex justify-end gap-4">
                {records.prev_page_url && (
                    <PageLink href={records.prev_page_url}>Previous</PageLink>
                )}
                {records.next_page_url && (
                    <PageLink href={records.next_page_url}>Next</PageLink>
                )}
            </nav>
        </main>
    );
}
MyRecords.layout = {
    breadcrumbs: [{ title: 'My Records', href: '/my-records' }],
};
