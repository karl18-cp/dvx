import { router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    TextField,
} from '@mui/material';
import { useState } from 'react';
import { DateTimeField } from '@/components/date-time-field';

export type AssignmentOption = {
    id: number;
    name?: string;
    title?: string;
    campaign_id?: number;
    available_at?: string;
    due_at?: string;
};

export function AssignmentFilters({
    filters,
    assessments,
    campaigns,
    teams,
}: {
    filters: Record<string, string>;
    assessments: AssignmentOption[];
    campaigns: AssignmentOption[];
    teams: AssignmentOption[];
}) {
    const form = useForm(
        Object.fromEntries(
            [
                'assessment_id',
                'campaign_id',
                'team_id',
                'employee',
                'status',
                'assigned_from',
                'assigned_to',
                'due_from',
                'due_to',
            ].map((key) => [key, String(filters[key] || '')]),
        ),
    );
    const choices = [
        { key: 'assessment_id', label: 'Assessment', options: assessments },
        { key: 'campaign_id', label: 'Campaign', options: campaigns },
        {
            key: 'team_id',
            label: 'Team',
            options: teams.filter(
                (team) =>
                    !form.data.campaign_id ||
                    String(team.campaign_id) === form.data.campaign_id,
            ),
        },
    ];

    return (
        <form
            className="mb-4 space-y-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.get('/management/assessment-assignments', {
                    preserveScroll: true,
                    preserveState: false,
                });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {choices.map(({ key, label, options }) => (
                    <TextField
                        key={key}
                        select
                        size="small"
                        label={label}
                        value={form.data[key]}
                        onChange={(event) => {
                            form.setData({
                                ...form.data,
                                [key]: event.target.value,
                                ...(key === 'campaign_id'
                                    ? { team_id: '' }
                                    : {}),
                            });
                        }}
                    >
                        <MenuItem value="">All {label.toLowerCase()}s</MenuItem>
                        {options.map((option) => (
                            <MenuItem key={option.id} value={String(option.id)}>
                                {option.title || option.name}
                            </MenuItem>
                        ))}
                    </TextField>
                ))}
                <TextField
                    size="small"
                    label="Employee name or ID"
                    value={form.data.employee}
                    onChange={(event) =>
                        form.setData('employee', event.target.value)
                    }
                />
                <TextField
                    select
                    size="small"
                    label="Status"
                    value={form.data.status}
                    onChange={(event) =>
                        form.setData('status', event.target.value)
                    }
                >
                    <MenuItem value="">All statuses</MenuItem>
                    {[
                        'assigned',
                        'in_progress',
                        'pending_review',
                        'passed',
                        'failed',
                    ].map((status) => (
                        <MenuItem key={status} value={status}>
                            {status.replaceAll('_', ' ')}
                        </MenuItem>
                    ))}
                </TextField>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['assigned_from', 'Assigned from'],
                    ['assigned_to', 'Assigned through'],
                    ['due_from', 'Due from'],
                    ['due_to', 'Due through'],
                ].map(([key, label]) => (
                    <DateTimeField
                        key={key}
                        type="date"
                        size="small"
                        label={label}
                        value={form.data[key]}
                        onChange={(event) =>
                            form.setData(key, event.target.value)
                        }
                        error={Boolean(form.errors[key])}
                        helperText={form.errors[key]}
                    />
                ))}
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Button
                    type="submit"
                    variant="contained"
                    disabled={form.processing}
                >
                    Apply filters
                </Button>
                <Button
                    onClick={() =>
                        router.get('/management/assessment-assignments')
                    }
                >
                    Clear filters
                </Button>
                <span className="text-xs text-gray-500">
                    Dates use Asia/Manila. Campaign and team reflect the
                    assignment records.
                </span>
            </div>
        </form>
    );
}

const displayTime = (value: string) =>
    value
        ? new Date(value).toLocaleString('en-US', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : 'Not set';
const wallTime = (value?: string) => {
    if (!value) {
        return '';
    }

    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(value));

    return parts.replace(' ', 'T');
};

