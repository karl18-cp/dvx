// These are calendar dates, not instants: preserve the day stored by Laravel.
export function coachingDateValue(value?: string | null): string {
    const day = value?.match(/^\d{4}-\d{2}-\d{2}(?=T|$)/)?.[0];

    if (!day) {
        return '';
    }

    const date = new Date(`${day}T00:00:00Z`);

    return Number.isNaN(date.getTime()) ||
        date.toISOString().slice(0, 10) !== day
        ? ''
        : day;
}

export function formatCoachingDate(value?: string | null): string {
    const day = coachingDateValue(value);

    if (!day) {
        return 'None';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${day}T00:00:00Z`));
}

export function coachingToday(now = new Date()): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(now);
}

export function coachingFollowUp(
    value?: string | null,
    status?: string,
    now = new Date(),
): string {
    if (status === 'completed') {
        return 'Completed';
    }

    const day = coachingDateValue(value);

    if (!day) {
        return 'None';
    }

    const today = coachingToday(now);
    const label =
        day < today ? 'Overdue' : day === today ? 'Due Today' : 'Upcoming';

    return `${formatCoachingDate(day)} · ${label}`;
}
