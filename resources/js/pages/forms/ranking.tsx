import { Head } from '@inertiajs/react';
import { Trophy } from 'lucide-react';
import { panel } from './shared';

export default function Ranking({
    leaders,
}: {
    leaders: {
        id: number;
        name: string;
        username: string | null;
        points: number;
        submissions: number;
    }[];
}) {
    return (
        <main className="min-h-full p-4 text-[#17202d] lg:p-6">
            <Head title="Ranking" />
            <div className="mx-auto max-w-5xl space-y-5">
                <header>
                    <h1 className="flex items-center gap-3 text-3xl font-extrabold">
                        <Trophy className="text-red-800" />
                        Ranking
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        All-time form points. Each response to a ranking-enabled
                        form earns 1 point.
                    </p>
                </header>
                <section className={panel}>
                    <div className="max-h-[600px] overflow-auto">
                        <table className="w-full min-w-[480px] text-left text-sm">
                            <thead className="sticky top-0 bg-[#fff5f5] text-red-900">
                                <tr>
                                    <th className="p-4">Rank</th>
                                    <th className="p-4">Employee</th>
                                    <th className="p-4">ID</th>
                                    <th className="p-4">Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                {leaders.map((leader) => (
                                    <tr
                                        key={leader.id}
                                        className="border-t border-slate-100"
                                    >
                                        <td className="p-4 font-bold">
                                            {leaders.findIndex(
                                                (row) =>
                                                    row.points ===
                                                    leader.points,
                                            ) + 1}
                                        </td>
                                        <td className="p-4">{leader.name}</td>
                                        <td className="p-4">
                                            {leader.username}
                                        </td>
                                        <td className="p-4 font-bold text-red-700">
                                            {leader.points}
                                        </td>
                                    </tr>
                                ))}
                                {!leaders.length && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-10 text-center text-slate-500"
                                        >
                                            No ranking points yet. Points appear
                                            when employees submit a
                                            ranking-enabled form.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    );
}
