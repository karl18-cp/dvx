import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Alert,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
} from '@mui/material';
import {
    CalendarDays,
    Check,
    List,
    Pencil,
    Plus,
    RotateCcw,
    Save,
    Search,
    Trash2,
    UserRoundCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';

const dayNames = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];
const fields = ['time_in', 'time_out', 'break_start', 'break_end'] as const;
const fieldLabels = {
    time_in: 'Time In',
    time_out: 'Time Out',
    break_start: 'Break Start',
    break_end: 'Break End',
};
type Times = Record<(typeof fields)[number], string>;
type Day = Times & { day: number; no_schedule: boolean };
type Schedule = {
    id: number;
    name: string;
    days: Day[];
    employees_count: number;
};
type Employee = {
    id: number;
    name: string;
    username: string | null;
    role: string;
    status: string;
    schedule_id: number | null;
    schedule_name: string | null;
    team: string | null;
    campaign: string | null;
};
type Props = {
    schedules: Schedule[];
    employees: Employee[];
    status: string | null;
};
const emptyTimes = (): Times => ({
    time_in: '',
    time_out: '',
    break_start: '',
    break_end: '',
});
const emptyDays = (): Day[] =>
    dayNames.map((_, i) => ({
        day: i + 1,
        no_schedule: true,
        ...emptyTimes(),
    }));
const inputClass =
    'w-full rounded-2xl border border-[#dce1ec] bg-white px-3 py-2.5 text-sm text-[#26334d] outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 disabled:bg-slate-50 disabled:text-slate-400';
const timeInputClass =
    'w-full rounded-2xl border border-[#ae1b20] bg-white px-3 py-2.5 text-sm text-[#26334d] outline-none focus:border-[#dc292e] focus:ring-2 focus:ring-[#dc292e]/20 disabled:border-[#ae1b20]/30 disabled:bg-slate-50 disabled:text-slate-400';
const buttonClass =
    'inline-flex items-center justify-center gap-1.5 rounded-full border border-slate-500 px-4 py-2 text-xs font-extrabold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50';
const primaryClass =
    'inline-flex items-center justify-center gap-1.5 rounded-full bg-gradient-to-r from-[#dc292e] to-[#ae1b20] px-4 py-2 text-xs font-extrabold text-white shadow-md shadow-red-100 transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50';

