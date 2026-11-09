import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarClock,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    MailWarning,
    MapPin,
    Plus,
    RefreshCw,
    UserRoundCheck,
    UserRoundX,
    Video,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

type Applicant = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    position: string;
    applicant_stage: string;
};

type Interviewer = {
    id: number;
    name: string;
    email: string;
    role: string;
};

type Event = {
    id: number;
    event_type: string;
    previous_starts_at: string | null;
    new_starts_at: string | null;
    notes: string | null;
    created_at: string;
    actor_name: string | null;
};

type Appointment = {
    id: number;
    job_application_id: number;
    interviewer_id: number;
    interview_type: 'screening' | 'final_interview';
    starts_at: string;
    ends_at: string;
    timezone: string;
    location: string | null;
    meeting_url: string | null;
    status: 'scheduled' | 'rescheduled' | 'completed' | 'no_show' | 'cancelled';
    calendar_sequence: number;
    reschedule_count: number;
    notes: string | null;
    applicant_invite_status: string;
    interviewer_invite_status: string;
    invited_at: string | null;
    application: Applicant;
    interviewer: Interviewer;
    events: Event[];
};

type Props = {
    appointments: Appointment[];
    applicants: Applicant[];
    interviewers: Interviewer[];
    month: string;
    status: string;
    summary: {
        scheduled: number;
        completed: number;
        no_show: number;
        needs_invite: number;
    };
    emailConfigured: boolean;
};

type Slot = { value: string; label: string };

const pretty = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));

const time = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));

const parts = (value: string | Date) => {
    const mapped = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
            timeZone: 'Asia/Manila',
        })
            .formatToParts(new Date(value))
            .map((part) => [part.type, part.value]),
    );
    return {
        date: `${mapped.year}-${mapped.month}-${mapped.day}`,
        local: `${mapped.year}-${mapped.month}-${mapped.day}T${mapped.hour}:${mapped.minute}`,
    };
};

const formDefaults = {
    job_application_id: '',
    interviewer_id: '',
    interview_type: 'screening',
    date: '',
    starts_at: '',
    duration: '60',
    location: '',
    meeting_url: '',
    notes: '',
};

