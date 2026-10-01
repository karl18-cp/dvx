import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    TextField,
} from '@mui/material';
import { X } from 'lucide-react';

export type ManualHours = {
    total_minutes: number;
    overtime_minutes: number;
    night_minutes: number;
    reason: string;
    actor_id: number;
    updated_at: string;
};
export default function ManualAttendanceHours({
    employee,
    date,
    onClose,
}: {
    employee: {
        id: number;
        name: string;
        totalMinutes: number | null;
        manualHours: ManualHours | null;
    };
    date: string;
    onClose: () => void;
}) {
    const form = useForm({
        attendance_date: date,
        clear: false,
        total_hours: String(
            (employee.manualHours?.total_minutes ??
                employee.totalMinutes ??
                0) / 60,
        ),
        overtime_hours: String(
            (employee.manualHours?.overtime_minutes ?? 0) / 60,
        ),
        night_hours: String((employee.manualHours?.night_minutes ?? 0) / 60),
        reason: '',
    });
    const save = (clear: boolean) => {
        form.transform((data) => ({ ...data, clear }));
        form.put(`/attendance/${employee.id}/hours`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog
            open
            onClose={() => !form.processing && onClose()}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                Manual attendance hours
                <IconButton
                    aria-label="Close manual hours"
                    disabled={form.processing}
                    onClick={onClose}
                >
                    <X size={20} />
                </IconButton>
            </DialogTitle>
            <DialogContent dividers>
                <div className="grid gap-5 py-2">
                    <p className="font-semibold">
                        {employee.name} · {date}
                    </p>
                    <Alert severity="info">
                        Enter net hours after unpaid breaks. Overtime is
                        included in the total, not added on top. Night hours may
                        overlap regular or overtime hours. This correction
                        replaces calculated hours for this day and feeds
                        payroll.
                    </Alert>
                    {Object.entries(form.errors).map(([key, message]) => (
                        <Alert severity="error" key={key}>
                            {message}
                        </Alert>
                    ))}
                    {employee.manualHours && (
                        <Alert severity="warning">
                            Current override: {employee.manualHours.reason}.
                            Restore calculated hours to use time entries and
                            approved requests again.
                        </Alert>
                    )}
                    {(
                        [
                            ['total_hours', 'Total credited hours'],
                            ['overtime_hours', 'Overtime portion (hours)'],
                            [
                                'night_hours',
                                'Night differential portion (hours)',
                            ],
                        ] as const
                    ).map(([key, label]) => (
                        <TextField
                            key={key}
                            size="small"
                            label={label}
                            value={form.data[key]}
                            onChange={(e) => form.setData(key, e.target.value)}
                            helperText={
                                key === 'total_hours'
                                    ? 'Example: 7.5 = 7 hours 30 minutes. Rounded to the nearest minute.'
                                    : undefined
                            }
                            slotProps={{ htmlInput: { inputMode: 'decimal' } }}
                        />
                    ))}
                    <TextField
                        label="Reason for correction"
                        required
                        multiline
                        minRows={2}
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        helperText="At least 5 characters. This correction and its author are recorded in the audit history."
                    />
                </div>
            </DialogContent>
            <DialogActions sx={{ p: 3, flexWrap: 'wrap', gap: 1 }}>
                {employee.manualHours && (
                    <Button
                        disabled={form.processing}
                        onClick={() => save(true)}
                    >
                        Restore calculated hours
                    </Button>
                )}
                <Button
                    variant="contained"
                    disabled={form.processing}
                    onClick={() => save(false)}
                >
                    Save hours
                </Button>
            </DialogActions>
        </Dialog>
    );
}
