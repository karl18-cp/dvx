import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    InputAdornment,
    MenuItem,
    TextField,
} from '@mui/material';
import type { TextFieldProps } from '@mui/material';
import { CalendarDays, ChevronLeft, ChevronRight, Clock } from 'lucide-react';
import { useId, useRef, useState } from 'react';

type Props = Omit<TextFieldProps, 'type' | 'value' | 'onChange'> & {
    type?: 'date' | 'datetime-local' | 'time';
    value?: unknown;
    onChange?: (event: { target: { value: string } }) => void;
};

const pad = (value: number) => String(value).padStart(2, '0');
const localDate = (date: Date) =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const months = Array.from({ length: 12 }, (_, month) =>
    new Date(2024, month, 1).toLocaleString('en-US', { month: 'long' }),
);

/** Keeps local date/time strings unchanged; never converts business time to UTC. */
export function DateTimeField({
    type = 'date',
    value,
    onChange,
    label,
    disabled,
    required,
    slotProps,
    ...props
}: Props) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [draftDate, setDraftDate] = useState('');
    const [draftTime, setDraftTime] = useState('09:00');
    const [month, setMonth] = useState(() => new Date().getMonth());
    const [year, setYear] = useState(() => new Date().getFullYear());
    const calendarRef = useRef<HTMLDivElement>(null);
    const hasDate = type !== 'time';
    const hasTime = type !== 'date';
    const text = typeof value === 'string' ? value : '';
    const title =
        typeof label === 'string'
            ? label
            : hasDate
              ? 'Select date'
              : 'Select time';

    const begin = () => {
        if (disabled) {
            return;
        }

        const today = new Date();
        const date = type === 'time' ? '' : text.slice(0, 10);
        const parsed = date ? new Date(`${date}T12:00:00`) : today;
        const valid = Number.isNaN(parsed.getTime()) ? today : parsed;
        setDraftDate(date);
        setDraftTime(
            (type === 'time' ? text : text.split('T')[1])?.slice(0, 5) ||
                '09:00',
        );
        setMonth(valid.getMonth());
        setYear(valid.getFullYear());
        setOpen(true);
    };
    const commit = (next: string) => {
        setOpen(false);
        onChange?.({ target: { value: next } });
    };
    const moveMonth = (offset: number) => {
        const next = new Date(year, month + offset, 1);
        setMonth(next.getMonth());
        setYear(next.getFullYear());
    };
    const selectDate = (date: Date) => {
        setDraftDate(localDate(date));
        setMonth(date.getMonth());
        setYear(date.getFullYear());
    };
    const hour24 = Number(draftTime.slice(0, 2));
    const minute = draftTime.slice(3, 5);
    const hour12 = hour24 % 12 || 12;
    const period = hour24 >= 12 ? 'PM' : 'AM';
    const changeTime = (hour: number, minutes: string, ampm: string) =>
        setDraftTime(
            `${pad((hour % 12) + (ampm === 'PM' ? 12 : 0))}:${minutes}`,
        );
    const days = new Date(year, month + 1, 0).getDate();
    const offset = new Date(year, month, 1).getDay();
    const selectedInMonth = draftDate.startsWith(`${year}-${pad(month + 1)}-`);
    const focusDay = selectedInMonth ? Number(draftDate.slice(8, 10)) : 1;
    const nowYear = new Date().getFullYear();
    const years = Array.from(
        {
            length:
                Math.max(nowYear + 20, year) -
                Math.min(nowYear - 100, year) +
                1,
        },
        (_, i) => Math.min(nowYear - 100, year) + i,
    );
    let display = text;

    if (text) {
        const date =
            type === 'time'
                ? new Date(`2000-01-01T${text}`)
                : new Date(`${text}${type === 'date' ? 'T12:00:00' : ''}`);

        if (!Number.isNaN(date.getTime())) {
            display = date.toLocaleString('en-US', {
                ...(hasDate
                    ? ({
                          month: 'short',
                          day: 'numeric',
                          year: 'numeric',
                      } as const)
                    : {}),
                ...(hasTime
                    ? ({
                          hour: 'numeric',
                          minute: '2-digit',
                          hour12: true,
                      } as const)
                    : {}),
            });
        }
    }

    return (
        <>
            <TextField
                {...props}
                label={label}
                disabled={disabled}
                required={required}
                value={display}
                onClick={begin}
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' ||
                        event.key === ' ' ||
                        event.key === 'ArrowDown'
                    ) {
                        event.preventDefault();
                        begin();
                    }
                }}
                slotProps={{
                    ...slotProps,
                    inputLabel: { shrink: true },
                    htmlInput: {
                        'aria-haspopup': 'dialog',
                        'aria-expanded': open,
                        'aria-label': title,
                    },
                    input: {
                        readOnly: true,
                        endAdornment: (
                            <InputAdornment position="end">
                                {hasDate ? (
                                    <CalendarDays
                                        size={18}
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Clock size={18} aria-hidden="true" />
                                )}
                            </InputAdornment>
                        ),
                    },
                }}
            />
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                fullWidth
                maxWidth="xs"
                aria-labelledby={id}
            >
                <DialogTitle id={id}>{title}</DialogTitle>
                <DialogContent>
                    {hasDate && (
                        <>
                            <div className="mb-4 flex items-center gap-2 pt-2">
                                <IconButton
                                    aria-label="Previous month"
                                    onClick={() => moveMonth(-1)}
                                >
                                    <ChevronLeft size={20} />
                                </IconButton>
                                <TextField
                                    select
                                    size="small"
                                    label="Month"
                                    value={month}
                                    onChange={(e) =>
                                        setMonth(Number(e.target.value))
                                    }
                                    sx={{ flex: 1 }}
                                >
                                    {months.map((name, index) => (
                                        <MenuItem key={name} value={index}>
                                            {name}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    select
                                    size="small"
                                    label="Year"
                                    value={year}
                                    onChange={(e) =>
                                        setYear(Number(e.target.value))
                                    }
                                    sx={{ width: 100 }}
                                >
                                    {years.map((item) => (
                                        <MenuItem key={item} value={item}>
                                            {item}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <IconButton
                                    aria-label="Next month"
                                    onClick={() => moveMonth(1)}
                                >
                                    <ChevronRight size={20} />
                                </IconButton>
                            </div>
                            <div
                                className="grid grid-cols-7 text-center text-sm"
                                role="group"
                                aria-label={`${months[month]} ${year}`}
                                ref={calendarRef}
                            >
                                {['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map(
                                    (day) => (
                                        <span
                                            key={day}
                                            className="py-2 text-gray-500"
                                        >
                                            {day}
                                        </span>
                                    ),
                                )}
                                {Array.from({ length: offset }, (_, index) => (
                                    <span key={`empty-${index}`} />
                                ))}
                                {Array.from({ length: days }, (_, index) => {
                                    const day = index + 1;
                                    const date = new Date(year, month, day);
                                    const iso = localDate(date);

                                    return (
                                        <Button
                                            key={iso}
                                            data-day={day}
                                            tabIndex={day === focusDay ? 0 : -1}
                                            aria-label={date.toLocaleDateString(
                                                'en-US',
                                                { dateStyle: 'full' },
                                            )}
                                            aria-pressed={draftDate === iso}
                                            aria-current={
                                                iso === localDate(new Date())
                                                    ? 'date'
                                                    : undefined
                                            }
                                            variant={
                                                draftDate === iso
                                                    ? 'contained'
                                                    : 'text'
                                            }
                                            sx={{
                                                minWidth: 0,
                                                height: 40,
                                                borderRadius: 2,
                                            }}
                                            onClick={() => selectDate(date)}
                                            onKeyDown={(event) => {
                                                const offsets: Record<
                                                    string,
                                                    number
                                                > = {
                                                    ArrowLeft: -1,
                                                    ArrowRight: 1,
                                                    ArrowUp: -7,
                                                    ArrowDown: 7,
                                                    Home: -date.getDay(),
                                                    End: 6 - date.getDay(),
                                                };

                                                if (!(event.key in offsets)) {
                                                    return;
                                                }

                                                event.preventDefault();
                                                const next = new Date(
                                                    year,
                                                    month,
                                                    day + offsets[event.key],
                                                );
                                                selectDate(next);
                                                requestAnimationFrame(() =>
                                                    calendarRef.current
                                                        ?.querySelector<HTMLButtonElement>(
                                                            `[data-day="${next.getDate()}"]`,
                                                        )
                                                        ?.focus(),
                                                );
                                            }}
                                        >
                                            {day}
                                        </Button>
                                    );
                                })}
                            </div>
                        </>
                    )}
                    {hasTime && (
                        <div className="mt-5 grid grid-cols-3 gap-3">
                            <TextField
                                select
                                size="small"
                                label="Hour"
                                value={hour12}
                                onChange={(e) =>
                                    changeTime(
                                        Number(e.target.value),
                                        minute,
                                        period,
                                    )
                                }
                            >
                                {Array.from({ length: 12 }, (_, i) => (
                                    <MenuItem key={i + 1} value={i + 1}>
                                        {pad(i + 1)}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                select
                                size="small"
                                label="Minute"
                                value={minute}
                                onChange={(e) =>
                                    changeTime(hour12, e.target.value, period)
                                }
                            >
                                {Array.from({ length: 60 }, (_, i) => (
                                    <MenuItem key={i} value={pad(i)}>
                                        {pad(i)}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                select
                                size="small"
                                label="AM/PM"
                                value={period}
                                onChange={(e) =>
                                    changeTime(hour12, minute, e.target.value)
                                }
                            >
                                <MenuItem value="AM">AM</MenuItem>
                                <MenuItem value="PM">PM</MenuItem>
                            </TextField>
                        </div>
                    )}
                    <Button
                        sx={{ mt: 2 }}
                        onClick={() => {
                            const today = new Date();
                            selectDate(today);
                            setDraftTime(
                                `${pad(today.getHours())}:${pad(today.getMinutes())}`,
                            );
                        }}
                    >
                        {hasTime ? 'Now' : 'Today'}
                    </Button>
                </DialogContent>
                <DialogActions>
                    {!required && (
                        <Button onClick={() => commit('')}>Clear</Button>
                    )}
                    <Button onClick={() => setOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={hasDate && !draftDate}
                        onClick={() =>
                            commit(
                                type === 'date'
                                    ? draftDate
                                    : type === 'time'
                                      ? draftTime
                                      : `${draftDate}T${draftTime}`,
                            )
                        }
                    >
                        Apply
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
