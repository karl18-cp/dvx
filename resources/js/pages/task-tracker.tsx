import { Head, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    LinearProgress,
    MenuItem,
    TextField,
} from '@mui/material';
import { ChevronRight, ListChecks, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';
import '../../css/task-tracker.css';

type Person = {
    id: number;
    name: string;
    username: string | null;
    role: string;
};
type Assignment = {
    id: number;
    user_id: number | null;
    assignee_name: string;
    assignee_role: string;
    completed_items: string[];
    status: string;
    version: number;
    reviewer_name: string | null;
    review_notes: string | null;
};
type Task = {
    id: number;
    title: string;
    description: string | null;
    creator_name: string;
    created_at: string;
    checklist: { id: string; label: string }[];
    assignments: Assignment[];
};
type Props = {
    tasks: {
        data: Task[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    assignees: Person[];
    isAdmin: boolean;
    currentUserId: number;
    filters: { search: string; status: string; scope: string };
    statusMessage?: string;
};
const labels: Record<string, string> = {
    open: 'Open',
    in_progress: 'In progress',
    pending_review: 'Pending approval',
    approved_done: 'Approved Done',
};
const colors: Record<string, string> = {
    open: 'bg-slate-100 text-slate-600',
    in_progress: 'bg-blue-50 text-blue-700',
    pending_review: 'bg-amber-50 text-amber-800',
    approved_done: 'bg-emerald-100 text-emerald-800',
};
const roleLabel = (role: string) =>
    role === 'admin' ? 'Admin' : 'Team Leader';
const dateLabel = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export default function TaskTracker({
    tasks,
    assignees,
    isAdmin,
    currentUserId,
    filters,
    statusMessage,
}: Props) {
    const confirm = useConfirmation();
    const form = useForm({
        request_id: crypto.randomUUID(),
        title: '',
        description: '',
        assignee_ids: [] as number[],
        checklist: '',
    });
    const [personSearch, setPersonSearch] = useState('');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);
    const [scope, setScope] = useState(filters.scope);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [review, setReview] = useState<{
        task: Task;
        assignment: Assignment;
        action: 'approve' | 'return';
    } | null>(null);
    const [notes, setNotes] = useState('');

    const create = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            checklist: data.checklist
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter(Boolean),
        }));
        form.post('/task-tracker', {
            preserveScroll: true,
            onSuccess: () => {
                form.setData({
                    request_id: crypto.randomUUID(),
                    title: '',
                    description: '',
                    assignee_ids: [],
                    checklist: '',
                });
                setPersonSearch('');
            },
        });
    };
    const update = (
        task: Task,
        assignment: Assignment,
        action: string,
        completed?: string[],
    ) => {
        setBusy(true);
        setError('');
        router.patch(
            `/task-tracker/${task.id}/assignments/${assignment.id}`,
            {
                action,
                version: assignment.version,
                ...(completed ? { completed_items: completed } : {}),
                ...(review ? { review_notes: notes } : {}),
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Unable to update the task.',
                    ),
                onSuccess: () => setReview(null),
                onFinish: () => setBusy(false),
            },
        );
    };
    const remove = async (task: Task) => {
        if (
            !(await confirm(
                `Delete “${task.title}” and remove it from everyone’s task tracker?`,
            ))
        ) {
            return;
        }

        setBusy(true);
        setError('');
        router.delete(`/task-tracker/${task.id}`, {
            preserveScroll: true,
            onError: (errors) =>
                setError(Object.values(errors)[0] ?? 'Unable to delete task.'),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <>
            <Head title="Task Tracker" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-5">
                    <header className="flex items-center justify-between gap-4">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-red-800 uppercase">
                                Team coordination
                            </p>
                            <h1 className="flex items-center gap-3 text-3xl font-bold tracking-tight text-slate-900">
                                <ListChecks className="text-red-800" />
                                Task Tracker
                            </h1>
                            <p className="mt-2 text-sm text-slate-500">
                                {isAdmin
                                    ? 'Assign work to admins and team leaders, track progress, and review completed tasks.'
                                    : 'Complete your assigned checklists and submit your work for admin approval.'}
                            </p>
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                            <Button
                                href="/eod-reports"
                                variant="outlined"
                                size="small"
                            >
                                EOD Report
                            </Button>
                            <span className="shrink-0 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">
                                {tasks.total} task{tasks.total === 1 ? '' : 's'}
                            </span>
                        </div>
                    </header>
                    {statusMessage && (
                        <Alert severity="success">{statusMessage}</Alert>
                    )}
                    {error && !review && (
                        <Alert severity="error" onClose={() => setError('')}>
                            {error}
                            <Button
                                size="small"
                                onClick={() => router.reload()}
                            >
                                Refresh tasks
                            </Button>
                        </Alert>
                    )}
                    {isAdmin && (
                        <form
                            onSubmit={create}
                            className="task-tracker-fields rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white p-4 shadow-sm sm:p-6"
                        >
                            <div className="grid items-stretch gap-5 lg:grid-cols-2">
                                <div className="grid min-w-0 gap-4">
                                    <TextField
                                        label="Task title"
                                        placeholder="Enter a task title"
                                        required
                                        fullWidth
                                        value={form.data.title}
                                        onChange={(e) =>
                                            form.setData(
                                                'title',
                                                e.target.value,
                                            )
                                        }
                                        slotProps={{
                                            htmlInput: { maxLength: 180 },
                                        }}
                                        disabled={form.processing}
                                    />
                                    <TextField
                                        label="Description"
                                        placeholder="Describe the task and expected outcome"
                                        multiline
                                        rows={3}
                                        fullWidth
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        slotProps={{
                                            htmlInput: { maxLength: 5000 },
                                        }}
                                        disabled={form.processing}
                                    />
                                </div>
                                <fieldset className="flex min-w-0 flex-col">
                                    <legend className="mb-2 text-sm font-semibold text-slate-800">
                                        Assign to{' '}
                                        <span className="text-red-700">*</span>
                                    </legend>
                                    <TextField
                                        size="small"
                                        fullWidth
                                        placeholder="Search name or employee ID"
                                        slotProps={{
                                            htmlInput: {
                                                'aria-label':
                                                    'Search assignees',
                                            },
                                        }}
                                        value={personSearch}
                                        onChange={(e) =>
                                            setPersonSearch(e.target.value)
                                        }
                                    />
                                    <div className="mt-2 max-h-40 min-h-28 flex-1 [scrollbar-gutter:stable] overflow-y-auto overscroll-contain rounded-xl border border-slate-200 p-1">
                                        {assignees
                                            .filter((person) =>
                                                `${person.name} ${person.username ?? ''}`
                                                    .toLowerCase()
                                                    .includes(
                                                        personSearch.toLowerCase(),
                                                    ),
                                            )
                                            .map((person) => (
                                                <label
                                                    key={person.id}
                                                    className="flex cursor-pointer items-center gap-1 rounded-lg pr-2 text-sm hover:bg-red-50"
                                                >
                                                    <Checkbox
                                                        size="small"
                                                        checked={form.data.assignee_ids.includes(
                                                            person.id,
                                                        )}
                                                        disabled={
                                                            form.processing
                                                        }
                                                        onChange={(
                                                            _,
                                                            checked,
                                                        ) =>
                                                            form.setData(
                                                                'assignee_ids',
                                                                checked
                                                                    ? [
                                                                          ...form
                                                                              .data
                                                                              .assignee_ids,
                                                                          person.id,
                                                                      ]
                                                                    : form.data.assignee_ids.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              person.id,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                    <span className="min-w-0 flex-1 font-medium">
                                                        {person.name}
                                                        <span className="block text-xs text-slate-400">
                                                            {person.username}
                                                        </span>
                                                    </span>
                                                    <span className="shrink-0 text-xs text-slate-500">
                                                        {roleLabel(person.role)}
                                                    </span>
                                                </label>
                                            ))}
                                        {!assignees.length && (
                                            <p className="p-3 text-sm text-slate-500">
                                                No other active admins or team
                                                leaders are available.
                                            </p>
                                        )}
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {form.data.assignee_ids.length} selected
                                        · Each person has their own progress.
                                    </p>
                                </fieldset>
                            </div>
                            <TextField
                                sx={{ mt: 3 }}
                                label="Checklist items"
                                placeholder="One checklist item per line"
                                helperText="Optional · Up to 100 items, 250 characters each"
                                multiline
                                rows={3}
                                fullWidth
                                value={form.data.checklist}
                                onChange={(e) =>
                                    form.setData('checklist', e.target.value)
                                }
                                disabled={form.processing}
                            />
                            {Object.keys(form.errors).length > 0 && (
                                <Alert severity="error" sx={{ mt: 2 }}>
                                    {Object.values(form.errors).map(
                                        (message, index) => (
                                            <div key={index}>{message}</div>
                                        ),
                                    )}
                                </Alert>
                            )}
                            <div className="mt-4 flex justify-end">
                                <Button
                                    type="submit"
                                    variant="contained"
                                    startIcon={<Plus size={17} />}
                                    disabled={
                                        form.processing ||
                                        !form.data.title.trim() ||
                                        !form.data.assignee_ids.length
                                    }
                                >
                                    {form.processing ? 'Adding…' : 'Add Task'}
                                </Button>
                            </div>
                        </form>
                    )}
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                '/task-tracker',
                                { search, status: status || undefined, scope },
                                { preserveState: true, preserveScroll: true },
                            );
                        }}
                        className="task-tracker-fields flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4"
                    >
                        <TextField
                            size="small"
                            label="Search tasks"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            sx={{ flex: 1, minWidth: 180 }}
                        />
                        <TextField
                            select
                            size="small"
                            label="Status"
                            slotProps={{ select: { displayEmpty: true } }}
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            sx={{ minWidth: 175 }}
                        >
                            <MenuItem value="">All statuses</MenuItem>
                            {Object.entries(labels).map(([key, label]) => (
                                <MenuItem key={key} value={key}>
                                    {label}
                                </MenuItem>
                            ))}
                        </TextField>
                        {isAdmin && (
                            <TextField
                                select
                                size="small"
                                label="Show"
                                value={scope}
                                onChange={(e) => setScope(e.target.value)}
                                sx={{ minWidth: 160 }}
                            >
                                <MenuItem value="all">All tasks</MenuItem>
                                <MenuItem value="mine">Assigned to me</MenuItem>
                            </TextField>
                        )}
                        <Button
                            type="submit"
                            variant="outlined"
                            startIcon={<Search size={16} />}
                        >
                            Filter
                        </Button>
                    </form>
                    <section aria-label="Tasks" className="space-y-3">
                        {!tasks.data.length && (
                            <div className="rounded-2xl border border-dashed border-red-200 bg-white p-10 text-center text-slate-500">
                                No tasks to show.{' '}
                                {isAdmin
                                    ? 'Add a task above or adjust your filters.'
                                    : 'Tasks assigned to you will appear here.'}
                            </div>
                        )}
                        {tasks.data.map((task) => {
                            const completed = task.assignments.reduce(
                                (sum, item) =>
                                    sum + item.completed_items.length,
                                0,
                            );
                            const total =
                                task.checklist.length * task.assignments.length;
                            const done = task.assignments.every(
                                (item) => item.status === 'approved_done',
                            );
                            const state = done
                                ? 'approved_done'
                                : task.assignments.some(
                                        (item) =>
                                            item.status === 'pending_review',
                                    )
                                  ? 'pending_review'
                                  : completed
                                    ? 'in_progress'
                                    : 'open';

                            return (
                                <details
                                    key={task.id}
                                    className={`group overflow-hidden rounded-xl border bg-white shadow-sm ${done ? 'border-emerald-400' : 'border-slate-200'}`}
                                >
                                    <summary className="flex cursor-pointer list-none items-center gap-3 p-4 sm:p-5 [&::-webkit-details-marker]:hidden">
                                        <ChevronRight
                                            size={19}
                                            className="shrink-0 text-slate-500 transition-transform group-open:rotate-90"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <h2 className="font-bold break-words text-slate-900">
                                                {task.title}
                                            </h2>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Created by {task.creator_name} ·{' '}
                                                {dateLabel(task.created_at)}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Assigned to{' '}
                                                {task.assignments
                                                    .map(
                                                        (item) =>
                                                            item.assignee_name,
                                                    )
                                                    .join(', ')}
                                            </p>
                                            {task.description && (
                                                <p className="mt-2 line-clamp-2 text-sm break-words text-slate-600">
                                                    {task.description}
                                                </p>
                                            )}
                                        </div>
                                        <div className="w-32 shrink-0 text-right sm:w-44">
                                            <span
                                                className={`inline-block rounded-full px-2.5 py-1 text-[11px] font-bold ${colors[state]}`}
                                            >
                                                {labels[state]}
                                            </span>
                                            <div className="mt-3 flex justify-between text-xs font-semibold text-slate-600">
                                                <span>Checklist</span>
                                                <span>
                                                    {completed}/{total}
                                                </span>
                                            </div>
                                            <LinearProgress
                                                variant="determinate"
                                                value={
                                                    total
                                                        ? (completed / total) *
                                                          100
                                                        : done
                                                          ? 100
                                                          : 0
                                                }
                                                sx={{
                                                    mt: 0.7,
                                                    height: 6,
                                                    borderRadius: 10,
                                                }}
                                            />
                                        </div>
                                    </summary>
                                    <div className="space-y-4 border-t border-slate-100 p-4 sm:p-5">
                                        {task.description && (
                                            <p className="text-sm break-words whitespace-pre-wrap text-slate-600">
                                                {task.description}
                                            </p>
                                        )}
                                        {task.assignments.map((assignment) => {
                                            const editable =
                                                assignment.user_id ===
                                                    currentUserId &&
                                                [
                                                    'open',
                                                    'in_progress',
                                                ].includes(assignment.status);

                                            return (
                                                <div
                                                    key={assignment.id}
                                                    className="rounded-xl border border-slate-200 p-4"
                                                >
                                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                        <h3 className="font-semibold text-slate-800">
                                                            {
                                                                assignment.assignee_name
                                                            }{' '}
                                                            <span className="text-xs font-normal text-slate-500">
                                                                ·{' '}
                                                                {roleLabel(
                                                                    assignment.assignee_role,
                                                                )}
                                                            </span>
                                                        </h3>
                                                        <span
                                                            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${colors[assignment.status]}`}
                                                        >
                                                            {
                                                                labels[
                                                                    assignment
                                                                        .status
                                                                ]
                                                            }
                                                        </span>
                                                    </div>
                                                    <div className="max-h-72 [scrollbar-gutter:stable] space-y-1 overflow-y-auto overscroll-contain pr-2">
                                                        {task.checklist.map(
                                                            (item) => (
                                                                <label
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    className="flex items-start gap-1 rounded-lg bg-slate-50 pr-2 text-sm"
                                                                >
                                                                    <Checkbox
                                                                        size="small"
                                                                        checked={assignment.completed_items.includes(
                                                                            item.id,
                                                                        )}
                                                                        disabled={
                                                                            !editable ||
                                                                            busy
                                                                        }
                                                                        onChange={(
                                                                            _,
                                                                            checked,
                                                                        ) =>
                                                                            update(
                                                                                task,
                                                                                assignment,
                                                                                'checklist',
                                                                                checked
                                                                                    ? [
                                                                                          ...assignment.completed_items,
                                                                                          item.id,
                                                                                      ]
                                                                                    : assignment.completed_items.filter(
                                                                                          (
                                                                                              id,
                                                                                          ) =>
                                                                                              id !==
                                                                                              item.id,
                                                                                      ),
                                                                            )
                                                                        }
                                                                    />
                                                                    <span
                                                                        className={`py-2 break-words ${assignment.completed_items.includes(item.id) ? 'text-slate-400 line-through' : 'text-slate-700'}`}
                                                                    >
                                                                        {
                                                                            item.label
                                                                        }
                                                                    </span>
                                                                </label>
                                                            ),
                                                        )}
                                                        {!task.checklist
                                                            .length && (
                                                            <p className="text-sm text-slate-500">
                                                                No checklist.
                                                                Submit this task
                                                                when the work is
                                                                complete.
                                                            </p>
                                                        )}
                                                    </div>
                                                    {assignment.reviewer_name && (
                                                        <p className="mt-3 text-xs text-slate-500">
                                                            Reviewed by{' '}
                                                            {
                                                                assignment.reviewer_name
                                                            }
                                                        </p>
                                                    )}
                                                    {assignment.review_notes && (
                                                        <p className="mt-2 rounded-lg bg-amber-50 p-3 text-sm whitespace-pre-wrap text-amber-900">
                                                            {
                                                                assignment.review_notes
                                                            }
                                                        </p>
                                                    )}
                                                    <div className="mt-3 flex flex-wrap justify-end gap-2">
                                                        {editable && (
                                                            <Button
                                                                variant="contained"
                                                                disabled={
                                                                    busy ||
                                                                    assignment
                                                                        .completed_items
                                                                        .length !==
                                                                        task
                                                                            .checklist
                                                                            .length
                                                                }
                                                                onClick={async () => {
                                                                    if (
                                                                        await confirm(
                                                                            `Submit “${task.title}” for admin approval?`,
                                                                        )
                                                                    ) {
                                                                        update(
                                                                            task,
                                                                            assignment,
                                                                            'submit',
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                Submit for
                                                                approval
                                                            </Button>
                                                        )}
                                                        {isAdmin &&
                                                            assignment.user_id !==
                                                                currentUserId &&
                                                            assignment.status ===
                                                                'pending_review' && (
                                                                <>
                                                                    <Button
                                                                        variant="outlined"
                                                                        disabled={
                                                                            busy
                                                                        }
                                                                        onClick={() => {
                                                                            setReview(
                                                                                {
                                                                                    task,
                                                                                    assignment,
                                                                                    action: 'return',
                                                                                },
                                                                            );
                                                                            setNotes(
                                                                                '',
                                                                            );
                                                                            setError(
                                                                                '',
                                                                            );
                                                                        }}
                                                                    >
                                                                        Return
                                                                        for
                                                                        changes
                                                                    </Button>
                                                                    <Button
                                                                        variant="contained"
                                                                        disabled={
                                                                            busy
                                                                        }
                                                                        onClick={() => {
                                                                            setReview(
                                                                                {
                                                                                    task,
                                                                                    assignment,
                                                                                    action: 'approve',
                                                                                },
                                                                            );
                                                                            setNotes(
                                                                                '',
                                                                            );
                                                                            setError(
                                                                                '',
                                                                            );
                                                                        }}
                                                                    >
                                                                        Approve
                                                                        completion
                                                                    </Button>
                                                                </>
                                                            )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                        {isAdmin && (
                                            <div className="flex justify-end">
                                                <Button
                                                    color="error"
                                                    startIcon={
                                                        <Trash2 size={16} />
                                                    }
                                                    disabled={busy}
                                                    onClick={() => remove(task)}
                                                >
                                                    Delete task
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </details>
                            );
                        })}
                    </section>
                    {tasks.last_page > 1 && (
                        <div className="flex items-center justify-between gap-3">
                            <Button
                                disabled={!tasks.prev_page_url}
                                onClick={() =>
                                    tasks.prev_page_url &&
                                    router.get(
                                        tasks.prev_page_url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Previous
                            </Button>
                            <span className="text-sm text-slate-500">
                                Page {tasks.current_page} of {tasks.last_page}
                            </span>
                            <Button
                                disabled={!tasks.next_page_url}
                                onClick={() =>
                                    tasks.next_page_url &&
                                    router.get(
                                        tasks.next_page_url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Next
                            </Button>
                        </div>
                    )}
                </div>
            </main>
            <Dialog
                open={review !== null}
                onClose={() => !busy && setReview(null)}
                fullWidth
                maxWidth="sm"
                slotProps={{ paper: { sx: { borderRadius: 3 } } }}
            >
                <DialogTitle sx={{ fontWeight: 800 }}>
                    {review?.action === 'approve'
                        ? 'Approve task completion'
                        : 'Return task for changes'}
                </DialogTitle>
                <DialogContent>
                    <p className="mb-4 text-sm text-slate-600">
                        {review?.task.title} ·{' '}
                        {review?.assignment.assignee_name}
                    </p>
                    <TextField
                        label="Review notes (optional)"
                        multiline
                        minRows={3}
                        fullWidth
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        slotProps={{ htmlInput: { maxLength: 2000 } }}
                        sx={{ mt: 1 }}
                    />
                    {error && (
                        <Alert severity="error" sx={{ mt: 2 }}>
                            {error}
                        </Alert>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button disabled={busy} onClick={() => setReview(null)}>
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={busy}
                        onClick={() =>
                            review &&
                            update(
                                review.task,
                                review.assignment,
                                review.action,
                            )
                        }
                    >
                        {busy
                            ? 'Saving…'
                            : review?.action === 'approve'
                              ? 'Approve'
                              : 'Return task'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

TaskTracker.layout = {
    breadcrumbs: [{ title: 'Task Tracker', href: '/task-tracker' }],
};
