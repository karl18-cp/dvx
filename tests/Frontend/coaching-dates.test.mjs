import assert from 'node:assert/strict';
import test from 'node:test';
import {
    coachingDateValue,
    coachingFollowUp,
    coachingToday,
    formatCoachingDate,
} from '../../resources/js/lib/coaching-dates.ts';

test('formats Laravel date timestamps without artificial midnight times', () => {
    assert.equal(
        formatCoachingDate('2026-09-09T00:00:00.000000Z'),
        'Sep 9, 2026',
    );
    assert.equal(formatCoachingDate('2026-08-28'), 'Aug 28, 2026');
    assert.equal(
        coachingDateValue('2026-09-09T00:00:00.000000Z'),
        '2026-09-09',
    );
});

test('handles missing and invalid calendar dates', () => {
    for (const value of [
        null,
        undefined,
        '',
        'invalid',
        '2026-02-30',
        '2026-13-01',
    ]) {
        assert.equal(formatCoachingDate(value), 'None');
        assert.equal(coachingDateValue(value), '');
    }
});

test('follow-up compares calendar days in Manila, including UTC midnight boundary', () => {
    const now = new Date('2026-09-09T17:00:00Z');
    assert.equal(coachingToday(now), '2026-09-10');
    assert.equal(
        coachingFollowUp('2026-09-10T00:00:00.000000Z', 'open', now),
        'Sep 10, 2026 · Due Today',
    );
    assert.equal(
        coachingFollowUp('2026-09-09T00:00:00.000000Z', 'open', now),
        'Sep 9, 2026 · Overdue',
    );
    assert.equal(
        coachingFollowUp('2026-09-11', 'open', now),
        'Sep 11, 2026 · Upcoming',
    );
    assert.equal(coachingFollowUp(null, 'open', now), 'None');
    assert.equal(coachingFollowUp('2026-09-09', 'completed', now), 'Completed');
});
