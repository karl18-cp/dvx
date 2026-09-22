import { Head, Link } from '@inertiajs/react';
import { CampaignScopeBadge } from '@/components/campaign-scope-field';
import type { CampaignOption } from '@/components/campaign-scope-field';

type Criterion = {
    id: number;
    label: string;
    guidance: string | null;
    points_possible: string;
    is_required: boolean;
    is_critical: boolean;
    allows_na: boolean;
    skill: { id: number; name: string } | null;
};
type Category = {
    id: number;
    name: string;
    description: string | null;
    criteria: Criterion[];
};
type Scorecard = {
    id: number;
    name: string;
    description: string | null;
    passing_score: string;
    status: string;
    applies_to_all_campaigns: boolean;
    campaigns: CampaignOption[];
    categories: Category[];
};

export default function ScorecardPreview({
    scorecard,
    total_points,
}: {
    scorecard: Scorecard;
    total_points: number;
}) {
    return (
        <main className="min-h-full bg-[#f7f7fa] p-6 text-[13px]">
            <Head title={`Preview: ${scorecard.name}`} />
            <div className="mx-auto max-w-4xl">
                <header className="mb-4 flex items-center justify-between">
                    <div>
                        <Link
                            href={`/management/qa-scorecards/${scorecard.id}/builder`}
                            className="font-semibold text-red-700"
                        >
                            ← Back to Builder
                        </Link>
                        <h1 className="text-2xl font-bold">
                            Scorecard Preview
                        </h1>
                        <p className="text-slate-500">
                            Management preview only. No Call Evaluation is
                            created.
                        </p>
                    </div>
                    <span className="rounded-full bg-slate-200 px-3 py-1 font-semibold capitalize">
                        {scorecard.status}
                    </span>
                </header>
                <section className="rounded-xl border bg-white p-5">
                    <div className="mb-5 border-b pb-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold">
                                    {scorecard.name}
                                </h2>
                                {scorecard.description && (
                                    <p className="mt-1 text-slate-600">
                                        {scorecard.description}
                                    </p>
                                )}
                            </div>
                            <CampaignScopeBadge
                                all={scorecard.applies_to_all_campaigns}
                                campaigns={scorecard.campaigns}
                            />
                        </div>
                        <div className="mt-3 flex gap-5 font-semibold">
                            <span>Total: {Number(total_points)} points</span>
                            <span>
                                Passing: {Number(scorecard.passing_score)}%
                            </span>
                        </div>
                    </div>
                    <div className="space-y-4">
                        {scorecard.categories.map((category) => (
                            <article
                                key={category.id}
                                className="overflow-hidden rounded-lg border"
                            >
                                <header className="bg-slate-100 px-4 py-3">
                                    <h3 className="text-base font-bold">
                                        {category.name}
                                    </h3>
                                    {category.description && (
                                        <p className="text-xs text-slate-500">
                                            {category.description}
                                        </p>
                                    )}
                                </header>
                                {category.criteria.map((criterion) => (
                                    <div
                                        key={criterion.id}
                                        className="flex items-start justify-between gap-4 border-t px-4 py-3"
                                    >
                                        <div>
                                            <strong>{criterion.label}</strong>
                                            {criterion.guidance && (
                                                <p className="mt-1 text-xs text-slate-500">
                                                    Evaluator guidance:{' '}
                                                    {criterion.guidance}
                                                </p>
                                            )}
                                            <div className="mt-1 flex flex-wrap gap-1 text-[11px]">
                                                {criterion.is_required && (
                                                    <span className="rounded bg-blue-50 px-2 py-0.5 text-blue-800">
                                                        Required
                                                    </span>
                                                )}
                                                {criterion.is_critical && (
                                                    <span className="rounded bg-red-50 px-2 py-0.5 text-red-800">
                                                        Critical
                                                    </span>
                                                )}
                                                {criterion.allows_na && (
                                                    <span className="rounded bg-amber-50 px-2 py-0.5 text-amber-800">
                                                        N/A allowed
                                                    </span>
                                                )}
                                                {criterion.skill && (
                                                    <span className="rounded bg-green-50 px-2 py-0.5 text-green-800">
                                                        Skill:{' '}
                                                        {criterion.skill.name}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <strong className="shrink-0">
                                            {Number(criterion.points_possible)}{' '}
                                            pts
                                        </strong>
                                    </div>
                                ))}
                            </article>
                        ))}
                        {!scorecard.categories.length && (
                            <p className="py-8 text-center text-slate-500">
                                This Scorecard has no Categories yet.
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </main>
    );
}

ScorecardPreview.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'QA Scorecards', href: '/management/qa-scorecards' },
        { title: 'Preview', href: '#' },
    ],
};