export default function CampaignSchedules({
    schedules,
    employees,
    status,
}: Props) {
    const page = usePage();
    const [tab, setTab] = useState<'edit' | 'view'>(
        page.url.includes('tab=view') ? 'view' : 'edit',
    );
    const [editing, setEditing] = useState<number | null>(null);
    const [showDays, setShowDays] = useState(false);
    const form = useForm<{ name: string; days: Day[] }>({
        name: '',
        days: emptyDays(),
    });
    const errors = form.errors as Record<string, string>;
    const [bulk, setBulk] = useState<Times>(emptyTimes());
    const [selectedDays, setSelectedDays] = useState<number[]>([1, 2, 3, 4, 5]);
    const [bulkError, setBulkError] = useState('');
    const [scheduleSearch, setScheduleSearch] = useState('');
    const [assigning, setAssigning] = useState<Schedule | null>(null);
    const assignment = useForm<{ employee_ids: number[] }>({
        employee_ids: [],
    });
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [deleting, setDeleting] = useState<Schedule | null>(null);
    const [deletingBusy, setDeletingBusy] = useState(false);
    const [deleteError, setDeleteError] = useState('');

    const clear = () => {
        setEditing(null);
        setShowDays(false);
        form.setData({ name: '', days: emptyDays() });
        form.clearErrors();
        setBulk(emptyTimes());
        setSelectedDays([1, 2, 3, 4, 5]);
        setBulkError('');
    };
    const edit = (schedule: Schedule) => {
        setEditing(schedule.id);
        setShowDays(true);
        form.setData({
            name: schedule.name,
            days: schedule.days.map((day) => ({ ...day })),
        });
        form.clearErrors();
        setBulk(emptyTimes());
        setBulkError('');
        setTab('edit');
    };
    const updateDay = (index: number, changes: Partial<Day>) =>
        form.setData(
            'days',
            form.data.days.map((day, i) =>
                i === index ? { ...day, ...changes } : day,
            ),
        );
    const apply = () => {
        if (!selectedDays.length || !bulk.time_in || !bulk.time_out) {
            setBulkError(
                'Choose at least one day and enter Time In and Time Out.',
            );

            return;
        }

        form.setData(
            'days',
            form.data.days.map((day) =>
                selectedDays.includes(day.day)
                    ? { ...day, ...bulk, no_schedule: false }
                    : day,
            ),
        );
        form.clearErrors();
        setBulkError('');
        setShowDays(true);
    };
    const save = () => {
        if (!showDays) {
            apply();

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                clear();
                setTab('view');
            },
        };

        if (editing) {
            form.put(`/campaign-schedules/${editing}`, options);
        } else {
            form.post('/campaign-schedules', options);
        }
    };
    const openAssign = (schedule: Schedule) => {
        setAssigning(schedule);
        setEmployeeSearch('');
        assignment.clearErrors();
        assignment.setData(
            'employee_ids',
            employees
                .filter((employee) => employee.schedule_id === schedule.id)
                .map((employee) => employee.id),
        );
    };
    const toggleEmployee = (id: number) =>
        assignment.setData(
            'employee_ids',
            assignment.data.employee_ids.includes(id)
                ? assignment.data.employee_ids.filter((value) => value !== id)
                : [...assignment.data.employee_ids, id],
        );
    const visibleEmployees = employees.filter(
        (employee) =>
            (employee.status === 'active' ||
                employee.schedule_id === assigning?.id) &&
            [
                employee.name,
                employee.username,
                employee.team,
                employee.campaign,
                employee.role,
            ].some((value) =>
                value?.toLowerCase().includes(employeeSearch.toLowerCase()),
            ),
    );
    const transfers = employees.filter(
        (employee) =>
            assignment.data.employee_ids.includes(employee.id) &&
            employee.schedule_id !== null &&
            employee.schedule_id !== assigning?.id,
    );
    const visibleSchedules = schedules.filter((schedule) =>
        schedule.name.toLowerCase().includes(scheduleSearch.toLowerCase()),
    );
    const workingDays = form.data.days.filter((day) => !day.no_schedule).length;

    return (
        <main className="min-h-full bg-[#f6f6f9] p-4 text-[#141c2c] lg:p-6">
            <Head title="Campaign Schedules" />
            <section className="relative mx-auto max-w-[1600px] overflow-hidden rounded-3xl bg-white p-5 shadow-sm lg:p-6">
                <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#852323] to-[#f03224]" />
                <header className="mb-5 border-b border-slate-100 pb-4">
                    <h1 className="flex items-center gap-2 text-lg font-extrabold">
                        <CalendarDays className="size-5 text-[#e83924]" />
                        Campaign Schedule
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Create reusable named weekly schedules, then assign
                        employees, edit, or delete them from the view tab.
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                        Times are in Asia/Manila. A time out earlier than time
                        in ends the following day.
                    </p>
                </header>
                {status && (
                    <Alert severity="success" className="mb-4">
                        {status}
                    </Alert>
                )}
                <div
                    role="tablist"
                    aria-label="Campaign schedule views"
                    className="mb-4 flex flex-wrap gap-2"
                >
                    <button
                        id="schedule-editor-tab"
                        role="tab"
                        aria-selected={tab === 'edit'}
                        aria-controls="schedule-editor"
                        onClick={() => setTab('edit')}
                        className={tab === 'edit' ? primaryClass : buttonClass}
                    >
                        <Plus className="size-3.5" />
                        Add / Edit Schedule
                    </button>
                    <button
                        id="schedule-list-tab"
                        role="tab"
                        aria-selected={tab === 'view'}
                        aria-controls="schedule-list"
                        onClick={() => setTab('view')}
                        className={tab === 'view' ? primaryClass : buttonClass}
                    >
                        <List className="size-3.5" />
                        View Schedules
                    </button>
                </div>
                {tab === 'edit' ? (
                    <form
                        id="schedule-editor"
                        role="tabpanel"
                        aria-labelledby="schedule-editor-tab"
                        onSubmit={(event) => {
                            event.preventDefault();
                            save();
                        }}
                        className="space-y-4"
                    >
                        <fieldset
                            disabled={form.processing}
                            className="space-y-4"
                        >
                            {editing && (
                                <Alert severity="info">
                                    Editing{' '}
                                    {
                                        schedules.find(
                                            (schedule) =>
                                                schedule.id === editing,
                                        )?.name
                                    }
                                    . Changes apply to everyone assigned to this
                                    schedule.
                                </Alert>
                            )}
                            {Object.keys(errors).length > 0 && (
                                <Alert severity="error">
                                    Please correct the highlighted fields before
                                    saving.
                                    {errors.days && <div>{errors.days}</div>}
                                </Alert>
                            )}
                            <div className="grid items-end gap-4 rounded-2xl border border-[#e9e9ed] p-4 shadow-sm lg:grid-cols-3">
                                <div className="lg:col-span-2">
                                    <label
                                        htmlFor="schedule-name"
                                        className="mb-2 block text-sm font-extrabold"
                                    >
                                        Schedule Name
                                    </label>
                                    <input
                                        id="schedule-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={150}
                                        required
                                        placeholder="Example: Morning Shift, Weekend Off, Night Shift"
                                        className={inputClass}
                                        aria-invalid={!!errors.name}
                                        aria-describedby={
                                            errors.name
                                                ? 'schedule-name-error'
                                                : undefined
                                        }
                                    />
                                    {errors.name && (
                                        <p
                                            id="schedule-name-error"
                                            className="mt-1 text-xs text-red-700"
                                        >
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className="rounded-2xl border border-[#ead5d5] bg-[#faf5f5] px-5 py-4">
                                    <p className="text-xs text-slate-500 uppercase">
                                        Schedule rows
                                    </p>
                                    <p className="mt-1 text-2xl font-black text-[#e03724]">
                                        7{' '}
                                        <span className="ml-2 text-xs font-medium text-slate-500">
                                            {workingDays} working ·{' '}
                                            {7 - workingDays} off
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div className="rounded-2xl border border-[#e9e9ed] p-4 shadow-sm">
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {fields.map((field) => (
                                        <div key={field}>
                                            <label
                                                htmlFor={`bulk-${field}`}
                                                className="mb-2 block text-sm font-extrabold"
                                            >
                                                {fieldLabels[field]}
                                            </label>
                                            <input
                                                id={`bulk-${field}`}
                                                type="time"
                                                value={bulk[field]}
                                                onChange={(event) =>
                                                    setBulk({
                                                        ...bulk,
                                                        [field]:
                                                            event.target.value,
                                                    })
                                                }
                                                className={timeInputClass}
                                            />
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
                                    <div>
                                        <p className="mb-2 text-sm font-extrabold">
                                            Days
                                        </p>
                                        <div className="flex flex-wrap gap-5">
                                            {dayNames.map((name, i) => (
                                                <label
                                                    key={name}
                                                    className="flex items-center gap-1.5 text-sm font-bold"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedDays.includes(
                                                            i + 1,
                                                        )}
                                                        onChange={() =>
                                                            setSelectedDays(
                                                                selectedDays.includes(
                                                                    i + 1,
                                                                )
                                                                    ? selectedDays.filter(
                                                                          (
                                                                              day,
                                                                          ) =>
                                                                              day !==
                                                                              i +
                                                                                  1,
                                                                      )
                                                                    : [
                                                                          ...selectedDays,
                                                                          i + 1,
                                                                      ],
                                                            )
                                                        }
                                                        className="accent-blue-600"
                                                    />
                                                    {name.slice(0, 3)}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            className={`${buttonClass} min-w-28`}
                                            onClick={() => {
                                                setBulk(emptyTimes());
                                                setSelectedDays([]);
                                                setBulkError('');
                                            }}
                                        >
                                            Clear
                                        </button>
                                        <button
                                            type="button"
                                            className={`${primaryClass} min-w-28`}
                                            onClick={apply}
                                        >
                                            Apply
                                        </button>
                                    </div>
                                </div>
                                {bulkError && (
                                    <p
                                        role="alert"
                                        className="mt-3 text-sm text-red-700"
                                    >
                                        {bulkError}
                                    </p>
                                )}
                            </div>
                            {showDays && (
                                <>
                                    <div className="overflow-x-auto rounded-2xl border border-[#e3e7ef]">
                                        <table className="w-full min-w-[900px] border-collapse text-left text-xs">
                                            <thead>
                                                <tr>
                                                    <th className="border-r border-b border-[#dce1eb] p-3">
                                                        Day
                                                    </th>
                                                    {fields.map((field) => (
                                                        <th
                                                            key={field}
                                                            className="border-r border-b border-[#dce1eb] p-3"
                                                        >
                                                            {fieldLabels[field]}
                                                        </th>
                                                    ))}
                                                    <th className="border-b border-[#dce1eb] p-3">
                                                        No Schedule
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {form.data.days.map(
                                                    (day, index) => (
                                                        <tr
                                                            key={day.day}
                                                            className="last:[&>td]:border-b-0"
                                                        >
                                                            <td className="border-r border-b border-[#dce1eb] p-3 font-extrabold">
                                                                {
                                                                    dayNames[
                                                                        day.day -
                                                                            1
                                                                    ]
                                                                }
                                                            </td>
                                                            {fields.map(
                                                                (field) => (
                                                                    <td
                                                                        key={
                                                                            field
                                                                        }
                                                                        className="border-r border-b border-[#dce1eb] p-2"
                                                                    >
                                                                        <input
                                                                            type="time"
                                                                            aria-label={`${dayNames[day.day - 1]} ${fieldLabels[field]}`}
                                                                            value={
                                                                                day[
                                                                                    field
                                                                                ]
                                                                            }
                                                                            disabled={
                                                                                day.no_schedule
                                                                            }
                                                                            required={
                                                                                !day.no_schedule &&
                                                                                (field ===
                                                                                    'time_in' ||
                                                                                    field ===
                                                                                        'time_out')
                                                                            }
                                                                            aria-invalid={
                                                                                !!errors[
                                                                                    `days.${index}.${field}`
                                                                                ]
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                updateDay(
                                                                                    index,
                                                                                    {
                                                                                        [field]:
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                    },
                                                                                )
                                                                            }
                                                                            className={
                                                                                timeInputClass
                                                                            }
                                                                        />
                                                                        {errors[
                                                                            `days.${index}.${field}`
                                                                        ] && (
                                                                            <p className="mt-1 max-w-56 text-xs text-red-700">
                                                                                {
                                                                                    errors[
                                                                                        `days.${index}.${field}`
                                                                                    ]
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                ),
                                                            )}
                                                            <td className="border-b border-[#dce1eb] p-3">
                                                                <label className="flex items-center gap-2 text-sm font-bold whitespace-nowrap">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={
                                                                            day.no_schedule
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateDay(
                                                                                index,
                                                                                {
                                                                                    no_schedule:
                                                                                        event
                                                                                            .target
                                                                                            .checked,
                                                                                    ...(event
                                                                                        .target
                                                                                        .checked
                                                                                        ? emptyTimes()
                                                                                        : {}),
                                                                                },
                                                                            )
                                                                        }
                                                                        className="accent-blue-600"
                                                                    />
                                                                    No schedule
                                                                    <span className="sr-only">
                                                                        {' '}
                                                                        for{' '}
                                                                        {
                                                                            dayNames[
                                                                                day.day -
                                                                                    1
                                                                            ]
                                                                        }
                                                                    </span>
                                                                </label>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <div className="flex justify-end gap-2 pt-1">
                                        <button
                                            type="button"
                                            onClick={clear}
                                            className={buttonClass}
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Clear Form
                                        </button>
                                        <button
                                            type="submit"
                                            className={primaryClass}
                                        >
                                            <Save className="size-3.5" />
                                            {form.processing
                                                ? 'Saving…'
                                                : editing
                                                  ? 'Update Schedule'
                                                  : 'Save Schedule'}
                                        </button>
                                    </div>
                                </>
                            )}
                        </fieldset>
                    </form>
                ) : (
                    <div
                        id="schedule-list"
                        role="tabpanel"
                        aria-labelledby="schedule-list-tab"
                        className="space-y-4"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-slate-500">
                                {schedules.length} saved schedules
                            </p>
                            <label className="relative w-full sm:w-72">
                                <Search className="absolute top-3 left-3 size-4 text-slate-400" />
                                <span className="sr-only">
                                    Search schedules
                                </span>
                                <input
                                    value={scheduleSearch}
                                    onChange={(event) =>
                                        setScheduleSearch(event.target.value)
                                    }
                                    placeholder="Search schedules"
                                    className={`${inputClass} pl-9`}
                                />
                            </label>
                        </div>
                        <div className="overflow-x-auto rounded-2xl border border-[#e3e7ef]">
                            <table className="w-full min-w-[760px] text-left text-xs">
                                <thead>
                                    <tr className="border-b border-[#dce1eb]">
                                        <th className="p-3">Schedule Name</th>
                                        <th className="p-3">Included Days</th>
                                        <th className="p-3">Total Rows</th>
                                        <th className="p-3">Employees</th>
                                        <th className="p-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visibleSchedules.map((schedule) => (
                                        <tr
                                            key={schedule.id}
                                            className="border-b border-[#dce1eb] last:border-0 hover:bg-slate-50/70"
                                        >
                                            <td className="p-3 font-extrabold">
                                                {schedule.name}
                                            </td>
                                            <td className="p-3">
                                                {schedule.days
                                                    .filter(
                                                        (day) =>
                                                            !day.no_schedule,
                                                    )
                                                    .map(
                                                        (day) =>
                                                            dayNames[
                                                                day.day - 1
                                                            ],
                                                    )
                                                    .join(', ') ||
                                                    'All days off'}
                                            </td>
                                            <td className="p-3">
                                                {schedule.days.length}
                                            </td>
                                            <td className="p-3">
                                                {schedule.employees_count}
                                            </td>
                                            <td className="p-3">
                                                <div className="flex justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        title="Assign employees"
                                                        aria-label={`Assign employees to ${schedule.name}`}
                                                        onClick={() =>
                                                            openAssign(schedule)
                                                        }
                                                        className="rounded-full border border-emerald-600 p-1.5 text-emerald-600 hover:bg-emerald-50"
                                                    >
                                                        <UserRoundCheck className="size-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        title="Edit schedule"
                                                        aria-label={`Edit ${schedule.name}`}
                                                        onClick={() =>
                                                            edit(schedule)
                                                        }
                                                        className="rounded-full border border-blue-500 p-1.5 text-blue-500 hover:bg-blue-50"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        title="Delete schedule"
                                                        aria-label={`Delete ${schedule.name}`}
                                                        onClick={() => {
                                                            setDeleting(
                                                                schedule,
                                                            );
                                                            setDeleteError('');
                                                        }}
                                                        className="rounded-full border border-red-500 p-1.5 text-red-500 hover:bg-red-50"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!visibleSchedules.length && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="p-12 text-center text-sm text-slate-500"
                                            >
                                                {scheduleSearch
                                                    ? 'No schedules match your search.'
                                                    : 'No schedules yet. Use Add / Edit Schedule to create your first weekly schedule.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </section>
            <Dialog
                open={!!assigning}
                onClose={() => {
                    if (!assignment.processing) {
                        setAssigning(null);
                    }
                }}
                fullWidth
                maxWidth="md"
                aria-labelledby="assign-schedule-title"
            >
                <DialogTitle id="assign-schedule-title">
                    <span className="flex items-center gap-2">
                        <UserRoundCheck className="size-5 text-emerald-600" />
                        Assign employees — {assigning?.name}
                    </span>
                </DialogTitle>
                <DialogContent>
                    <p className="mb-4 text-sm text-slate-600">
                        Select employees for this weekly schedule. Unchecking an
                        assigned employee removes their assignment. Selecting
                        someone from another schedule moves them here.
                    </p>
                    {!!Object.keys(assignment.errors).length && (
                        <Alert severity="error" className="mb-3">
                            {Object.values(assignment.errors).join(' ')}
                        </Alert>
                    )}
                    <label
                        htmlFor="schedule-employee-search"
                        className="mb-1 block text-xs font-bold"
                    >
                        Find employee, ID, team, campaign, or role
                    </label>
                    <input
                        id="schedule-employee-search"
                        value={employeeSearch}
                        onChange={(event) =>
                            setEmployeeSearch(event.target.value)
                        }
                        className={inputClass}
                    />
                    <div className="my-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <strong>
                            {assignment.data.employee_ids.length} selected
                        </strong>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                disabled={assignment.processing}
                                onClick={() =>
                                    assignment.setData('employee_ids', [
                                        ...new Set([
                                            ...assignment.data.employee_ids,
                                            ...visibleEmployees.map(
                                                (employee) => employee.id,
                                            ),
                                        ]),
                                    ])
                                }
                                className="font-bold text-emerald-700"
                            >
                                Select visible
                            </button>
                            <button
                                type="button"
                                disabled={assignment.processing}
                                onClick={() =>
                                    assignment.setData('employee_ids', [])
                                }
                                className="font-bold text-slate-600"
                            >
                                Clear selection
                            </button>
                        </div>
                    </div>
                    <div className="max-h-80 overflow-y-auto rounded-xl border border-slate-200">
                        {visibleEmployees.map((employee) => (
                            <label
                                key={employee.id}
                                className="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50"
                            >
                                <input
                                    type="checkbox"
                                    checked={assignment.data.employee_ids.includes(
                                        employee.id,
                                    )}
                                    disabled={assignment.processing}
                                    onChange={() => toggleEmployee(employee.id)}
                                    className="size-4 accent-emerald-600"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold">
                                        {employee.name}{' '}
                                        <span className="font-normal text-slate-500">
                                            {employee.username}
                                        </span>
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {[
                                            employee.role.replaceAll('_', ' '),
                                            employee.team,
                                            employee.campaign,
                                            employee.status !== 'active'
                                                ? employee.status
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <span className="text-right text-xs text-slate-500">
                                    {employee.schedule_name ??
                                        'No assigned schedule'}
                                </span>
                            </label>
                        ))}
                        {!visibleEmployees.length && (
                            <p className="p-8 text-center text-sm text-slate-500">
                                No eligible employees match your search.
                            </p>
                        )}
                    </div>
                    {!!transfers.length && (
                        <Alert severity="warning" className="mt-3">
                            Saving will move {transfers.length} selected{' '}
                            {transfers.length === 1 ? 'employee' : 'employees'}{' '}
                            from their current schedule to {assigning?.name}.
                        </Alert>
                    )}
                </DialogContent>
                <DialogActions className="gap-2 p-4">
                    <button
                        type="button"
                        disabled={assignment.processing}
                        onClick={() => setAssigning(null)}
                        className={buttonClass}
                    >
                        <X className="size-3.5" />
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={assignment.processing}
                        onClick={() =>
                            assigning &&
                            assignment.put(
                                `/campaign-schedules/${assigning.id}/employees`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setAssigning(null),
                                },
                            )
                        }
                        className={primaryClass}
                    >
                        <Check className="size-3.5" />
                        {assignment.processing ? 'Saving…' : 'Save Assignments'}
                    </button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!deleting}
                onClose={() => {
                    if (!deletingBusy) {
                        setDeleting(null);
                    }
                }}
                fullWidth
                maxWidth="xs"
                aria-labelledby="delete-schedule-title"
            >
                <DialogTitle id="delete-schedule-title">
                    Delete schedule?
                </DialogTitle>
                <DialogContent>
                    <p className="text-sm">
                        Delete <strong>{deleting?.name}</strong> and its seven
                        daily rows? {deleting?.employees_count || 0} employees
                        will be unassigned. Their employee records will be kept.
                    </p>
                    {deleteError && (
                        <Alert severity="error" className="mt-3">
                            {deleteError}
                        </Alert>
                    )}
                </DialogContent>
                <DialogActions className="gap-2 p-4">
                    <button
                        type="button"
                        disabled={deletingBusy}
                        onClick={() => setDeleting(null)}
                        className={buttonClass}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={deletingBusy}
                        className={primaryClass}
                        onClick={() => {
                            if (!deleting) {
                                return;
                            }

                            router.delete(
                                `/campaign-schedules/${deleting.id}`,
                                {
                                    preserveScroll: true,
                                    onStart: () => setDeletingBusy(true),
                                    onSuccess: () => {
                                        if (editing === deleting.id) {
                                            clear();
                                        }

                                        setDeleting(null);
                                    },
                                    onError: (error) =>
                                        setDeleteError(
                                            Object.values(error).join(' '),
                                        ),
                                    onFinish: () => setDeletingBusy(false),
                                },
                            );
                        }}
                    >
                        <Trash2 className="size-3.5" />
                        {deletingBusy ? 'Deleting…' : 'Delete Schedule'}
                    </button>
                </DialogActions>
            </Dialog>
        </main>
    );
}

CampaignSchedules.layout = {
    breadcrumbs: [{ title: 'Campaign Schedules', href: '/campaign-schedules' }],
};