export function AssignmentScheduleDialog({
    onClose,
    onAssign,
    assessments,
    campaigns,
    teams,
    filters,
}: {
    onClose: () => void;
    onAssign: (data: {
        assessment_id: string;
        campaign_id: string;
        team_id: string;
        available_from: string;
        due_date: string;
    }) => void;
    assessments: AssignmentOption[];
    campaigns: AssignmentOption[];
    teams: AssignmentOption[];
    filters: Record<string, string>;
}) {
    const initial = assessments.find(
        (item) => String(item.id) === String(filters.assessment_id),
    );
    const form = useForm({
        assessment_id: initial ? String(initial.id) : '',
        campaign_id: String(filters.campaign_id || ''),
        team_id: String(filters.team_id || ''),
        available_from: wallTime(initial?.available_at),
        due_date: wallTime(initial?.due_at),
        review_token: '',
    });
    const [review, setReview] = useState<{
        count: number;
        skipped: number;
    } | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const change = (key: keyof typeof form.data, value: string) => {
        setReview(null);
        setError('');
        form.clearErrors();
        const assessment =
            key === 'assessment_id'
                ? assessments.find((item) => String(item.id) === value)
                : undefined;
        form.setData({
            ...form.data,
            [key]: value,
            review_token: '',
            ...(key === 'campaign_id' ? { team_id: '' } : {}),
            ...(key === 'assessment_id'
                ? {
                      available_from: wallTime(assessment?.available_at),
                      due_date: wallTime(assessment?.due_at),
                  }
                : {}),
        });
    };
    const preview = async () => {
        setLoading(true);
        setError('');
        form.clearErrors();

        try {
            const response = await fetch(
                '/management/assessment-assignment-schedule/preview?' +
                    new URLSearchParams(form.data),
                { headers: { Accept: 'application/json' } },
            );
            const result = await response.json();

            if (!response.ok) {
                setError(
                    Object.values(result.errors || {})
                        .flat()
                        .join(' ') || 'Unable to review this schedule.',
                );

                return;
            }

            setReview({ count: result.count, skipped: result.skipped });
            form.setData('review_token', result.review_token);
        } catch {
            setError('Unable to load the review. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog
            open
            onClose={() => !form.processing && !loading && onClose()}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle>Schedule an assessment</DialogTitle>
            <DialogContent>
                <div className="grid gap-4 pt-2">
                    <Alert severity="info">
                        Set availability and a deadline for existing, unstarted
                        assignments. Times are in Asia/Manila. Started and
                        completed assessments will not be changed.
                    </Alert>
                    {error && <Alert severity="error">{error}</Alert>}
                    {Object.values(form.errors).length > 0 && (
                        <Alert severity="error">
                            {Object.values(form.errors).join(' ')}
                        </Alert>
                    )}
                    <fieldset
                        disabled={loading || form.processing}
                        className="grid gap-4 border-0 p-0"
                    >
                        <h3 className="font-semibold">
                            1. Choose the assessment and group
                        </h3>
                        <TextField
                            select
                            required
                            label="Assessment"
                            value={form.data.assessment_id}
                            onChange={(event) =>
                                change('assessment_id', event.target.value)
                            }
                        >
                            <MenuItem value="">
                                Choose a published assessment
                            </MenuItem>
                            {assessments.map((item) => (
                                <MenuItem key={item.id} value={String(item.id)}>
                                    {item.title}
                                </MenuItem>
                            ))}
                        </TextField>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <TextField
                                select
                                label="Campaign"
                                value={form.data.campaign_id}
                                onChange={(event) =>
                                    change('campaign_id', event.target.value)
                                }
                            >
                                <MenuItem value="">All campaigns</MenuItem>
                                {campaigns.map((item) => (
                                    <MenuItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                select
                                label="Team"
                                value={form.data.team_id}
                                onChange={(event) =>
                                    change('team_id', event.target.value)
                                }
                            >
                                <MenuItem value="">All teams</MenuItem>
                                {teams
                                    .filter(
                                        (item) =>
                                            !form.data.campaign_id ||
                                            String(item.campaign_id) ===
                                                form.data.campaign_id,
                                    )
                                    .map((item) => (
                                        <MenuItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            {item.name}
                                        </MenuItem>
                                    ))}
                            </TextField>
                        </div>
                        <h3 className="font-semibold">2. Set the schedule</h3>
                        <DateTimeField
                            required
                            type="datetime-local"
                            label="Available from"
                            value={form.data.available_from}
                            onChange={(event) =>
                                change('available_from', event.target.value)
                            }
                        />
                        <DateTimeField
                            required
                            type="datetime-local"
                            label="Deadline"
                            value={form.data.due_date}
                            onChange={(event) =>
                                change('due_date', event.target.value)
                            }
                        />
                        <p className="text-xs text-gray-500">
                            This replaces the availability and deadline for the
                            reviewed assignments. Other list filters do not
                            affect this schedule.
                        </p>
                    </fieldset>
                    {review && (
                        <Alert severity={review.count ? 'success' : 'warning'}>
                            <b>
                                {review.count} assignment(s) will be scheduled.
                            </b>
                            <p>
                                {review.skipped} started or completed
                                assignment(s) will be skipped.
                            </p>
                            <p>
                                {displayTime(form.data.available_from)} to{' '}
                                {displayTime(form.data.due_date)} (Asia/Manila)
                            </p>
                            {!review.count && (
                                <Button onClick={() => onAssign(form.data)}>
                                    Assign employees first
                                </Button>
                            )}
                        </Alert>
                    )}
                </div>
            </DialogContent>
            <DialogActions>
                <Button disabled={loading || form.processing} onClick={onClose}>
                    Cancel
                </Button>
                {review && (
                    <Button
                        disabled={form.processing}
                        onClick={() => setReview(null)}
                    >
                        Back
                    </Button>
                )}
                {!review ? (
                    <Button
                        variant="contained"
                        disabled={
                            loading ||
                            !form.data.assessment_id ||
                            !form.data.available_from ||
                            !form.data.due_date
                        }
                        onClick={preview}
                    >
                        {loading ? 'Reviewing...' : 'Review schedule'}
                    </Button>
                ) : (
                    <Button
                        variant="contained"
                        disabled={!review.count || form.processing}
                        onClick={() =>
                            form.patch(
                                '/management/assessment-assignment-schedule',
                                {
                                    preserveScroll: true,
                                    onSuccess: onClose,
                                    onError: () => setReview(null),
                                },
                            )
                        }
                    >
                        {form.processing ? 'Saving...' : 'Confirm schedule'}
                    </Button>
                )}
            </DialogActions>
        </Dialog>
    );
}
