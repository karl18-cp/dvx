import { Head, router } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
    Tooltip,
} from '@mui/material';
import {
    CalendarCheck,
    ChevronLeft,
    ChevronRight,
    History,
    LogIn,
    LogOut,
    Coffee,
    Utensils,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DateTimeField } from '@/components/date-time-field';

type AttendanceEmployee = {
    id: number;
    employeeId: string;
    name: string;
    position: string;
    team: string | null;
    status: string;
    timeIn: string | null;
    lunchOut: string | null;
    lunchIn: string | null;
    timeOut: string | null;
};

type AttendanceProps = {
    attendanceDate: string;
    employees: AttendanceEmployee[];
};

type TimeField = 'time_in' | 'lunch_out' | 'lunch_in' | 'time_out';

type TimeOverride = {
    employee: AttendanceEmployee;
    field: TimeField;
    label: string;
    value: string;
};

const roles = [
    'Admin',
    'Team Leader',
    'Agent',
    'IT Admin',
    'IT Support',
    'IT Developer',
];

const statusOptions = [
    ['all', 'All Statuses'],
    ['present', 'Present'],
    ['late', 'Late'],
    ['absent', 'Absent'],
    ['rest_day', 'Rest Day'],
    ['on_leave', 'On Leave'],
    ['not_recorded', 'Not Recorded'],
];

const initialsFor = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

const formatTime = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : 'Not yet';

const timeInputValue = (value: string | null) => {
    if (!value) return '';
    const date = new Date(value);
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
};

const totalHours = (employee: AttendanceEmployee) => {
    if (!employee.timeIn || !employee.timeOut) return '—';

    let milliseconds =
        new Date(employee.timeOut).getTime() -
        new Date(employee.timeIn).getTime();

    if (employee.lunchOut && employee.lunchIn) {
        milliseconds -=
            new Date(employee.lunchIn).getTime() -
            new Date(employee.lunchOut).getTime();
    }

    const minutes = Math.max(0, Math.floor(milliseconds / 60000));
    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
};

const statusPresentation: Record<string, { label: string; classes: string }> = {
    present: { label: 'Present', classes: 'bg-emerald-50 text-emerald-700' },
    late: { label: 'Late', classes: 'bg-amber-50 text-amber-700' },
    absent: { label: 'Absent', classes: 'bg-red-50 text-red-700' },
    rest_day: { label: 'Rest Day', classes: 'bg-slate-100 text-slate-600' },
    on_leave: { label: 'On Leave', classes: 'bg-sky-50 text-sky-700' },
    not_recorded: {
        label: 'Not Recorded',
        classes: 'bg-slate-100 text-slate-500',
    },
};

