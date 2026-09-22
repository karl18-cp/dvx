import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    MenuItem,
    TextField,
} from '@mui/material';
import { CalendarDays, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AssessmentStatusBadge } from '@/components/assessment-status-badge';
import {
    AssignmentFilters,
    AssignmentScheduleDialog,
} from '@/components/assignment-controls';
import { DateTimeField } from '@/components/date-time-field';
type Ref = {
    id: number;
    title?: string;
    name?: string;
    available_at?: string;
    due_at?: string;
    members_count?: number;
    campaign_id?: number;
};
type Employee = { id: number; name: string; username: string };
type Row = {
    id: number;
    assessment: { title: string; available_at?: string; due_at?: string };
    campaign_name?: string;
    employee: Employee;
    team?: { name: string };
    assigned_at: string;
    available_from?: string;
    due_date?: string;
    status: string;
    attempts_count: number;
    training_progress_count: number;
};

const localDateTime = (value?: string) => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(value ? new Date(value) : new Date());
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
};
export default function Assignments({
    assignments,
    assessments,
    teams,
    campaigns,
    assessmentFilters,
    filters,
}: {
    assignments: {
        data: Row[];
        total: number;
        prev_page_url?: string;
        next_page_url?: string;
    };
    assessments: Ref[];
    teams: Ref[];
    campaigns: Ref[];
    assessmentFilters: Ref[];
    filters: Record<string, string>;
}) {
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const [open, setOpen] = useState(false),
        [editing, setEditing] = useState<Row | null>(null),
        [deleting, setDeleting] = useState<Row | null>(null),
        [deleteProcessing, setDeleteProcessing] = useState(false),
        [selected, setSelected] = useState<number[]>([]),
        [deadlineOpen, setDeadlineOpen] = useState(false),
        [newDeadline, setNewDeadline] = useState(''),
        [results, setResults] = useState<Employee[]>([]),
        [search, setSearch] = useState(''),
        [recipientCount, setRecipientCount] = useState<number | null>(null);
    const form = useForm({
        assessment_id: '',
        employee_ids: [] as number[],
        team_ids: [] as number[],
        all_employees: false,
        available_from: '',
        due_date: '',
    });
    const editForm = useForm({
        available_from: '',
        due_date: '',
    });
    const openEdit = (row: Row) => {
        setEditing(row);
        editForm.setData({
            available_from: row.available_from
                ? localDateTime(row.available_from)
                : '',
            due_date: row.due_date ? localDateTime(row.due_date) : '',
        });
        editForm.clearErrors();
    };
    const deleteAssignment = () => {
        if (!deleting) {
            return;
        }

        setDeleteProcessing(true);
        router.delete(`/management/assessment-assignments/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onFinish: () => setDeleteProcessing(false),
        });
    };
    const extendDeadlines = () =>
        router.patch(
            '/management/assessment-assignments/deadlines/extend',
            { assignment_ids: selected, due_date: newDeadline },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeadlineOpen(false);
                    setSelected([]);
                },
            },
        );
    const find = () =>
        fetch(
            `/management/assessment-employees?search=${encodeURIComponent(search)}`,
            { headers: { Accept: 'application/json' } },
        )
            .then((r) => r.json())
            .then(setResults);
    const toggle = (key: 'employee_ids' | 'team_ids', id: number) =>
        form.setData(
            key,
            form.data[key].includes(id)
                ? form.data[key].filter((x) => x !== id)
                : [...form.data[key], id],
        );
    const calculateRecipients = () => {
        const params = new URLSearchParams({
            all_employees: form.data.all_employees ? '1' : '0',
        });
        form.data.employee_ids.forEach((id) =>
            params.append('employee_ids[]', String(id)),
        );
        form.data.team_ids.forEach((id) =>
            params.append('team_ids[]', String(id)),
        );
        fetch(`/management/assessment-recipient-count?${params}`, {
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json())
            .then((data: { count: number }) => setRecipientCount(data.count));
    };

    return (
        <>
            <Head title="Assessment Assignments" />
            <main className="assessment-admin min-h-full bg-[#f7f7fa] p-4 lg:p-6">
                <div className="mx-auto max-w-7xl space-y-5">
                    <header className="flex justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">
                                Assessment Assignments
                            </h1>
                            <p className="text-[#777b8e]">
                                Assign published assessments to unique
                                employees.
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="small"
                                variant="outlined"
                                startIcon={<CalendarDays size={16} />}
                                onClick={() => setScheduleOpen(true)}
                            >
                                Schedule
                            </Button>
                            <Button
                                variant="contained"
                                startIcon={<Plus />}
                                onClick={() => setOpen(true)}
                            >
                                Assign
                            </Button>
                        </div>
                    </header>
                    <section className="rounded-2xl border bg-white p-4">
                        <AssignmentFilters
                            key={JSON.stringify(filters)}
                            filters={filters}
                            assessments={assessmentFilters}
                            campaigns={campaigns}
                            teams={teams}
                        />
                        <div className="overflow-x-auto">
                            <Button
                                disabled={!selected.length}
                                onClick={() => setDeadlineOpen(true)}
                            >
                                Extend Deadline ({selected.length})
                            </Button>
                            <table className="w-full min-w-[1050px] text-left text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-3">Select</th>
                                        <th className="p-3">Assessment</th>
                                        <th>Employee</th>
                                        <th>Team</th>
                                        <th>Campaign</th>
                                        <th>Assigned</th>
                                        <th>Available</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {assignments.data.map((x) => (
                                        <tr key={x.id} className="border-b">
                                            <td className="p-3">
                                                <Checkbox
                                                    disabled={[
                                                        'passed',
                                                        'failed',
                                                        'pending_review',
                                                    ].includes(x.status)}
                                                    checked={selected.includes(
                                                        x.id,
                                                    )}
                                                    onChange={() =>
                                                        setSelected(
                                                            (current) =>
                                                                current.includes(
                                                                    x.id,
                                                                )
                                                                    ? current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              x.id,
                                                                      )
                                                                    : [
                                                                          ...current,
                                                                          x.id,
                                                                      ],
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="p-3 font-semibold">
                                                {x.assessment.title}
                                            </td>
                                            <td>
                                                {x.employee.name} (
                                                {x.employee.username})
                                            </td>
                                            <td>
                                                {x.team?.name || 'Individual'}
                                            </td>
                                            <td>{x.campaign_name || '—'}</td>
                                            <td>
                                                {displayDateTime(x.assigned_at)}
                                            </td>
                                            <td>
                                                {displayDateTime(
                                                    x.available_from ||
                                                        x.assessment
                                                            .available_at,
                                                )}
                                            </td>
                                            <td>
                                                {displayDateTime(
                                                    x.due_date ||
                                                        x.assessment.due_at,
                                                )}
                                            </td>
                                            <td>
                                                <AssessmentStatusBadge
                                                    status={x.status}
                                                />
                                            </td>
                                            <td>
                                                <div className="flex gap-1">
                                                    <Button
                                                        size="small"
                                                        startIcon={
                                                            <Pencil size={15} />
                                                        }
                                                        onClick={() =>
                                                            openEdit(x)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <span
                                                        title={
                                                            x.attempts_count >
                                                                0 ||
                                                            x.training_progress_count >
                                                                0
                                                                ? 'Cannot delete an assignment that already has employee activity. Historical assessment records must be preserved.'
                                                                : undefined
                                                        }
                                                    >
                                                        <Button
                                                            size="small"
                                                            color="error"
                                                            disabled={
                                                                x.attempts_count >
                                                                    0 ||
                                                                x.training_progress_count >
                                                                    0
                                                            }
                                                            startIcon={
                                                                <Trash2
                                                                    size={15}
                                                                />
                                                            }
                                                            onClick={() =>
                                                                setDeleting(x)
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!assignments.data.length && (
                                        <tr>
                                            <td
                                                colSpan={10}
                                                className="p-8 text-center text-gray-500"
                                            >
                                                No assignments match these
                                                filters.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-4 flex justify-between">
                            <span>{assignments.total} assignment(s)</span>
                            <div>
                                <Button
                                    disabled={!assignments.prev_page_url}
                                    onClick={() =>
                                        assignments.prev_page_url &&
                                        router.get(assignments.prev_page_url)
                                    }
                                >
                                    Previous
                                </Button>
                                <Button
                                    disabled={!assignments.next_page_url}
                                    onClick={() =>
                                        assignments.next_page_url &&
                                        router.get(assignments.next_page_url)
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
            {scheduleOpen && (
                <AssignmentScheduleDialog
                    filters={filters}
                    assessments={assessments}
                    campaigns={campaigns}
                    teams={teams}
                    onClose={() => setScheduleOpen(false)}
                    onAssign={(schedule) => {
                        form.setData({
                            assessment_id: schedule.assessment_id,
                            available_from: schedule.available_from,
                            due_date: schedule.due_date,
                            employee_ids: [],
                            team_ids: schedule.team_id
                                ? [Number(schedule.team_id)]
                                : schedule.campaign_id
                                  ? teams
                                        .filter(
                                            (team) =>
                                                String(team.campaign_id) ===
                                                schedule.campaign_id,
                                        )
                                        .map((team) => team.id)
                                  : [],
                            all_employees: false,
                        });
                        setRecipientCount(null);
                        form.clearErrors();
                        setScheduleOpen(false);
                        setOpen(true);
                    }}
                />
            )}
            <Dialog open={deadlineOpen} onClose={() => setDeadlineOpen(false)}>
                <DialogTitle>Extend Deadline</DialogTitle>
                <DialogContent>
                    <DateTimeField
                        className="mt-2"
                        type="datetime-local"
                        label="New due date"
                        value={newDeadline}
                        onChange={(e) => setNewDeadline(e.target.value)}
                        slotProps={{ inputLabel: { shrink: true } }}
                        helperText="Asia/Manila business time"
                    />
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDeadlineOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={!newDeadline}
                        onClick={extendDeadlines}
                    >
                        Extend
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                fullWidth
                maxWidth="md"
            >
                <DialogTitle>Assign Published Assessment</DialogTitle>
                <DialogContent>
                    <div className="mt-2 grid gap-4">
                        <TextField
                            select
                            label="Assessment"
                            value={form.data.assessment_id}
                            onChange={(e) =>
                                form.setData('assessment_id', e.target.value)
                            }
                        >
                            {assessments.map((a) => (
                                <MenuItem key={a.id} value={a.id}>
                                    {a.title}
                                </MenuItem>
                            ))}
                        </TextField>
                        <FormControlLabel
                            control={
                                <Checkbox
                                    checked={form.data.all_employees}
                                    onChange={(e) =>
                                        form.setData(
                                            'all_employees',
                                            e.target.checked,
                                        )
                                    }
                                />
                            }
                            label="All eligible employees"
                        />
                        <b>Teams</b>
                        <div>
                            {teams.map((t) => (
                                <FormControlLabel
                                    key={t.id}
                                    control={
                                        <Checkbox
                                            checked={form.data.team_ids.includes(
                                                t.id,
                                            )}
                                            onChange={() =>
                                                toggle('team_ids', t.id)
                                            }
                                        />
                                    }
                                    label={`${t.name} (${t.members_count})`}
                                />
                            ))}
                        </div>
                        <b>Individual employees</b>
                        <div className="flex gap-2">
                            <TextField
                                fullWidth
                                size="small"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                label="Search name or employee ID"
                            />
                            <Button onClick={find}>Search</Button>
                        </div>
                        <div>
                            {results.map((e) => (
                                <FormControlLabel
                                    key={e.id}
                                    control={
                                        <Checkbox
                                            checked={form.data.employee_ids.includes(
                                                e.id,
                                            )}
                                            onChange={() =>
                                                toggle('employee_ids', e.id)
                                            }
                                        />
                                    }
                                    label={`${e.name} (${e.username})`}
                                />
                            ))}
                        </div>
                        <div className="flex items-center gap-3">
                            <Button onClick={calculateRecipients}>
                                Calculate unique total
                            </Button>
                            <p className="font-semibold">
                                Total unique employees receiving assessment:{' '}
                                {recipientCount ?? 'Not calculated'}
                            </p>
                        </div>
                        <p className="text-sm text-[#777b8e]">
                            Direct selections: {form.data.employee_ids.length};
                            teams: {form.data.team_ids.length}
                            {form.data.all_employees
                                ? ' plus all eligible employees'
                                : ''}
                            .
                        </p>
                        <DateField
                            label="Available from override"
                            value={form.data.available_from}
                            onChange={(value) =>
                                form.setData('available_from', value)
                            }
                        />
                        <DateField
                            label="Due date override"
                            value={form.data.due_date}
                            onChange={(value) =>
                                form.setData('due_date', value)
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={!form.data.assessment_id || form.processing}
                        onClick={() =>
                            form.post('/management/assessment-assignments', {
                                onSuccess: () => setOpen(false),
                            })
                        }
                    >
                        {form.processing ? 'Assigning...' : 'Assign'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle>Edit Assignment</DialogTitle>
                <DialogContent>
                    {editing && (
                        <div className="mt-2 grid gap-4">
                            <div className="rounded-xl bg-[#f7f7fa] p-4 text-sm">
                                <p>
                                    <b>Assessment:</b>{' '}
                                    {editing.assessment.title}
                                </p>
                                <p>
                                    <b>Employee:</b> {editing.employee.name}
                                </p>
                                <p>
                                    <b>Team:</b>{' '}
                                    {editing.team?.name || 'Individual'}
                                </p>
                                <p className="capitalize">
                                    <b>Status:</b>{' '}
                                    {editing.status.replaceAll('_', ' ')}
                                </p>
                            </div>
                            <DateField
                                label="Available From"
                                value={editForm.data.available_from}
                                onChange={(value) =>
                                    editForm.setData('available_from', value)
                                }
                                error={editForm.errors.available_from}
                            />
                            <DateField
                                label="Due Date"
                                value={editForm.data.due_date}
                                onChange={(value) =>
                                    editForm.setData('due_date', value)
                                }
                                error={editForm.errors.due_date}
                            />
                        </div>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setEditing(null)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={editForm.processing}
                        onClick={() =>
                            editing &&
                            editForm.put(
                                `/management/assessment-assignments/${editing.id}`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setEditing(null),
                                },
                            )
                        }
                    >
                        {editForm.processing ? 'Saving...' : 'Save Changes'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={Boolean(deleting)}
                onClose={() => !deleteProcessing && setDeleting(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle>Delete Assignment?</DialogTitle>
                <DialogContent>
                    <p className="mt-2">
                        Remove <b>{deleting?.assessment.title}</b> from{' '}
                        <b>{deleting?.employee.name}</b>?
                    </p>
                    <p className="mt-2 text-sm text-[#777b8e]">
                        This assignment has not been started. This action cannot
                        be undone.
                    </p>
                </DialogContent>
                <DialogActions>
                    <Button
                        disabled={deleteProcessing}
                        onClick={() => setDeleting(null)}
                    >
                        Cancel
                    </Button>
                    <Button
                        color="error"
                        variant="contained"
                        disabled={deleteProcessing}
                        onClick={deleteAssignment}
                    >
                        {deleteProcessing ? 'Deleting...' : 'Delete Assignment'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

function displayDateTime(value?: string) {
    return value
        ? new Date(value).toLocaleString('en-US', {
              timeZone: 'Asia/Manila',
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

function DateField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="flex items-start gap-2">
            <DateTimeField
                fullWidth
                type="datetime-local"
                label={label}
                value={value}
                error={Boolean(error)}
                helperText={error}
                onChange={(event) => onChange(event.target.value)}
                slotProps={{ inputLabel: { shrink: true } }}
            />
            <Button
                size="small"
                variant="text"
                onClick={() => onChange(localDateTime())}
            >
                Today
            </Button>
        </div>
    );
}
Assignments.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        {
            title: 'Assessment Assignments',
            href: '/management/assessment-assignments',
        },
    ],
};
