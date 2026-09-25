import { Head, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    Tab,
    Tabs,
    TextField,
} from '@mui/material';
import {
    CheckCircle2,
    Circle,
    ClipboardCheck,
    Eye,
    ListChecks,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DateTimeField } from '@/components/date-time-field';
import { useConfirmation } from '@/hooks/use-confirmation';
import '../../css/task-tracker.css';

type Assignment = {
    id: number;
    version: number;
    title: string;
    description: string | null;
    status: string;
    assigned_at: string;
    completed_items: string[];
    checklist: { id: string; label: string }[];
};
type Report = {
    id: number;
    author_name: string;
    author_username: string | null;
    author_role: string;
    report_date: string;
    summary: string;
    task_count: number;
    created_at: string;
};
type Detail = Report & {
    blockers: string | null;
    next_steps: string | null;
    task_snapshots: {
        task_id: number;
        assignment_id: number;
        title: string;
        description: string | null;
        status: string;
        checklist: { id: string; label: string; completed: boolean }[];
        completed_count: number;
        notes: string | null;
        reviewer_name: string | null;
        review_notes: string | null;
    }[];
};
type Props = {
    assignments: Assignment[];
    reports: {
        data: Report[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    today: string;
    isAdmin: boolean;
    filters: { search: string; role: string; from: string; to: string };
    statusMessage?: string;
};
const statuses: Record<string, string> = {
    open: 'Open',
    in_progress: 'In progress',
    pending_review: 'Pending approval',
    approved_done: 'Approved Done',
};
const roleLabel = (value: string) =>
    value === 'admin' ? 'Admin' : 'Team Leader';
const dateLabel = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeZone: 'Asia/Manila',
    }).format(
        new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value),
    );
const localDay = (value: string) =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));