export default function Attendance({
    attendanceDate,
    employees,
}: AttendanceProps) {
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('all');
    const [status, setStatus] = useState('all');
    const [page, setPage] = useState(1);
    const [timeOverride, setTimeOverride] = useState<TimeOverride | null>(null);
    const [overrideProcessing, setOverrideProcessing] = useState(false);
    const perPage = 20;

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        return employees.filter(
            (employee) =>
                (!query ||
                    employee.name.toLowerCase().includes(query) ||
                    employee.employeeId.toLowerCase().includes(query)) &&
                (role === 'all' || employee.position === role) &&
                (status === 'all' || employee.status === status),
        );
    }, [employees, role, search, status]);

    const pages = Math.ceil(filtered.length / perPage);
    const visible = filtered.slice((page - 1) * perPage, page * perPage);
    const first = filtered.length ? (page - 1) * perPage + 1 : 0;
    const last = Math.min(page * perPage, filtered.length);

    useEffect(() => setPage(1), [role, search, status]);
    useEffect(() => {
        if (pages > 0 && page > pages) setPage(pages);
    }, [page, pages]);

    const changeDate = (date: string) => {
        router.get('/attendance', { date }, { preserveState: true });
    };

    const saveTimeOverride = () => {
        if (!timeOverride) return;

        setOverrideProcessing(true);
        router.put(
            `/attendance/${timeOverride.employee.id}/time`,
            {
                attendance_date: attendanceDate,
                field: timeOverride.field,
                time: timeOverride.value || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setTimeOverride(null),
                onFinish: () => setOverrideProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="Attendance" />
            <main className="min-h-full bg-[#f7f7fa] p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1600px] space-y-6">
                    <header className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-[#b72822] uppercase">
                                Workforce Management
                            </p>
                            <h1 className="text-3xl font-bold tracking-[-0.03em] text-[#1b1d2a] sm:text-4xl">
                                Daily Attendance
                            </h1>
                            <p className="mt-2 text-sm text-[#777b8e] sm:text-base">
                                Review employee attendance and regular working
                                hours.
                            </p>
                        </div>
                        <DateTimeField
                            label="Attendance Date"
                            type="date"
                            size="small"
                            value={attendanceDate}
                            onChange={(event) => changeDate(event.target.value)}
                            slotProps={{ inputLabel: { shrink: true } }}
                            sx={{ minWidth: 190, bgcolor: 'white' }}
                        />
                    </header>

                    <section className="overflow-hidden rounded-3xl border border-[#e6e7ec] bg-white shadow-[0_16px_50px_rgba(25,27,38,0.06)]">
                        <div className="border-b border-[#ededf1] p-5 sm:p-6">
                            <div className="flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
                                <div className="flex items-center gap-3">
                                    <div className="grid size-11 place-items-center rounded-xl bg-[#fff0ee] text-[#bf2923]">
                                        <CalendarCheck size={21} />
                                    </div>
                                    <div>
                                        <h2 className="text-lg font-bold text-[#202230]">
                                            Attendance Register
                                        </h2>
                                        <p className="text-sm text-[#888b9b]">
                                            {employees.length} registered
                                            employee accounts
                                        </p>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                    <label className="relative block min-w-0 sm:w-64">
                                        <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[#a1a4b2]" />
                                        <input
                                            type="search"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Search name or ID"
                                            className="h-11 w-full rounded-xl border border-[#e1e2e8] bg-[#fafafd] pr-4 pl-11 text-sm outline-none focus:border-[#bd352a] focus:ring-3 focus:ring-[#bd352a]/10"
                                        />
                                    </label>
                                    <TextField
                                        select
                                        size="small"
                                        value={role}
                                        onChange={(event) =>
                                            setRole(event.target.value)
                                        }
                                        sx={{
                                            minWidth: 145,
                                            '& .MuiOutlinedInput-root': {
                                                height: 44,
                                                borderRadius: 3,
                                            },
                                        }}
                                    >
                                        <MenuItem value="all">
                                            All Roles
                                        </MenuItem>
                                        {roles.map((option) => (
                                            <MenuItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                    <TextField
                                        select
                                        size="small"
                                        value={status}
                                        onChange={(event) =>
                                            setStatus(event.target.value)
                                        }
                                        sx={{
                                            minWidth: 155,
                                            '& .MuiOutlinedInput-root': {
                                                height: 44,
                                                borderRadius: 3,
                                            },
                                        }}
                                    >
                                        {statusOptions.map(([value, label]) => (
                                            <MenuItem key={value} value={value}>
                                                {label}
                                            </MenuItem>
                                        ))}
                                    </TextField>
                                    <Button
                                        variant="outlined"
                                        onClick={() =>
                                            router.reload({
                                                only: ['employees'],
                                            })
                                        }
                                        startIcon={<RefreshCw size={17} />}
                                    >
                                        Refresh
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1320px] border-collapse text-left">
                                <thead>
                                    <tr className="bg-[#fff7f6] text-[11px] font-bold tracking-[0.07em] text-[#7a1b18] uppercase">
                                        <th className="px-5 py-4">Employee</th>
                                        <th className="px-4 py-4">ID</th>
                                        <th className="px-4 py-4">Position</th>
                                        <th className="px-4 py-4">Team</th>
                                        <th className="px-4 py-4">Status</th>
                                        <th className="px-4 py-4">Time In</th>
                                        <th className="px-4 py-4">Lunch Out</th>
                                        <th className="px-4 py-4">Lunch In</th>
                                        <th className="px-4 py-4">Time Out</th>
                                        <th className="px-4 py-4">
                                            Total Hours
                                        </th>
                                        <th className="px-5 py-4 text-center">
                                            History
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#eff0f3]">
                                    {visible.map((employee, index) => {
                                        const presentation =
                                            statusPresentation[
                                                employee.status
                                            ] ??
                                            statusPresentation.not_recorded;
                                        return (
                                            <tr
                                                key={employee.id}
                                                className="transition hover:bg-[#fcfaf9]"
                                            >
                                                <td className="px-5 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div
                                                            className={`grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br ${['from-emerald-500 to-amber-400', 'from-sky-500 to-indigo-500', 'from-fuchsia-500 to-rose-400', 'from-orange-500 to-red-500'][index % 4]} text-sm font-bold text-white`}
                                                        >
                                                            {initialsFor(
                                                                employee.name,
                                                            )}
                                                        </div>
                                                        <span className="max-w-44 font-semibold text-[#252735]">
                                                            {employee.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 font-mono text-xs font-bold text-[#c12a26]">
                                                    {employee.employeeId}
                                                </td>
                                                <td className="px-4 py-4 text-sm text-[#4d5060]">
                                                    {employee.position}
                                                </td>
                                                <td className="px-4 py-4 text-sm text-[#626576]">
                                                    {employee.team ??
                                                        'Not assigned'}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-3 py-1.5 text-xs font-bold ${presentation.classes}`}
                                                    >
                                                        {presentation.label}
                                                    </span>
                                                </td>
                                                {[
                                                    {
                                                        field: 'time_in' as const,
                                                        label: 'Time In',
                                                        time: employee.timeIn,
                                                        icon: LogIn,
                                                    },
                                                    {
                                                        field: 'lunch_out' as const,
                                                        label: 'Lunch Out',
                                                        time: employee.lunchOut,
                                                        icon: Coffee,
                                                    },
                                                    {
                                                        field: 'lunch_in' as const,
                                                        label: 'Lunch In',
                                                        time: employee.lunchIn,
                                                        icon: Utensils,
                                                    },
                                                    {
                                                        field: 'time_out' as const,
                                                        label: 'Time Out',
                                                        time: employee.timeOut,
                                                        icon: LogOut,
                                                    },
                                                ].map((entry) => {
                                                    const TimeIcon = entry.icon;
                                                    return (
                                                        <td
                                                            key={entry.field}
                                                            className="px-4 py-4"
                                                        >
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="min-w-16 text-sm font-medium text-[#4f5262]">
                                                                    {formatTime(
                                                                        entry.time,
                                                                    )}
                                                                </span>
                                                                <Tooltip
                                                                    title={`Override ${entry.label}`}
                                                                >
                                                                    <IconButton
                                                                        size="small"
                                                                        aria-label={`Override ${entry.label} for ${employee.name}`}
                                                                        onClick={() =>
                                                                            setTimeOverride(
                                                                                {
                                                                                    employee,
                                                                                    field: entry.field,
                                                                                    label: entry.label,
                                                                                    value: timeInputValue(
                                                                                        entry.time,
                                                                                    ),
                                                                                },
                                                                            )
                                                                        }
                                                                        sx={{
                                                                            width: 30,
                                                                            height: 30,
                                                                            color: '#ad2823',
                                                                            bgcolor:
                                                                                '#fff1ef',
                                                                            '&:hover':
                                                                                {
                                                                                    bgcolor:
                                                                                        '#ffe2df',
                                                                                },
                                                                        }}
                                                                    >
                                                                        <TimeIcon
                                                                            size={
                                                                                15
                                                                            }
                                                                        />
                                                                    </IconButton>
                                                                </Tooltip>
                                                            </div>
                                                        </td>
                                                    );
                                                })}
                                                <td className="px-4 py-4 text-sm font-bold text-[#252735]">
                                                    {totalHours(employee)}
                                                </td>
                                                <td className="px-5 py-4 text-center">
                                                    <button
                                                        type="button"
                                                        aria-label={`Attendance history for ${employee.name}`}
                                                        className="inline-grid size-9 place-items-center rounded-full border border-[#2979ff] text-[#2979ff] transition hover:bg-blue-50"
                                                    >
                                                        <History size={17} />
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {visible.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={11}
                                                className="px-6 py-14 text-center text-sm text-[#888b9b]"
                                            >
                                                No attendance records match the
                                                selected filters.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <footer className="flex flex-col items-center justify-between gap-4 border-t border-[#ededf1] px-6 py-4 sm:flex-row">
                            <p className="text-sm text-[#888b9b]">
                                Showing{' '}
                                <strong className="text-[#363846]">
                                    {first}–{last}
                                </strong>{' '}
                                of{' '}
                                <strong className="text-[#363846]">
                                    {filtered.length}
                                </strong>{' '}
                                employees
                            </p>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    aria-label="Previous page"
                                    disabled={page === 1}
                                    onClick={() =>
                                        setPage((value) =>
                                            Math.max(1, value - 1),
                                        )
                                    }
                                    className="inline-grid size-9 place-items-center rounded-lg border border-[#e1e2e8] disabled:opacity-40"
                                >
                                    <ChevronLeft size={16} />
                                </button>
                                {Array.from(
                                    { length: pages },
                                    (_, index) => index + 1,
                                ).map((number) => (
                                    <button
                                        key={number}
                                        type="button"
                                        onClick={() => setPage(number)}
                                        className={`size-9 rounded-lg text-sm font-semibold ${page === number ? 'bg-[#a92420] text-white' : 'text-[#666979] hover:bg-[#f3f3f6]'}`}
                                    >
                                        {number}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    aria-label="Next page"
                                    disabled={pages === 0 || page === pages}
                                    onClick={() =>
                                        setPage((value) =>
                                            Math.min(pages, value + 1),
                                        )
                                    }
                                    className="inline-grid size-9 place-items-center rounded-lg border border-[#e1e2e8] disabled:opacity-40"
                                >
                                    <ChevronRight size={16} />
                                </button>
                            </div>
                        </footer>
                    </section>
                </div>
            </main>

            <Dialog
                open={timeOverride !== null}
                onClose={() => !overrideProcessing && setTimeOverride(null)}
                fullWidth
                maxWidth="xs"
                slotProps={{ paper: { sx: { borderRadius: 3 } } }}
            >
                <DialogTitle sx={{ pb: 1, fontWeight: 800 }}>
                    Override {timeOverride?.label}
                </DialogTitle>
                <DialogContent sx={{ pt: '12px !important' }}>
                    <p className="mb-5 text-sm text-[#777b8e]">
                        {timeOverride?.employee.name} · {attendanceDate}
                    </p>
                    <DateTimeField
                        label={timeOverride?.label}
                        type="time"
                        fullWidth
                        value={timeOverride?.value ?? ''}
                        onChange={(event) =>
                            setTimeOverride((current) =>
                                current
                                    ? { ...current, value: event.target.value }
                                    : current,
                            )
                        }
                        slotProps={{ inputLabel: { shrink: true } }}
                        helperText="Clear the field and save to remove this recorded time."
                    />
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2.5 }}>
                    <Button
                        color="inherit"
                        onClick={() => setTimeOverride(null)}
                        disabled={overrideProcessing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        onClick={saveTimeOverride}
                        disabled={overrideProcessing}
                    >
                        {overrideProcessing ? 'Saving...' : 'Save Override'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

Attendance.layout = {
    breadcrumbs: [{ title: 'Attendance', href: '/attendance' }],
};
