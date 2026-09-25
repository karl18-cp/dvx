import {
    Alert,
    CircularProgress,
    Dialog,
    DialogContent,
    DialogTitle,
    IconButton,
} from '@mui/material';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

type AttendanceDay = {
    date: string;
    status: string;
    schedule: string | null;
    totalMinutes: number | null;
    leaveMinutes: number | null;
    times: { field: string; actual: string | null; credited: string | null }[];
};
const labels: Record<string, string> = {
    time_in: 'Time in',
    lunch_out: 'Break out',
    lunch_in: 'Break in',
    time_out: 'Time out',
};
const time = (value: string | null) =>
    value
        ? new Date(value).toLocaleTimeString('en-US', {
              timeZone: 'Asia/Manila',
              hour: '2-digit',
              minute: '2-digit',
          })
        : 'Not recorded';
const hours = (minutes: number) =>
    `${Math.floor(minutes / 60)}h ${minutes % 60}m`;

export default function AttendanceRecordsDialog({
    initialDate,
    onClose,
}: {
    initialDate: string;
    onClose: () => void;
}) {
    const [date, setDate] = useState(initialDate);
    const [record, setRecord] = useState<AttendanceDay | null>(null);
    const [error, setError] = useState<{
        date: string;
        message: string;
    } | null>(null);
    useEffect(() => {
        const controller = new AbortController();
        void fetch(`/my-attendance/records?date=${encodeURIComponent(date)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                const body = await response.json();

                if (!response.ok) {
                    throw new Error(
                        body.message ||
                            'Unable to load your attendance records.',
                    );
                }

                if (!controller.signal.aborted) {
                    setRecord(body);
                }
            })
            .catch((failure) => {
                if (!controller.signal.aborted) {
                    setError({
                        date,
                        message:
                            failure instanceof Error
                                ? failure.message
                                : 'Unable to load attendance.',
                    });
                }
            });

        return () => controller.abort();
    }, [date]);

    return (
        <Dialog
            open
            onClose={onClose}
            fullWidth
            maxWidth="md"
            scroll="paper"
            aria-labelledby="attendance-records-title"
        >
            <DialogTitle
                id="attendance-records-title"
                className="flex items-center justify-between gap-3"
            >
                <span>My attendance records</span>
                <IconButton
                    aria-label="Close attendance records"
                    onClick={onClose}
                >
                    <X />
                </IconButton>
            </DialogTitle>
            <DialogContent dividers>
                <label className="mb-5 flex max-w-xs flex-col gap-2 text-sm font-semibold">
                    Attendance date
                    <input
                        type="date"
                        value={date}
                        onChange={(event) => {
                            if (event.target.value) {
                                setError(null);
                                setDate(event.target.value);
                            }
                        }}
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-slate-900"
                    />
                </label>
                {error?.date === date ? (
                    <Alert severity="error">{error.message}</Alert>
                ) : record?.date !== date ? (
                    <div
                        className="flex items-center justify-center gap-3 py-12"
                        role="status"
                    >
                        <CircularProgress size={24} />
                        Loading your attendance…
                    </div>
                ) : (
                    <>
                        <div className="mb-5 flex flex-wrap justify-between gap-4 rounded-xl bg-red-50 p-4">
                            <div>
                                <p className="font-semibold">
                                    {record.schedule || 'No assigned schedule'}
                                </p>
                                <p className="mt-1 text-sm text-slate-600 capitalize">
                                    {record.status.replaceAll('_', ' ')}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-slate-600">
                                    Credited total
                                </p>
                                <p className="font-bold text-red-800">
                                    {record.totalMinutes === null
                                        ? 'Not recorded'
                                        : hours(record.totalMinutes)}
                                </p>
                            </div>
                        </div>
                        <div className="overflow-x-auto rounded-xl border border-slate-200">
                            <table className="w-full min-w-[440px] text-left text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[
                                            'Attendance',
                                            'Actual punch',
                                            'Credited time',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                className="px-4 py-3 font-semibold"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {record.times.map((row) => (
                                        <tr
                                            key={row.field}
                                            className="border-t border-slate-200"
                                        >
                                            <th
                                                scope="row"
                                                className="px-4 py-4 font-medium"
                                            >
                                                {labels[row.field]}
                                            </th>
                                            <td className="px-4 py-4">
                                                {time(row.actual)}
                                            </td>
                                            <td className="px-4 py-4">
                                                {time(row.credited)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {!!record.leaveMinutes && (
                            <p className="mt-4 text-sm text-sky-700">
                                Paid leave credit: {hours(record.leaveMinutes)}
                            </p>
                        )}
                        <p className="mt-4 text-xs text-slate-500">
                            Times are in Asia/Manila. Credited hours follow your
                            schedule and approved requests, with breaks
                            deducted.
                        </p>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
