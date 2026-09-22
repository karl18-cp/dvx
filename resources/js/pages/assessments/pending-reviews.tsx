import { Head, Link } from '@inertiajs/react';
import { Empty, Pages } from './history';
type Row = {
    id: number;
    employee: string;
    employee_id: string;
    assessment: string;
    attempt_number: number;
    submitted_at: string;
    objective_points: number;
    pending_count: number;
};
export default function Pending({
    attempts,
}: {
    attempts: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}) {
    return (
        <>
            <Head title="Pending Reviews" />
            <main className="assessment-admin min-h-full bg-[#f7f7fa] p-4 text-slate-900 lg:p-6">
                <div className="mx-auto max-w-6xl">
                    <h1 className="text-2xl font-bold">Pending Reviews</h1>
                    <div className="mt-6 overflow-hidden rounded-2xl border bg-white">
                        {attempts.data.length === 0 ? (
                            <Empty text="No assessments are pending review." />
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-100">
                                    <tr>
                                        {[
                                            'Employee',
                                            'Assessment',
                                            'Attempt',
                                            'Submitted',
                                            'Objective Points',
                                            'Pending',
                                            'Action',
                                        ].map((h) => (
                                            <th key={h} className="p-4">
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {attempts.data.map((r) => (
                                        <tr key={r.id} className="border-t">
                                            <td className="p-4">
                                                {r.employee} ({r.employee_id})
                                            </td>
                                            <td className="p-4">
                                                {r.assessment}
                                            </td>
                                            <td className="p-4">
                                                {r.attempt_number}
                                            </td>
                                            <td className="p-4">
                                                {new Date(
                                                    r.submitted_at,
                                                ).toLocaleString()}
                                            </td>
                                            <td className="p-4">
                                                {r.objective_points}
                                            </td>
                                            <td className="p-4">
                                                {r.pending_count}
                                            </td>
                                            <td className="p-4">
                                                <Link
                                                    className="text-[#ad2924]"
                                                    href={`/management/assessment-reviews/${r.id}`}
                                                >
                                                    Grade
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                    <Pages links={attempts.links} />
                </div>
            </main>
        </>
    );
}

Pending.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        {
            title: 'Pending Reviews',
            href: '/management/assessment-reviews',
        },
    ],
};
