const styles: Record<string, string> = {
    assigned: 'bg-slate-100 text-slate-800',
    draft: 'bg-slate-100 text-slate-800',
    published: 'bg-blue-100 text-blue-800',
    archived: 'bg-gray-200 text-gray-700',
    in_progress: 'bg-amber-100 text-amber-900',
    pending_review: 'bg-violet-100 text-violet-900',
    passed: 'bg-green-100 text-green-900',
    failed: 'bg-red-100 text-red-900',
};

export function AssessmentStatusBadge({ status }: { status: string }) {
    const label = status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${styles[status] ?? styles.assigned}`}
        >
            {label}
        </span>
    );
}
