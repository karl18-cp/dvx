import { Head, Link } from '@inertiajs/react';

type Attempt = {
    id: number;
    completed_at: string;
    assessment: string;
    category: string | null;
    attempt_number: number;
    percentage: number;
    status: string;
    time_taken_seconds: number | null;
};
type Page = {
    data: Attempt[];
    links: { url: string | null; label: string; active: boolean }[];
};
export default function History({ attempts }: { attempts: Page }) {
    return (
        <>
            <Head title="Assessment History" />
            <main className="min-h-full bg-[#f7f7fa] p-6 lg:p-8">
                <div className="mx-auto max-w-6xl">
                    <Nav />
                    <h1 className="mt-5 text-3xl font-bold">
                        Assessment History
                    </h1>
                    <div className="mt-6 overflow-hidden rounded-2xl border bg-white">
                        {attempts.data.length === 0 ? (
                            <Empty text="No assessment attempts yet." />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-[#f4f4f7]">
                                        <tr>
                                            {[
                                                'Completion Date',
                                                'Assessment',
                                                'Category',
                                                'Attempt',
                                                'Score',
                                                'Status',
                                                'Time Taken',
                                                'Action',
                                            ].map((h) => (
                                                <th key={h} className="p-4">
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {attempts.data.map((a) => (
                                            <tr key={a.id} className="border-t">
                                                <td className="p-4">
                                                    {new Date(
                                                        a.completed_at,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="p-4 font-semibold">
                                                    {a.assessment}
                                                </td>
                                                <td className="p-4">
                                                    {a.category ?? 'General'}
                                                </td>
                                                <td className="p-4">
                                                    {a.attempt_number}
                                                </td>
                                                <td className="p-4">
                                                    {a.percentage}%
                                                </td>
                                                <td className="p-4 capitalize">
                                                    {a.status.replace('_', ' ')}
                                                </td>
                                                <td className="p-4">
                                                    {a.time_taken_seconds ===
                                                    null
                                                        ? '—'
                                                        : `${Math.floor(a.time_taken_seconds / 60)}m`}
                                                </td>
                                                <td className="p-4">
                                                    <Link
                                                        className="text-[#ad2924]"
                                                        href={`/assessments/attempts/${a.id}/result`}
                                                    >
                                                        View Results
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                    <Pages links={attempts.links} />
                </div>
            </main>
        </>
    );
}
export function Nav() {
    return (
        <nav className="flex gap-4 text-sm font-semibold">
            <Link href="/assessments">My Assessments</Link>
            <Link href="/assessments/history">History</Link>
            <Link href="/assessments/progress">My Progress</Link>
        </nav>
    );
}
export function Empty({ text }: { text: string }) {
    return <p className="p-10 text-center text-[#6f7282]">{text}</p>;
}
export function Pages({ links }: { links: Page['links'] }) {
    return (
        <div className="mt-4 flex flex-wrap gap-2">
            {links.map((l, i) =>
                l.url ? (
                    <Link
                        key={i}
                        href={l.url}
                        className={`rounded border px-3 py-1 ${l.active ? 'bg-[#ad2924] text-white' : ''}`}
                        dangerouslySetInnerHTML={{ __html: l.label }}
                    />
                ) : null,
            )}
        </div>
    );
}
