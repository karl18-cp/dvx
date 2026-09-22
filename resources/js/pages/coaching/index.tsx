import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Autocomplete,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    TextField,
} from '@mui/material';
import { useState } from 'react';
import CoachingExportButton from '@/components/coaching-export-button';
import { DateTimeField } from '@/components/date-time-field';
import {
    coachingFollowUp,
    coachingToday,
    formatCoachingDate,
} from '@/lib/coaching-dates';

type Ref = { id: number; name: string; username?: string };
type RecordRow = {
    id: number;
    coaching_date: string;
    type: string;
    status: string;
    follow_up_date?: string;
    team_name?: string;
    campaign_name?: string;
    acknowledged_at?: string;
    employee: Ref;
    coach: Ref;
    skill?: Ref;
    assessment?: { title: string };
    call_evaluation?: { percentage: string };
};
type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url?: string;
    next_page_url?: string;
};
type Material = { id: number; title: string; type: string };

export default function CoachingIndex({
    records,
    summary,
    filters,
    employees,
    skills,
    assessments,
    materials,
    campaigns,
    teams,
    types,
    statuses,
    prefill = {},
    can_manage,
}: {
    records: Page<RecordRow>;
    summary: Record<string, number>;
    filters: Record<string, string>;
    employees: Ref[];
    skills: Ref[];
    assessments: { id: number; title: string }[];
    materials: Material[];
    campaigns: Ref[];
    teams: Ref[];
    types: string[];
    statuses: string[];
    prefill?: Record<string, string>;
    can_manage: boolean;
}) {
    const [open, setOpen] = useState(prefill.create === '1');
    const form = useForm({
        employee_id: prefill.employee || '',
        type: 'General Coaching',
        skill_id: prefill.skill || '',
        assessment_id: prefill.assessment || '',
        call_evaluation_id: prefill.call_evaluation_id || '',
        coaching_date: coachingToday(),
        summary: prefill.summary || '',
        strengths: prefill.strengths || '',
        areas_for_improvement: prefill.areas_for_improvement || '',
        action_plan: prefill.action_plan || '',
        follow_up_date: '',
        material_ids: [] as number[],
    });
    const filter = (key: string, value: string) =>
        router.get(
            '/management/coaching',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    const statusLabel = (value: string) =>
        value
            .split('_')
            .map((word) => word[0].toUpperCase() + word.slice(1))
            .join(' ');

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6">
            <Head title="Coaching" />
            <div className="mx-auto max-w-7xl space-y-5">
                <header className="flex items-start justify-between gap-4">
                    <div>
                        <h1>Coaching Log</h1>
                        <p>
                            Manage employee coaching, follow-ups, and
                            development actions.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <CoachingExportButton records={records.data} />
                        {can_manage && (
                            <Button
                                variant="contained"
                                onClick={() => setOpen(true)}
                            >
                                Create Coaching
                            </Button>
                        )}
                    </div>
                </header>
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['Open Coaching', summary.open],
                        ['Follow-up Required', summary.follow_up_required],
                        ['Completed', summary.completed],
                        ['Upcoming Follow-ups', summary.upcoming],
                    ].map(([label, value]) => (
                        <div
                            key={String(label)}
                            className="rounded-xl border bg-white p-4"
                        >
                            <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">
                                {label}
                            </p>
                            <p className="mt-1 text-xl font-bold">{value}</p>
                        </div>
                    ))}
                </section>
                <section className="grid gap-2 rounded-xl border bg-white p-3 md:grid-cols-4">
                    <Filter
                        label="Employee"
                        value={filters.employee}
                        options={employees}
                        onChange={(v) => filter('employee', v)}
                    />
                    <Filter
                        label="Campaign"
                        value={filters.campaign}
                        options={campaigns}
                        onChange={(v) => filter('campaign', v)}
                    />
                    <Filter
                        label="Team"
                        value={filters.team}
                        options={teams}
                        onChange={(v) => filter('team', v)}
                    />
                    <Filter
                        label="Status"
                        value={filters.status}
                        values={statuses}
                        onChange={(v) => filter('status', v)}
                    />
                    <DateTimeField
                        size="small"
                        label="From"
                        type="date"
                        slotProps={{ inputLabel: { shrink: true } }}
                        value={filters.from || ''}
                        onChange={(e) => filter('from', e.target.value)}
                    />
                    <DateTimeField
                        size="small"
                        label="To"
                        type="date"
                        slotProps={{ inputLabel: { shrink: true } }}
                        value={filters.to || ''}
                        onChange={(e) => filter('to', e.target.value)}
                    />
                    <Button
                        variant="outlined"
                        onClick={() => router.get('/management/coaching')}
                    >
                        Clear Filters
                    </Button>
                </section>
                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full min-w-[950px] text-left text-sm">
                        <thead>
                            <tr>
                                {[
                                    'Employee',
                                    'Campaign',
                                    'Team',
                                    'Source',
                                    'Focus',
                                    'Coach',
                                    'Coaching Date',
                                    'Follow-up',
                                    'Status',
                                    'Acknowledged',
                                    'Action',
                                ].map((h) => (
                                    <th className="px-4 py-3" key={h}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {records.data.map((record) => (
                                <tr className="border-t" key={record.id}>
                                    <td className="px-4 py-3 font-semibold">
                                        {record.employee.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.campaign_name || 'Unassigned'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.team_name || 'Unassigned'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.call_evaluation
                                            ? 'QA Evaluation'
                                            : record.assessment
                                              ? 'Assessment'
                                              : 'Manual Coaching'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.skill?.name || record.type}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.coach.name}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {formatCoachingDate(
                                            record.coaching_date,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {coachingFollowUp(
                                            record.follow_up_date,
                                            record.status,
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {statusLabel(record.status)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {record.acknowledged_at
                                            ? 'Yes'
                                            : 'Not Yet'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            className="font-semibold text-red-700"
                                            href={`/management/coaching/${record.id}`}
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex justify-end gap-2">
                    {records.prev_page_url && (
                        <Button
                            onClick={() => router.get(records.prev_page_url!)}
                        >
                            Previous
                        </Button>
                    )}
                    {records.next_page_url && (
                        <Button
                            onClick={() => router.get(records.next_page_url!)}
                        >
                            Next
                        </Button>
                    )}
                </div>
            </div>
            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                fullWidth
                maxWidth="md"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '94vh' } },
                }}
            >
                <DialogTitle sx={{ px: 3, pt: 2.5, pb: 1.5 }}>
                    <span className="block text-xl font-bold">
                        Create Coaching Record
                    </span>
                    <span className="mt-0.5 block text-sm font-medium text-slate-600">
                        Add feedback, an action plan, and optional training.
                    </span>
                </DialogTitle>
                <DialogContent
                    dividers
                    sx={{
                        px: 3,
                        py: 2,
                        '& .MuiInputBase-root:not(.MuiInputBase-multiline)': {
                            minHeight: 44,
                        },
                    }}
                >
                    <div className="grid gap-2.5 sm:grid-cols-2">
                        <TextField
                            select
                            label="Employee"
                            value={form.data.employee_id}
                            onChange={(e) =>
                                form.setData('employee_id', e.target.value)
                            }
                        >
                            {employees.map((e) => (
                                <MenuItem key={e.id} value={e.id}>
                                    {e.name} ({e.username})
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Coaching Type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            {types.map((t) => (
                                <MenuItem key={t} value={t}>
                                    {t}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Related Skill"
                            value={form.data.skill_id}
                            onChange={(e) =>
                                form.setData('skill_id', e.target.value)
                            }
                        >
                            <MenuItem value="">None</MenuItem>
                            {skills.map((s) => (
                                <MenuItem key={s.id} value={s.id}>
                                    {s.name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Related Assessment"
                            value={form.data.assessment_id}
                            onChange={(e) =>
                                form.setData('assessment_id', e.target.value)
                            }
                        >
                            <MenuItem value="">None</MenuItem>
                            {assessments.map((a) => (
                                <MenuItem key={a.id} value={a.id}>
                                    {a.title}
                                </MenuItem>
                            ))}
                        </TextField>
                        <DateTimeField
                            label="Coaching Date"
                            type="date"
                            slotProps={{ inputLabel: { shrink: true } }}
                            value={form.data.coaching_date}
                            onChange={(e) =>
                                form.setData('coaching_date', e.target.value)
                            }
                        />
                        <DateTimeField
                            label="Follow-up Date"
                            type="date"
                            slotProps={{ inputLabel: { shrink: true } }}
                            value={form.data.follow_up_date}
                            onChange={(e) =>
                                form.setData('follow_up_date', e.target.value)
                            }
                        />
                        <TextField
                            className="sm:col-span-2"
                            multiline
                            minRows={1}
                            label="Summary"
                            value={form.data.summary}
                            onChange={(e) =>
                                form.setData('summary', e.target.value)
                            }
                        />
                        <TextField
                            multiline
                            minRows={1}
                            label="Strengths"
                            value={form.data.strengths}
                            onChange={(e) =>
                                form.setData('strengths', e.target.value)
                            }
                        />
                        <TextField
                            multiline
                            minRows={1}
                            label="Areas for Improvement"
                            value={form.data.areas_for_improvement}
                            onChange={(e) =>
                                form.setData(
                                    'areas_for_improvement',
                                    e.target.value,
                                )
                            }
                        />
                        <TextField
                            className="sm:col-span-2"
                            multiline
                            minRows={1}
                            label="Action Plan"
                            value={form.data.action_plan}
                            onChange={(e) =>
                                form.setData('action_plan', e.target.value)
                            }
                        />
                        <div className="mt-1 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                            <div className="mb-2 flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-bold text-slate-800">
                                        Assign Training Materials
                                    </p>
                                    <p className="text-sm font-medium text-slate-600">
                                        Select one or more Training Library
                                        lessons.
                                    </p>
                                </div>
                                <span className="shrink-0 rounded-full bg-white px-2.5 py-1 text-sm font-bold text-red-700 ring-1 ring-slate-200">
                                    {form.data.material_ids.length} selected
                                </span>
                            </div>
                            <Autocomplete
                                multiple
                                disableCloseOnSelect
                                limitTags={3}
                                options={materials}
                                value={materials.filter((material) =>
                                    form.data.material_ids.includes(
                                        material.id,
                                    ),
                                )}
                                getOptionLabel={(option) => option.title}
                                isOptionEqualToValue={(option, value) =>
                                    option.id === value.id
                                }
                                onChange={(_, selectedMaterials) =>
                                    form.setData(
                                        'material_ids',
                                        selectedMaterials.map(
                                            (material) => material.id,
                                        ),
                                    )
                                }
                                renderOption={(props, option, state) => {
                                    const { key, ...optionProps } = props;

                                    return (
                                        <li key={key} {...optionProps}>
                                            <Checkbox
                                                size="small"
                                                checked={state.selected}
                                                sx={{ mr: 1, p: 0.5 }}
                                            />
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold">
                                                    {option.title}
                                                </span>
                                                <span className="block text-sm font-medium text-slate-600 capitalize">
                                                    {option.type}
                                                </span>
                                            </span>
                                        </li>
                                    );
                                }}
                                renderInput={(params) => (
                                    <TextField
                                        {...params}
                                        size="small"
                                        placeholder={
                                            form.data.material_ids.length
                                                ? 'Add another material'
                                                : 'Search and select training materials'
                                        }
                                    />
                                )}
                            />
                        </div>
                    </div>
                </DialogContent>
                <DialogActions sx={{ px: 3, py: 1.5 }}>
                    <Button onClick={() => setOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={form.processing}
                        onClick={() => form.post('/management/coaching')}
                    >
                        Save Coaching
                    </Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}

function Filter({
    label,
    value = '',
    options = [],
    values = [],
    onChange,
}: {
    label: string;
    value?: string;
    options?: Ref[];
    values?: string[];
    onChange: (value: string) => void;
}) {
    return (
        <TextField
            select
            size="small"
            label={label}
            value={value}
            onChange={(e) => onChange(e.target.value)}
        >
            <MenuItem value="">All</MenuItem>
            {options.map((o) => (
                <MenuItem key={o.id} value={o.id}>
                    {o.name}
                </MenuItem>
            ))}
            {values.map((v) => (
                <MenuItem key={v} value={v}>
                    {v
                        .split('_')
                        .map((w) => w[0].toUpperCase() + w.slice(1))
                        .join(' ')}
                </MenuItem>
            ))}
        </TextField>
    );
}
CoachingIndex.layout = {
    breadcrumbs: [
        { title: 'Training & Development', href: '/management/assessments' },
        { title: 'Coaching', href: '/management/coaching' },
    ],
};