export default function EodReports({
    assignments,
    reports,
    today,
    isAdmin,
    filters,
    statusMessage,
}: Props) {
    const confirm = useConfirmation();
    const [tab, setTab] = useState('reports');
    const [taskSearch, setTaskSearch] = useState('');
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [filterError, setFilterError] = useState('');
    const [detailOpen, setDetailOpen] = useState(false);
    const [detail, setDetail] = useState<Detail | null>(null);
    const [detailError, setDetailError] = useState('');
    const pending = useRef<AbortController | null>(null);
    useEffect(() => () => pending.current?.abort(), []);
    const form = useForm({
        request_id: crypto.randomUUID(),
        report_date: today,
        summary: '',
        blockers: '',
        next_steps: '',
        tasks: [] as {
            assignment_id: number;
            version: number;
            notes: string;
        }[],
    });
    const selected = new Set(form.data.tasks.map((task) => task.assignment_id));
    const available = assignments.filter(
        (task) => localDay(task.assigned_at) <= form.data.report_date,
    );
    const visible = available.filter((task) =>
        task.title.toLowerCase().includes(taskSearch.toLowerCase()),
    );
    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (
            !(await confirm(
                'Submit this EOD report? The selected task progress will be saved as a permanent record.',
            ))
        ) {
            return;
        }

        form.post('/eod-reports', {
            preserveScroll: true,
            onSuccess: () => {
                form.setData({
                    request_id: crypto.randomUUID(),
                    report_date: today,
                    summary: '',
                    blockers: '',
                    next_steps: '',
                    tasks: [],
                });
                setTab('reports');
            },
        });
    };
    const openReport = async (report: Report) => {
        pending.current?.abort();
        const controller = new AbortController();
        pending.current = controller;
        setDetailOpen(true);
        setDetail(null);
        setDetailError('');

        try {
            const response = await fetch(`/eod-reports/${report.id}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(
                    'Unable to open this report. Refresh and try again.',
                );
            }

            const data: Detail = await response.json();

            if (!controller.signal.aborted) {
                setDetail(data);
            }
        } catch (error) {
            if (!controller.signal.aborted) {
                setDetailError(
                    error instanceof Error
                        ? error.message
                        : 'Unable to load report.',
                );
            }
        }
    };
    const closeDetail = () => {
        pending.current?.abort();
        setDetailOpen(false);
    };

    return (
        <>
            <Head title="EOD Report" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1500px] space-y-5">
                    <header>
                        <p className="mb-2 text-xs font-bold tracking-[0.2em] text-red-800 uppercase">
                            Daily work summary
                        </p>
                        <h1 className="flex items-center gap-3 text-3xl font-bold tracking-tight text-slate-900">
                            <ClipboardCheck className="text-red-800" />
                            EOD Report
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            {isAdmin
                                ? 'Review reports from admins and team leaders, or create your own from assigned tasks.'
                                : 'Summarize your assigned tasks and share your end-of-day progress with admins.'}
                        </p>
                    </header>
                    {statusMessage && (
                        <Alert severity="success">{statusMessage}</Alert>
                    )}
                    <div className="rounded-2xl border border-slate-200 bg-white px-3">
                        <Tabs
                            value={tab}
                            onChange={(_, value: string) => setTab(value)}
                            variant="scrollable"
                            allowScrollButtonsMobile
                        >
                            <Tab
                                value="reports"
                                label={`${isAdmin ? 'Submitted reports' : 'My reports'} (${reports.total})`}
                            />
                            <Tab value="create" label="Create report" />
                        </Tabs>
                    </div>
                    {tab === 'create' ? (
                        <form
                            onSubmit={submit}
                            className="task-tracker-fields space-y-5 rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white p-4 shadow-sm sm:p-6"
                        >
                            <div className="flex flex-wrap items-end justify-between gap-4">
                                <div className="w-full sm:w-64">
                                    <DateTimeField
                                        label="Report date"
                                        type="date"
                                        fullWidth
                                        value={form.data.report_date}
                                        onChange={(event) => {
                                            form.setData(
                                                'report_date',
                                                event.target.value,
                                            );
                                            form.setData('tasks', []);
                                        }}
                                    />
                                </div>
                                <Button
                                    href="/task-tracker"
                                    variant="outlined"
                                    startIcon={<ListChecks size={16} />}
                                >
                                    Open Task Tracker
                                </Button>
                            </div>
                            <section className="rounded-xl border border-slate-200 p-4">
                                <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                                    <div>
                                        <h2 className="font-semibold text-slate-800">
                                            Select tasks for this report
                                        </h2>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {selected.size} selected · Current
                                            progress is saved when you submit.
                                        </p>
                                    </div>
                                    <TextField
                                        size="small"
                                        label="Search assigned tasks"
                                        value={taskSearch}
                                        onChange={(event) =>
                                            setTaskSearch(event.target.value)
                                        }
                                    />
                                </div>
                                <div className="max-h-80 [scrollbar-gutter:stable] space-y-2 overflow-y-auto overscroll-contain pr-1">
                                    {visible.map((task) => (
                                        <label
                                            key={task.id}
                                            className={`flex cursor-pointer items-start gap-2 rounded-xl border p-2 ${selected.has(task.id) ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50'}`}
                                        >
                                            <Checkbox
                                                checked={selected.has(task.id)}
                                                disabled={form.processing}
                                                onChange={(_, checked) =>
                                                    form.setData(
                                                        'tasks',
                                                        checked
                                                            ? [
                                                                  ...form.data
                                                                      .tasks,
                                                                  {
                                                                      assignment_id:
                                                                          task.id,
                                                                      version:
                                                                          task.version,
                                                                      notes: '',
                                                                  },
                                                              ]
                                                            : form.data.tasks.filter(
                                                                  (item) =>
                                                                      item.assignment_id !==
                                                                      task.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            <span className="min-w-0 flex-1 py-2">
                                                <span className="block text-sm font-semibold break-words text-slate-800">
                                                    {task.title}
                                                </span>
                                                <span className="mt-1 block text-xs text-slate-500">
                                                    {statuses[task.status]} ·
                                                    Checklist{' '}
                                                    {
                                                        task.completed_items
                                                            .length
                                                    }
                                                    /{task.checklist.length}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                    {!visible.length && (
                                        <p className="p-5 text-center text-sm text-slate-500">
                                            {available.length
                                                ? 'No tasks match your search.'
                                                : 'No assigned tasks are available for this date. Tasks must be assigned to you in the Task Tracker before you can include them.'}
                                        </p>
                                    )}
                                </div>
                                <Button
                                    size="small"
                                    startIcon={<RefreshCw size={14} />}
                                    disabled={form.processing}
                                    onClick={() =>
                                        router.reload({
                                            only: ['assignments'],
                                            onSuccess: (page) => {
                                                const latest = page.props
                                                    .assignments as Assignment[];
                                                form.setData(
                                                    'tasks',
                                                    form.data.tasks
                                                        .filter((item) =>
                                                            latest.some(
                                                                (task) =>
                                                                    task.id ===
                                                                    item.assignment_id,
                                                            ),
                                                        )
                                                        .map((item) => ({
                                                            ...item,
                                                            version:
                                                                latest.find(
                                                                    (task) =>
                                                                        task.id ===
                                                                        item.assignment_id,
                                                                )!.version,
                                                        })),
                                                );
                                                form.clearErrors();
                                            },
                                        })
                                    }
                                >
                                    Refresh task progress
                                </Button>
                            </section>
                            {form.data.tasks.map((entry) => {
                                const task = assignments.find(
                                    (item) => item.id === entry.assignment_id,
                                );

                                return (
                                    task && (
                                        <TextField
                                            key={entry.assignment_id}
                                            label={`Work notes: ${task.title}`}
                                            placeholder="What did you accomplish on this task today? (optional)"
                                            multiline
                                            minRows={2}
                                            fullWidth
                                            value={entry.notes}
                                            onChange={(event) =>
                                                form.setData(
                                                    'tasks',
                                                    form.data.tasks.map(
                                                        (item) =>
                                                            item.assignment_id ===
                                                            entry.assignment_id
                                                                ? {
                                                                      ...item,
                                                                      notes: event
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : item,
                                                    ),
                                                )
                                            }
                                            disabled={form.processing}
                                            slotProps={{
                                                htmlInput: { maxLength: 2000 },
                                            }}
                                        />
                                    )
                                );
                            })}
                            <TextField
                                label="Daily summary"
                                placeholder="Summarize your accomplishments and progress today"
                                required
                                multiline
                                minRows={3}
                                fullWidth
                                value={form.data.summary}
                                onChange={(event) =>
                                    form.setData('summary', event.target.value)
                                }
                                disabled={form.processing}
                                slotProps={{ htmlInput: { maxLength: 5000 } }}
                            />
                            <div className="grid gap-5 lg:grid-cols-2">
                                <TextField
                                    label="Blockers / concerns"
                                    placeholder="Any issues or help needed? (optional)"
                                    multiline
                                    minRows={3}
                                    fullWidth
                                    value={form.data.blockers}
                                    onChange={(event) =>
                                        form.setData(
                                            'blockers',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                    slotProps={{
                                        htmlInput: { maxLength: 3000 },
                                    }}
                                />
                                <TextField
                                    label="Next steps"
                                    placeholder="What will you work on next? (optional)"
                                    multiline
                                    minRows={3}
                                    fullWidth
                                    value={form.data.next_steps}
                                    onChange={(event) =>
                                        form.setData(
                                            'next_steps',
                                            event.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                    slotProps={{
                                        htmlInput: { maxLength: 3000 },
                                    }}
                                />
                            </div>
                            {Object.keys(form.errors).length > 0 && (
                                <Alert severity="error">
                                    {Object.values(form.errors).map(
                                        (message, index) => (
                                            <div key={index}>{message}</div>
                                        ),
                                    )}
                                </Alert>
                            )}
                            <div className="flex justify-end">
                                <Button
                                    variant="contained"
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !selected.size ||
                                        !form.data.summary.trim()
                                    }
                                >
                                    {form.processing
                                        ? 'Submitting…'
                                        : 'Submit EOD report'}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    setFilterError('');
                                    router.get(
                                        '/eod-reports',
                                        {
                                            search,
                                            role: role || undefined,
                                            from: from || undefined,
                                            to: to || undefined,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                            onError: (errors) =>
                                                setFilterError(
                                                    Object.values(errors)[0],
                                                ),
                                        },
                                    );
                                }}
                                className="task-tracker-fields flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4"
                            >
                                <TextField
                                    label={
                                        isAdmin
                                            ? 'Search employee or summary'
                                            : 'Search summary'
                                    }
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    sx={{ flex: 1, minWidth: 200 }}
                                />
                                {isAdmin && (
                                    <TextField
                                        select
                                        label="Role"
                                        value={role}
                                        onChange={(event) =>
                                            setRole(event.target.value)
                                        }
                                        slotProps={{
                                            select: { displayEmpty: true },
                                        }}
                                        sx={{ minWidth: 160 }}
                                    >
                                        <MenuItem value="">All roles</MenuItem>
                                        <MenuItem value="admin">Admin</MenuItem>
                                        <MenuItem value="team_leader">
                                            Team Leader
                                        </MenuItem>
                                    </TextField>
                                )}
                                <div className="w-full sm:w-44">
                                    <DateTimeField
                                        label="From date"
                                        type="date"
                                        fullWidth
                                        value={from}
                                        onChange={(event) =>
                                            setFrom(event.target.value)
                                        }
                                    />
                                </div>
                                <div className="w-full sm:w-44">
                                    <DateTimeField
                                        label="To date"
                                        type="date"
                                        fullWidth
                                        value={to}
                                        onChange={(event) =>
                                            setTo(event.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    variant="outlined"
                                    type="submit"
                                    startIcon={<Search size={16} />}
                                >
                                    Filter
                                </Button>
                            </form>
                            {filterError && (
                                <Alert severity="error">{filterError}</Alert>
                            )}
                            <section
                                aria-label="Submitted EOD reports"
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                            >
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[700px] text-left text-sm">
                                        <thead className="bg-red-50 text-xs text-red-900 uppercase">
                                            <tr>
                                                {[
                                                    'Employee',
                                                    'Report date',
                                                    'Summary',
                                                    'Tasks',
                                                    'Submitted',
                                                    '',
                                                ].map((label) => (
                                                    <th
                                                        key={label}
                                                        className="px-5 py-4"
                                                    >
                                                        {label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {reports.data.map((report) => (
                                                <tr
                                                    key={report.id}
                                                    className="border-t border-slate-100"
                                                >
                                                    <td className="px-5 py-4">
                                                        <div className="font-semibold text-slate-800">
                                                            {report.author_name}
                                                        </div>
                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {
                                                                report.author_username
                                                            }{' '}
                                                            ·{' '}
                                                            {roleLabel(
                                                                report.author_role,
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-4 whitespace-nowrap">
                                                        {dateLabel(
                                                            report.report_date,
                                                        )}
                                                    </td>
                                                    <td className="max-w-sm px-5 py-4">
                                                        <p className="line-clamp-2 break-words text-slate-600">
                                                            {report.summary}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        {report.task_count}
                                                    </td>
                                                    <td className="px-5 py-4 whitespace-nowrap text-slate-500">
                                                        {dateLabel(
                                                            report.created_at,
                                                        )}
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <Button
                                                            startIcon={
                                                                <Eye
                                                                    size={16}
                                                                />
                                                            }
                                                            onClick={() =>
                                                                openReport(
                                                                    report,
                                                                )
                                                            }
                                                            aria-label={`View report by ${report.author_name} for ${dateLabel(report.report_date)}`}
                                                        >
                                                            View
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {!reports.data.length && (
                                                <tr>
                                                    <td
                                                        colSpan={6}
                                                        className="px-5 py-12 text-center text-slate-500"
                                                    >
                                                        No reports found. Create
                                                        a report from your
                                                        assigned tasks or adjust
                                                        your filters.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex items-center justify-between border-t border-slate-100 p-4">
                                    <Button
                                        disabled={!reports.prev_page_url}
                                        onClick={() =>
                                            reports.prev_page_url &&
                                            router.get(reports.prev_page_url)
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <span className="text-xs text-slate-500">
                                        {reports.total} reports · Page{' '}
                                        {reports.current_page} of{' '}
                                        {reports.last_page}
                                    </span>
                                    <Button
                                        disabled={!reports.next_page_url}
                                        onClick={() =>
                                            reports.next_page_url &&
                                            router.get(reports.next_page_url)
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </main>
            <Dialog
                open={detailOpen}
                onClose={closeDetail}
                fullWidth
                maxWidth="md"
                scroll="paper"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '90dvh' } },
                }}
            >
                <DialogTitle
                    sx={{ fontWeight: 800, borderBottom: '1px solid #eee' }}
                >
                    EOD Report {detail && `· ${dateLabel(detail.report_date)}`}
                </DialogTitle>
                <DialogContent sx={{ pt: '20px !important' }}>
                    {detailError ? (
                        <Alert severity="error">{detailError}</Alert>
                    ) : !detail ? (
                        <p
                            role="status"
                            className="p-8 text-center text-slate-500"
                        >
                            Loading report…
                        </p>
                    ) : (
                        <div className="space-y-5">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900">
                                    {detail.author_name}
                                </h2>
                                <p className="text-sm text-slate-500">
                                    {detail.author_username} ·{' '}
                                    {roleLabel(detail.author_role)} · Submitted{' '}
                                    {dateLabel(detail.created_at)}
                                </p>
                            </div>
                            <div className="rounded-xl border border-red-100 bg-red-50 p-4">
                                <h3 className="mb-2 font-semibold">
                                    Daily summary
                                </h3>
                                <p className="text-sm break-words whitespace-pre-wrap text-slate-700">
                                    {detail.summary}
                                </p>
                            </div>
                            <h3 className="font-semibold text-slate-800">
                                Task progress at submission
                            </h3>
                            {detail.task_snapshots.map((task) => (
                                <section
                                    key={task.assignment_id}
                                    className="rounded-xl border border-slate-200 p-4"
                                >
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <h4 className="font-semibold break-words text-slate-900">
                                            {task.title}
                                        </h4>
                                        <span className="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                            {statuses[task.status]} ·{' '}
                                            {task.completed_count}/
                                            {task.checklist.length}
                                        </span>
                                    </div>
                                    {task.notes && (
                                        <p className="mt-3 text-sm break-words whitespace-pre-wrap text-slate-700">
                                            {task.notes}
                                        </p>
                                    )}
                                    <ul className="mt-3 space-y-2">
                                        {task.checklist.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex items-start gap-2 text-sm text-slate-600"
                                            >
                                                {item.completed ? (
                                                    <CheckCircle2
                                                        size={17}
                                                        className="shrink-0 text-emerald-600"
                                                        aria-label="Completed"
                                                    />
                                                ) : (
                                                    <Circle
                                                        size={17}
                                                        className="shrink-0 text-slate-400"
                                                        aria-label="Incomplete"
                                                    />
                                                )}
                                                <span className="break-words">
                                                    {item.label}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                    {task.reviewer_name && (
                                        <p className="mt-3 text-xs text-slate-500">
                                            Reviewed by {task.reviewer_name}
                                            {task.review_notes
                                                ? ` · ${task.review_notes}`
                                                : ''}
                                        </p>
                                    )}
                                </section>
                            ))}
                            <div className="grid gap-4 sm:grid-cols-2">
                                {[
                                    ['Blockers / concerns', detail.blockers],
                                    ['Next steps', detail.next_steps],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="rounded-xl bg-slate-50 p-4"
                                    >
                                        <h3 className="mb-2 text-sm font-semibold">
                                            {label}
                                        </h3>
                                        <p className="text-sm break-words whitespace-pre-wrap text-slate-600">
                                            {value || 'None reported.'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 2 }}>
                    <Button onClick={closeDetail}>Close</Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

EodReports.layout = {
    breadcrumbs: [{ title: 'EOD Report', href: '/eod-reports' }],
};
