import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { X } from 'lucide-react';
import { useState } from 'react';

type Day = {
    day: number;
    no_schedule: boolean;
    time_in: string | null;
    time_out: string | null;
    break_start: string | null;
    break_end: string | null;
};
export type TeamControlsProps = {
    teams: { id: number; name: string; campaign_id: number }[];
    assignableAgents: {
        id: number;
        name: string;
        username: string;
        role: string;
        training_campaign_id: number | null;
    }[];
    ownSchedule: { name: string; days: Day[] } | null;
};
const names = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];
const fields = {
    time_in: 'Time in',
    time_out: 'Time out',
    break_start: 'Break out',
    break_end: 'Break in',
} as const;

export default function TeamManagementControls({
    teams,
    assignableAgents,
    ownSchedule,
}: TeamControlsProps) {
    const [open, setOpen] = useState<'agent' | 'schedule' | null>(null);
    const agent = useForm({ team_id: '', agent_id: '' });
    const schedule = useForm<{ days: Day[] }>({ days: [] });
    const busy = agent.processing || schedule.processing;
    const close = () => {
        if (!busy) {
            setOpen(null);
        }
    };

    return (
        <>
            <div className="flex flex-wrap gap-3">
                <Button
                    variant="contained"
                    disabled={!teams.length}
                    onClick={() => {
                        agent.clearErrors();
                        setOpen('agent');
                    }}
                >
                    Assign team member
                </Button>
                <Button
                    variant="outlined"
                    disabled={!ownSchedule}
                    onClick={() => {
                        schedule.clearErrors();
                        schedule.setData(
                            'days',
                            [...(ownSchedule?.days ?? [])]
                                .sort((a, b) => a.day - b.day)
                                .map((d) => ({
                                    day: d.day,
                                    no_schedule: d.no_schedule,
                                    time_in: '',
                                    time_out: '',
                                    break_start: '',
                                    break_end: '',
                                    ...Object.fromEntries(
                                        Object.keys(fields).map((k) => [
                                            k,
                                            (
                                                d[k as keyof typeof fields] ??
                                                ''
                                            ).slice(0, 5),
                                        ]),
                                    ),
                                })),
                        );
                        setOpen('schedule');
                    }}
                >
                    Edit my campaign schedule
                </Button>
                {!ownSchedule && (
                    <p className="self-center text-sm text-slate-500">
                        An admin must assign your campaign schedule first.
                    </p>
                )}
            </div>
            <Dialog
                open={open !== null}
                onClose={close}
                fullWidth
                maxWidth={open === 'schedule' ? 'lg' : 'sm'}
            >
                <DialogTitle className="flex items-center justify-between">
                    {open === 'agent'
                        ? 'Assign team member to my team'
                        : 'My campaign schedule'}
                    <IconButton
                        aria-label="Close"
                        disabled={busy}
                        onClick={close}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    {open === 'agent' ? (
                        <div className="grid gap-5 py-2">
                            <p className="text-sm text-slate-500">
                                Choose an unassigned agent or trainee, or a
                                member from a team you lead.
                            </p>
                            <TextField
                                fullWidth
                                select
                                label="Team"
                                value={agent.data.team_id}
                                onChange={(e) =>
                                    agent.setData({
                                        team_id: String(e.target.value),
                                        agent_id: '',
                                    })
                                }
                            >
                                {teams.map((t) => (
                                    <MenuItem key={t.id} value={t.id}>
                                        {t.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                fullWidth
                                select
                                label="Agent or trainee"
                                value={agent.data.agent_id}
                                onChange={(e) =>
                                    agent.setData('agent_id', e.target.value)
                                }
                            >
                                {assignableAgents
                                    .filter(
                                        (a) =>
                                            a.role !== 'trainee' ||
                                            teams.some(
                                                (t) =>
                                                    String(t.id) ===
                                                        agent.data.team_id &&
                                                    t.campaign_id ===
                                                        a.training_campaign_id,
                                            ),
                                    )
                                    .map((a) => (
                                        <MenuItem key={a.id} value={a.id}>
                                            {a.name} · {a.username}
                                            {a.role === 'trainee'
                                                ? ' (Trainee)'
                                                : ' (Agent)'}
                                        </MenuItem>
                                    ))}
                            </TextField>
                            {!assignableAgents.length && (
                                <Alert severity="info">
                                    No eligible agents or trainees available.
                                </Alert>
                            )}
                            {Object.values(agent.errors).map((e, i) => (
                                <Alert key={i} severity="error">
                                    {e}
                                </Alert>
                            ))}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <Alert severity="info">
                                Times use Asia/Manila. Changes apply only to
                                your schedule. Previously recorded attendance
                                keeps its original schedule.
                            </Alert>
                            {schedule.data.days.map((day, index) => (
                                <div
                                    key={day.day}
                                    className="grid items-center gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-6"
                                >
                                    <strong>{names[day.day - 1]}</strong>
                                    <label className="text-sm">
                                        <Checkbox
                                            checked={day.no_schedule}
                                            onChange={(e) =>
                                                schedule.setData(
                                                    'days',
                                                    schedule.data.days.map(
                                                        (d, i) =>
                                                            i === index
                                                                ? {
                                                                      ...d,
                                                                      no_schedule:
                                                                          e
                                                                              .target
                                                                              .checked,
                                                                  }
                                                                : d,
                                                    ),
                                                )
                                            }
                                        />
                                        Rest day
                                    </label>
                                    {Object.entries(fields).map(
                                        ([key, label]) => (
                                            <TextField
                                                key={key}
                                                size="small"
                                                type="time"
                                                label={label}
                                                slotProps={{
                                                    inputLabel: {
                                                        shrink: true,
                                                    },
                                                }}
                                                disabled={day.no_schedule}
                                                value={
                                                    day[
                                                        key as keyof typeof fields
                                                    ] ?? ''
                                                }
                                                onChange={(e) =>
                                                    schedule.setData(
                                                        'days',
                                                        schedule.data.days.map(
                                                            (d, i) =>
                                                                i === index
                                                                    ? {
                                                                          ...d,
                                                                          [key]: e
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : d,
                                                        ),
                                                    )
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            ))}
                            {Object.values(schedule.errors).map((e, i) => (
                                <Alert key={i} severity="error">
                                    {e}
                                </Alert>
                            ))}
                        </div>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={
                            busy ||
                            (open === 'agent' &&
                                (!agent.data.team_id || !agent.data.agent_id))
                        }
                        onClick={() =>
                            open === 'agent'
                                ? agent.post('/my-team/agents', {
                                      preserveScroll: true,
                                      onSuccess: () => {
                                          setOpen(null);
                                          agent.reset();
                                      },
                                  })
                                : schedule.put('/my-team/schedule', {
                                      preserveScroll: true,
                                      onSuccess: () => setOpen(null),
                                  })
                        }
                    >
                        {busy ? 'Saving…' : 'Save changes'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