export default function InterviewCalendar({
    appointments,
    applicants,
    interviewers,
    month,
    status,
    summary,
    emailConfigured,
}: Props) {
    const [scheduler, setScheduler] = useState<'new' | Appointment | null>(
        null,
    );
    const [detail, setDetail] = useState<Appointment | null>(null);
    const [slots, setSlots] = useState<Slot[]>([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [statusNotes, setStatusNotes] = useState('');
    const form = useForm(formDefaults);

    const [year, monthNumber] = month.split('-').map(Number);
    const first = new Date(`${month}-01T00:00:00+08:00`);
    const daysInMonth = new Date(year, monthNumber, 0).getDate();
    const mondayOffset = (first.getDay() + 6) % 7;
    const calendarCells = [
        ...Array.from({ length: mondayOffset }, () => null),
        ...Array.from({ length: daysInMonth }, (_, index) => index + 1),
    ];
    while (calendarCells.length % 7) calendarCells.push(null);

    const grouped = useMemo(() => {
        const map = new Map<string, Appointment[]>();
        appointments.forEach((appointment) => {
            const key = parts(appointment.starts_at).date;
            map.set(key, [...(map.get(key) ?? []), appointment]);
        });
        return map;
    }, [appointments]);

    useEffect(() => {
        if (!scheduler || !form.data.interviewer_id || !form.data.date) {
            setSlots([]);
            return;
        }
        const controller = new AbortController();
        const params = new URLSearchParams({
            interviewer_id: form.data.interviewer_id,
            date: form.data.date,
            duration: form.data.duration,
        });
        if (scheduler !== 'new') params.set('exclude', String(scheduler.id));
        setLoadingSlots(true);
        fetch(`/management/interview-calendar/availability?${params}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('Unable to load slots');
                return response.json() as Promise<{ slots: Slot[] }>;
            })
            .then((payload) => setSlots(payload.slots))
            .catch((error: Error) => {
                if (error.name !== 'AbortError') setSlots([]);
            })
            .finally(() => setLoadingSlots(false));
        return () => controller.abort();
    }, [
        form.data.date,
        form.data.duration,
        form.data.interviewer_id,
        scheduler,
    ]);

    const navigateMonth = (change: number) => {
        const next = new Date(year, monthNumber - 1 + change, 1);
        const value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
        router.get(
            '/management/interview-calendar',
            { month: value, status },
            { preserveState: true },
        );
    };

    const openNew = (date?: string) => {
        form.clearErrors();
        form.setData({ ...formDefaults, date: date ?? '' });
        setScheduler('new');
    };

    const openReschedule = (appointment: Appointment) => {
        const local = parts(appointment.starts_at);
        const duration = Math.round(
            (new Date(appointment.ends_at).getTime() -
                new Date(appointment.starts_at).getTime()) /
                60000,
        );
        form.clearErrors();
        form.setData({
            job_application_id: String(appointment.job_application_id),
            interviewer_id: String(appointment.interviewer_id),
            interview_type: appointment.interview_type,
            date: local.date,
            starts_at: local.local,
            duration: String(duration),
            location: appointment.location ?? '',
            meeting_url: appointment.meeting_url ?? '',
            notes: appointment.notes ?? '',
        });
        setDetail(null);
        setScheduler(appointment);
    };

    const selectApplicant = (id: string) => {
        const applicant = applicants.find((item) => String(item.id) === id);
        form.setData({
            ...form.data,
            job_application_id: id,
            interview_type:
                applicant?.applicant_stage === 'for_final_interview'
                    ? 'final_interview'
                    : 'screening',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const url =
            scheduler === 'new'
                ? '/management/interview-calendar'
                : `/management/interview-calendar/${(scheduler as Appointment).id}/reschedule`;
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => setScheduler(null),
        });
    };

    const setOutcome = (appointment: Appointment, nextStatus: string) =>
        router.patch(
            `/management/interview-calendar/${appointment.id}/status`,
            { status: nextStatus, notes: statusNotes },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDetail(null);
                    setStatusNotes('');
                },
            },
        );

    return (
        <>
            <Head title="Interview Calendar" />
            <main className="min-h-full bg-[linear-gradient(135deg,#fff7f7_0%,#f8fafc_58%,#fff_100%)] px-5 py-8 lg:px-10">
                <div className="mx-auto max-w-[1650px] space-y-6">
                    <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                                Recruitment operations
                            </p>
                            <h1 className="flex items-center gap-3 text-3xl font-black text-slate-950">
                                <CalendarCheck className="size-8 text-red-700" />
                                Interview Scheduling Calendar
                            </h1>
                            <p className="mt-2 text-slate-600">
                                Assign interviewers, use conflict-free slots,
                                and keep applicants and calendars updated.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => openNew()}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 text-sm font-bold text-white shadow-lg shadow-red-900/15 hover:bg-red-800"
                        >
                            <Plus className="size-4" /> Schedule interview
                        </button>
                    </header>

                    {!emailConfigured && (
                        <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                            <div>
                                <b>Calendar email is not configured.</b>{' '}
                                Scheduling still works, and failed invitations
                                remain visible for retry after mail is
                                configured.
                            </div>
                        </div>
                    )}

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            [
                                'Upcoming',
                                summary.scheduled,
                                CalendarClock,
                                'text-blue-700',
                            ],
                            [
                                'Completed',
                                summary.completed,
                                CheckCircle2,
                                'text-emerald-700',
                            ],
                            [
                                'No-shows',
                                summary.no_show,
                                UserRoundX,
                                'text-red-700',
                            ],
                            [
                                'Invitation attention',
                                summary.needs_invite,
                                MailWarning,
                                'text-amber-700',
                            ],
                        ].map(([label, value, Icon, color]) => {
                            const CardIcon = Icon as typeof CalendarClock;
                            return (
                                <article
                                    key={String(label)}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-500">
                                                {String(label)}
                                            </p>
                                            <p className="mt-1 text-3xl font-black text-slate-950">
                                                {Number(value)}
                                            </p>
                                        </div>
                                        <CardIcon
                                            className={`size-7 ${String(color)}`}
                                        />
                                    </div>
                                </article>
                            );
                        })}
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => navigateMonth(-1)}
                                    className="rounded-lg border border-slate-300 p-2 hover:bg-slate-50"
                                    aria-label="Previous month"
                                >
                                    <ChevronLeft className="size-5" />
                                </button>
                                <h2 className="min-w-44 text-center text-lg font-black text-slate-900">
                                    {new Intl.DateTimeFormat('en-PH', {
                                        month: 'long',
                                        year: 'numeric',
                                        timeZone: 'Asia/Manila',
                                    }).format(first)}
                                </h2>
                                <button
                                    type="button"
                                    onClick={() => navigateMonth(1)}
                                    className="rounded-lg border border-slate-300 p-2 hover:bg-slate-50"
                                    aria-label="Next month"
                                >
                                    <ChevronRight className="size-5" />
                                </button>
                            </div>
                            <select
                                value={status}
                                onChange={(event) =>
                                    router.get(
                                        '/management/interview-calendar',
                                        { month, status: event.target.value },
                                        { preserveState: true },
                                    )
                                }
                                className="h-10 rounded-xl border border-slate-300 bg-slate-50 px-3 font-semibold text-slate-700"
                            >
                                <option value="">All statuses</option>
                                {[
                                    'scheduled',
                                    'rescheduled',
                                    'completed',
                                    'no_show',
                                    'cancelled',
                                ].map((item) => (
                                    <option key={item} value={item}>
                                        {pretty(item)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-bold tracking-wider text-slate-500 uppercase">
                            {[
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                                'Sun',
                            ].map((day) => (
                                <div key={day} className="p-3">
                                    {day}
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7">
                            {calendarCells.map((day, index) => {
                                const key = day
                                    ? `${month}-${String(day).padStart(2, '0')}`
                                    : '';
                                const dayAppointments = key
                                    ? (grouped.get(key) ?? [])
                                    : [];
                                return (
                                    <div
                                        key={`${index}-${day ?? 'blank'}`}
                                        className={`min-h-32 border-r border-b border-slate-100 p-2 ${day ? 'bg-white' : 'bg-slate-50/70'}`}
                                    >
                                        {day && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => openNew(key)}
                                                    className="mb-2 flex size-7 items-center justify-center rounded-full text-xs font-bold text-slate-700 hover:bg-red-50 hover:text-red-700"
                                                    title="Schedule on this date"
                                                >
                                                    {day}
                                                </button>
                                                <div className="space-y-1.5">
                                                    {dayAppointments
                                                        .slice(0, 4)
                                                        .map((appointment) => (
                                                            <button
                                                                type="button"
                                                                key={
                                                                    appointment.id
                                                                }
                                                                onClick={() =>
                                                                    setDetail(
                                                                        appointment,
                                                                    )
                                                                }
                                                                className={`w-full rounded-lg border px-2 py-1.5 text-left text-[11px] leading-4 ${appointment.status === 'cancelled' ? 'border-slate-200 bg-slate-100 text-slate-500 line-through' : appointment.status === 'no_show' ? 'border-red-200 bg-red-50 text-red-800' : appointment.status === 'completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-blue-200 bg-blue-50 text-blue-900'}`}
                                                            >
                                                                <b>
                                                                    {time(
                                                                        appointment.starts_at,
                                                                    )}
                                                                </b>
                                                                <span className="block truncate">
                                                                    {
                                                                        appointment
                                                                            .application
                                                                            .first_name
                                                                    }{' '}
                                                                    {
                                                                        appointment
                                                                            .application
                                                                            .last_name
                                                                    }
                                                                </span>
                                                            </button>
                                                        ))}
                                                    {dayAppointments.length >
                                                        4 && (
                                                        <p className="px-1 text-[11px] font-bold text-slate-500">
                                                            +
                                                            {dayAppointments.length -
                                                                4}{' '}
                                                            more
                                                        </p>
                                                    )}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h2 className="font-black text-slate-900">
                                Monthly agenda
                            </h2>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {appointments.map((appointment) => (
                                <button
                                    key={appointment.id}
                                    type="button"
                                    onClick={() => setDetail(appointment)}
                                    className="grid w-full gap-3 px-5 py-4 text-left hover:bg-slate-50 md:grid-cols-[190px_1fr_1fr_150px]"
                                >
                                    <div className="font-bold text-slate-900">
                                        {dateTime(appointment.starts_at)}
                                    </div>
                                    <div>
                                        <p className="font-bold text-slate-900">
                                            {appointment.application.first_name}{' '}
                                            {appointment.application.last_name}
                                        </p>
                                        <p className="text-sm text-slate-500">
                                            {appointment.application.position}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="font-semibold text-slate-800">
                                            {appointment.interviewer.name}
                                        </p>
                                        <p className="text-sm text-slate-500">
                                            {pretty(appointment.interview_type)}
                                        </p>
                                    </div>
                                    <div>
                                        <StatusBadge
                                            status={appointment.status}
                                        />
                                    </div>
                                </button>
                            ))}
                            {appointments.length === 0 && (
                                <div className="py-14 text-center text-slate-500">
                                    No interviews scheduled for this month.
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </main>

            {scheduler && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                >
                    <form
                        onSubmit={submit}
                        className="flex max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden rounded-[28px] bg-white shadow-2xl"
                    >
                        <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h2 className="text-xl font-black text-slate-950">
                                    {scheduler === 'new'
                                        ? 'Schedule interview'
                                        : 'Reschedule interview'}
                                </h2>
                                <p className="text-sm text-slate-500">
                                    Times are shown in Asia/Manila. Only
                                    conflict-free slots can be selected.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setScheduler(null)}
                                className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                            >
                                <X className="size-5" />
                            </button>
                        </header>
                        <div className="overflow-y-auto p-6">
                            <div className="grid gap-5 rounded-2xl border border-red-100 bg-red-50/40 p-5 md:grid-cols-2">
                                <Field
                                    label="Applicant"
                                    error={form.errors.job_application_id}
                                >
                                    <select
                                        disabled={scheduler !== 'new'}
                                        value={form.data.job_application_id}
                                        onChange={(event) =>
                                            selectApplicant(event.target.value)
                                        }
                                        className="control"
                                    >
                                        <option value="">
                                            Select applicant
                                        </option>
                                        {applicants.map((applicant) => (
                                            <option
                                                key={applicant.id}
                                                value={applicant.id}
                                            >
                                                {applicant.first_name}{' '}
                                                {applicant.last_name} ·{' '}
                                                {applicant.position}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Interview type"
                                    error={form.errors.interview_type}
                                >
                                    <select
                                        value={form.data.interview_type}
                                        onChange={(event) =>
                                            form.setData(
                                                'interview_type',
                                                event.target.value,
                                            )
                                        }
                                        className="control"
                                    >
                                        <option value="screening">
                                            Screening interview
                                        </option>
                                        <option value="final_interview">
                                            Final interview
                                        </option>
                                    </select>
                                </Field>
                                <Field
                                    label="Assigned interviewer"
                                    error={form.errors.interviewer_id}
                                >
                                    <select
                                        value={form.data.interviewer_id}
                                        onChange={(event) =>
                                            form.setData({
                                                ...form.data,
                                                interviewer_id:
                                                    event.target.value,
                                                starts_at: '',
                                            })
                                        }
                                        className="control"
                                    >
                                        <option value="">
                                            Select interviewer
                                        </option>
                                        {interviewers.map((interviewer) => (
                                            <option
                                                key={interviewer.id}
                                                value={interviewer.id}
                                            >
                                                {interviewer.name} ·{' '}
                                                {pretty(interviewer.role)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Duration"
                                    error={form.errors.duration}
                                >
                                    <select
                                        value={form.data.duration}
                                        onChange={(event) =>
                                            form.setData({
                                                ...form.data,
                                                duration: event.target.value,
                                                starts_at: '',
                                            })
                                        }
                                        className="control"
                                    >
                                        {[30, 45, 60, 90, 120].map(
                                            (minutes) => (
                                                <option
                                                    key={minutes}
                                                    value={minutes}
                                                >
                                                    {minutes} minutes
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </Field>
                                <Field
                                    label="Interview date"
                                    error={
                                        form.errors.date ||
                                        form.errors.starts_at
                                    }
                                >
                                    <input
                                        type="date"
                                        value={form.data.date}
                                        onChange={(event) =>
                                            form.setData({
                                                ...form.data,
                                                date: event.target.value,
                                                starts_at: '',
                                            })
                                        }
                                        className="control"
                                    />
                                </Field>
                                <Field
                                    label="Available time"
                                    error={form.errors.starts_at}
                                >
                                    <select
                                        value={form.data.starts_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'starts_at',
                                                event.target.value,
                                            )
                                        }
                                        className="control"
                                    >
                                        <option value="">
                                            {loadingSlots
                                                ? 'Checking availability…'
                                                : 'Select an available time'}
                                        </option>
                                        {slots.map((slot) => (
                                            <option
                                                key={slot.value}
                                                value={slot.value}
                                            >
                                                {slot.label}
                                            </option>
                                        ))}
                                    </select>
                                    {!loadingSlots &&
                                        form.data.date &&
                                        form.data.interviewer_id &&
                                        slots.length === 0 && (
                                            <span className="text-xs text-amber-700">
                                                No available future slots from
                                                9:00 AM–6:00 PM.
                                            </span>
                                        )}
                                </Field>
                                <Field
                                    label="Location"
                                    error={form.errors.location}
                                >
                                    <input
                                        value={form.data.location}
                                        onChange={(event) =>
                                            form.setData(
                                                'location',
                                                event.target.value,
                                            )
                                        }
                                        className="control"
                                        placeholder="Office, interview room, or address"
                                    />
                                </Field>
                                <Field
                                    label="Online meeting link"
                                    error={form.errors.meeting_url}
                                >
                                    <input
                                        type="url"
                                        value={form.data.meeting_url}
                                        onChange={(event) =>
                                            form.setData(
                                                'meeting_url',
                                                event.target.value,
                                            )
                                        }
                                        className="control"
                                        placeholder="https://meet…"
                                    />
                                </Field>
                                <Field
                                    label="Internal notes"
                                    error={form.errors.notes}
                                >
                                    <textarea
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        className="control min-h-24 py-3"
                                        placeholder="Preparation notes or instructions"
                                    />
                                </Field>
                            </div>
                        </div>
                        <footer className="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                            <button
                                type="button"
                                onClick={() => setScheduler(null)}
                                className="h-11 rounded-xl border border-slate-300 px-5 font-bold text-slate-700"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={form.processing}
                                className="h-11 rounded-xl bg-red-700 px-6 font-bold text-white disabled:opacity-50"
                            >
                                {form.processing
                                    ? 'Saving…'
                                    : scheduler === 'new'
                                      ? 'Schedule & send invites'
                                      : 'Reschedule & update invites'}
                            </button>
                        </footer>
                    </form>
                </div>
            )}

            {detail && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                >
                    <div className="flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-[28px] bg-white shadow-2xl">
                        <header className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h2 className="text-xl font-black text-slate-950">
                                    {detail.application.first_name}{' '}
                                    {detail.application.last_name}
                                </h2>
                                <p className="text-sm text-slate-500">
                                    {pretty(detail.interview_type)} ·{' '}
                                    {detail.application.position}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setDetail(null)}
                                className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                            >
                                <X className="size-5" />
                            </button>
                        </header>
                        <div className="space-y-5 overflow-y-auto p-6">
                            <div className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:grid-cols-2">
                                <Info
                                    icon={Clock3}
                                    label="Schedule"
                                    value={`${dateTime(detail.starts_at)} – ${time(detail.ends_at)}`}
                                />
                                <Info
                                    icon={UserRoundCheck}
                                    label="Interviewer"
                                    value={detail.interviewer.name}
                                />
                                <Info
                                    icon={MapPin}
                                    label="Location"
                                    value={detail.location || 'Not specified'}
                                />
                                <Info
                                    icon={Video}
                                    label="Meeting"
                                    value={
                                        detail.meeting_url || 'No online link'
                                    }
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <StatusBadge status={detail.status} />
                                <InviteBadge
                                    label="Applicant invite"
                                    value={detail.applicant_invite_status}
                                />
                                <InviteBadge
                                    label="Interviewer invite"
                                    value={detail.interviewer_invite_status}
                                />
                                {detail.reschedule_count > 0 && (
                                    <span className="rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-purple-800">
                                        Rescheduled {detail.reschedule_count}×
                                    </span>
                                )}
                            </div>
                            {detail.notes && (
                                <div className="rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                                    <b>Notes:</b> {detail.notes}
                                </div>
                            )}
                            {detail.events.length > 0 && (
                                <div>
                                    <h3 className="mb-2 font-black text-slate-900">
                                        Activity history
                                    </h3>
                                    <div className="space-y-2">
                                        {detail.events.map((event) => (
                                            <div
                                                key={event.id}
                                                className="rounded-xl border border-slate-200 p-3 text-sm"
                                            >
                                                <div className="flex justify-between gap-3">
                                                    <b>
                                                        {pretty(
                                                            event.event_type,
                                                        )}
                                                    </b>
                                                    <span className="text-xs text-slate-500">
                                                        {dateTime(
                                                            event.created_at,
                                                        )}
                                                    </span>
                                                </div>
                                                <p className="text-slate-500">
                                                    {event.actor_name ||
                                                        'System'}
                                                    {event.notes
                                                        ? ` · ${event.notes}`
                                                        : ''}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {['scheduled', 'rescheduled'].includes(
                                detail.status,
                            ) && (
                                <textarea
                                    value={statusNotes}
                                    onChange={(event) =>
                                        setStatusNotes(event.target.value)
                                    }
                                    className="control min-h-20 py-3"
                                    placeholder="Optional outcome, cancellation, or no-show notes"
                                />
                            )}
                        </div>
                        <footer className="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">
                            {['scheduled', 'rescheduled'].includes(
                                detail.status,
                            ) && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                `/management/interview-calendar/${detail.id}/retry`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700"
                                    >
                                        <RefreshCw className="size-4" /> Retry
                                        invites
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => openReschedule(detail)}
                                        className="h-10 rounded-xl border border-blue-300 px-4 text-sm font-bold text-blue-700"
                                    >
                                        Reschedule
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setOutcome(detail, 'no_show')
                                        }
                                        className="h-10 rounded-xl border border-amber-300 px-4 text-sm font-bold text-amber-800"
                                    >
                                        No-show
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setOutcome(detail, 'cancelled')
                                        }
                                        className="h-10 rounded-xl border border-red-300 px-4 text-sm font-bold text-red-700"
                                    >
                                        Cancel interview
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setOutcome(detail, 'completed')
                                        }
                                        className="h-10 rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white"
                                    >
                                        Mark completed
                                    </button>
                                </>
                            )}
                        </footer>
                    </div>
                </div>
            )}
            <style>{`.control{height:3rem;width:100%;border-radius:.85rem;border:1px solid #cbd5e1;background:white;padding:0 .9rem;color:#0f172a;outline:none}.control:focus{border-color:#dc2626;box-shadow:0 0 0 3px #fee2e2}.control:disabled{background:#f1f5f9;color:#64748b}`}</style>
        </>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="space-y-1.5">
            <span className="text-sm font-bold text-slate-700">{label}</span>
            {children}
            {error && (
                <span className="block text-xs font-semibold text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function StatusBadge({ status }: { status: Appointment['status'] }) {
    const styles = {
        scheduled: 'bg-blue-100 text-blue-800',
        rescheduled: 'bg-purple-100 text-purple-800',
        completed: 'bg-emerald-100 text-emerald-800',
        no_show: 'bg-red-100 text-red-800',
        cancelled: 'bg-slate-200 text-slate-700',
    };
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-black ${styles[status]}`}
        >
            {pretty(status)}
        </span>
    );
}

function InviteBadge({ label, value }: { label: string; value: string }) {
    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-bold ${value === 'sent' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}
        >
            {label}: {pretty(value)}
        </span>
    );
}

function Info({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Clock3;
    label: string;
    value: string;
}) {
    return (
        <div className="flex gap-3">
            <Icon className="mt-0.5 size-5 shrink-0 text-red-700" />
            <div>
                <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">
                    {label}
                </p>
                <p className="font-semibold text-slate-900">{value}</p>
            </div>
        </div>
    );
}

InterviewCalendar.layout = {
    breadcrumbs: [
        { title: 'Interview Calendar', href: '/management/interview-calendar' },
    ],
};
