import { Head, Link } from '@inertiajs/react';

type OverviewLink = { label: string; href: string };
type OverviewRow = {
    id: number;
    title: string;
    subtitle: string;
    status: string;
    details: string[];
    links: OverviewLink[];
};
type Props = {
    kind: 'assessments' | 'coaching';
    summary: Record<string, number>;
    search: string;
    rows: {
        data: OverviewRow[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function LearningOverview({
    kind,
    summary,
    search,
    rows,
}: Props) {
    const assessments = kind === 'assessments';
    const title = assessments
        ? 'Training & Assessments Overview'
        : 'Coaching Overview';
    const url = assessments ? '/assessments' : '/my-coaching';
    const links: OverviewLink[] = assessments
        ? [
              { label: 'Manage assessments', href: '/management/assessments' },
              {
                  label: 'Employee assignments',
                  href: '/management/assessment-assignments',
              },
              {
                  label: 'Training library',
                  href: '/management/training-library',
              },
              {
                  label: 'Results & analytics',
                  href: '/management/assessment-results',
              },
              {
                  label: 'Pending reviews',
                  href: '/management/assessment-reviews',
              },
          ]
        : [{ label: 'Manage coaching', href: '/management/coaching' }];

    return (
        <main className="min-h-full bg-[#f7f7fa] p-6 text-[#202231] lg:p-8">
            <Head title={title} />
            <div className="mx-auto max-w-6xl space-y-6">
                <header>
                    <p className="text-xs font-bold tracking-widest text-[#b72822] uppercase">
                        Administrator overview
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">{title}</h1>
                    <p className="mt-2 text-slate-600">
                        {assessments
                            ? 'View all assessments, including drafts and archives, and monitor employee progress across every campaign and team.'
                            : 'View coaching across all employees, coaches, campaigns, and teams.'}
                    </p>
                    <nav
                        aria-label="Management shortcuts"
                        className="mt-4 flex flex-wrap gap-3"
                    >
                        {links.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className="rounded-lg border bg-white px-4 py-2 text-sm font-semibold hover:border-red-300"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                </header>
                <section
                    aria-label="Organization totals"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-3"
                >
                    {Object.entries(summary).map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-2xl border border-[#e2e3e8] bg-white p-5"
                        >
                            <p className="text-sm text-slate-600">{label}</p>
                            <p className="mt-2 text-3xl font-bold">
                                {value.toLocaleString()}
                            </p>
                        </div>
                    ))}
                </section>
                <section
                    aria-label={
                        assessments ? 'All assessments' : 'All coaching records'
                    }
                    className="space-y-4"
                >
                    <form
                        action={url}
                        method="get"
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="min-w-60 flex-1">
                            <label
                                htmlFor="overview-search"
                                className="mb-1 block text-sm font-semibold"
                            >
                                {assessments
                                    ? 'Search assessments'
                                    : 'Search employee, coach, campaign, or team'}
                            </label>
                            <input
                                key={search}
                                id="overview-search"
                                name="search"
                                defaultValue={search}
                                maxLength={100}
                                className="w-full rounded-lg border bg-white px-3 py-2"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg bg-[#ad2924] px-4 py-2 font-semibold text-white"
                        >
                            Search
                        </button>
                        {search && (
                            <Link
                                href={url}
                                className="px-3 py-2 text-sm font-semibold"
                            >
                                Clear
                            </Link>
                        )}
                    </form>
                    <p className="text-sm text-slate-600">
                        {rows.total.toLocaleString()}{' '}
                        {assessments ? 'assessments' : 'coaching records'}
                        {search
                            ? ' matching your search'
                            : ' across the organization'}
                        . Summary totals include all records.
                    </p>
                    {rows.data.map((row) => (
                        <article
                            key={row.id}
                            className="rounded-2xl border border-[#e2e3e8] bg-white p-5 shadow-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-bold">
                                        {row.title}
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {row.subtitle}
                                    </p>
                                </div>
                                <span className="rounded-full bg-[#f7e9e8] px-3 py-1 text-xs font-semibold text-[#9e251f] capitalize">
                                    {row.status.replaceAll('_', ' ')}
                                </span>
                            </div>
                            <ul className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
                                {row.details.map((detail) => (
                                    <li key={detail}>{detail}</li>
                                ))}
                            </ul>
                            <div className="mt-4 flex flex-wrap gap-4">
                                {row.links.map((link) => (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        className="text-sm font-semibold text-[#ad2924] underline-offset-4 hover:underline"
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </article>
                    ))}
                    {!rows.data.length && (
                        <div className="rounded-2xl border bg-white p-10 text-center text-slate-500">
                            {search
                                ? 'No records match your search.'
                                : assessments
                                  ? 'No assessments have been created yet.'
                                  : 'No coaching records have been created yet.'}
                        </div>
                    )}
                    <nav
                        aria-label="Pagination"
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        {rows.prev_page_url ? (
                            <Link
                                href={rows.prev_page_url}
                                className="rounded-lg border bg-white px-4 py-2"
                            >
                                Previous
                            </Link>
                        ) : (
                            <span />
                        )}
                        <span>
                            Page {rows.current_page} of {rows.last_page}
                        </span>
                        {rows.next_page_url ? (
                            <Link
                                href={rows.next_page_url}
                                className="rounded-lg border bg-white px-4 py-2"
                            >
                                Next
                            </Link>
                        ) : (
                            <span />
                        )}
                    </nav>
                </section>
            </div>
        </main>
    );
}
