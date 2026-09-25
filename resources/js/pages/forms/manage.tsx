import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import {
    ArrowDown,
    ArrowUp,
    FileText,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';
import {
    choiceTypes,
    formatDate,
    Pagination,
    panel,
    teamLabel,
    types,
} from './shared';
import type { Field, FormRecord, Page, ResponseRecord, Team } from './shared';

type Filters = {
    tab: string;
    search: string;
    form_id: number | string;
    from: string;
    to: string;
};
type Props = {
    forms: Page<FormRecord>;
    responses: Page<ResponseRecord> | null;
    teams: Team[];
    formOptions: { id: number; title: string; deleted_at: string | null }[];
    stats: { total: number; active: number; responses: number };
    filters: Filters;
    statusMessage: string | null;
};
type DraftField = Field & { optionsText: string };
type Builder = {
    title: string;
    description: string;
    status: string;
    ranking_enabled: boolean;
    team_ids: number[];
    fields: DraftField[];
    revision: number;
};
const newField = (): DraftField => ({
    id: crypto.randomUUID(),
    label: '',
    type: 'text',
    placeholder: '',
    required: false,
    options: [],
    optionsText: '',
});

export default function ManageForms({
    forms,
    responses,
    teams,
    formOptions,
    stats,
    filters,
    statusMessage,
}: Props) {
    const confirmAction = useConfirmation();
    const { errors } = usePage().props;
    const [editing, setEditing] = useState<FormRecord | null>(null);
    const [open, setOpen] = useState(false);
    const [detail, setDetail] = useState<ResponseRecord | null>(null);
    const [query, setQuery] = useState(filters);
    const [deleting, setDeleting] = useState(false);
    const builder = useForm<Builder>({
        title: '',
        description: '',
        status: 'draft',
        ranking_enabled: false,
        team_ids: [],
        fields: [],
        revision: 1,
    });
    const showEditor = (form?: FormRecord) => {
        setEditing(form ?? null);
        builder.setData(
            form
                ? {
                      title: form.title,
                      description: form.description ?? '',
                      status: form.status,
                      ranking_enabled: form.ranking_enabled,
                      team_ids: form.teams.map((t) => t.id),
                      fields: form.fields.map((f) => ({
                          ...f,
                          placeholder: f.placeholder ?? '',
                          optionsText: f.options.join('\n'),
                      })),
                      revision: form.revision,
                  }
                : {
                      title: '',
                      description: '',
                      status: 'draft',
                      ranking_enabled: false,
                      team_ids: [],
                      fields: [newField()],
                      revision: 1,
                  },
        );
        builder.clearErrors();
        setOpen(true);
    };
    const updateField = (index: number, patch: Partial<DraftField>) =>
        builder.setData(
            'fields',
            builder.data.fields.map((field, i) =>
                i === index ? { ...field, ...patch } : field,
            ),
        );
    const moveField = (index: number, offset: number) => {
        const fields = [...builder.data.fields];
        [fields[index], fields[index + offset]] = [
            fields[index + offset],
            fields[index],
        ];
        builder.setData('fields', fields);
    };
    const save = () => {
        builder.transform((data) => ({
            ...data,
            fields: data.fields.map(({ optionsText, ...field }) => ({
                ...field,
                options: choiceTypes.includes(field.type)
                    ? optionsText
                          .split(/\n|,/)
                          .map((s) => s.trim())
                          .filter(Boolean)
                    : [],
            })),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setQuery({
                    ...filters,
                    tab: 'manage',
                    search: '',
                    form_id: '',
                    from: '',
                    to: '',
                });
            },
        };

        if (editing) {
            builder.put(`/forms/${editing.id}`, options);
        } else {
            builder.post('/forms', options);
        }
    };
    const remove = async (form: FormRecord) => {
        if (
            !(await confirmAction(
                `Delete ${form.title}? Employees will no longer be able to submit it. Existing responses and earned points will be kept.`,
            ))
        ) {
            return;
        }

        router.delete(`/forms/${form.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
        });
    };
    const visit = (next: Filters) => {
        setQuery(next);
        router.get('/forms', next, { preserveScroll: true });
    };

    return (
        <main className="min-h-full p-4 text-[#17202d] lg:p-6">
            <Head title="Forms" />
            {!open && Object.keys(errors).length > 0 && (
                <Alert severity="error">
                    {Object.values(errors).join(' ')}
                </Alert>
            )}
            <div className="mx-auto max-w-[1600px] space-y-5">
                <header>
                    <h1 className="flex items-center gap-3 text-3xl font-extrabold">
                        <FileText className="text-red-800" />
                        Forms
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        Create team-assigned forms and review employee
                        responses.
                    </p>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                <div className="flex gap-3">
                    <Button
                        variant={
                            filters.tab === 'manage' ? 'contained' : 'outlined'
                        }
                        onClick={() =>
                            visit({
                                tab: 'manage',
                                search: '',
                                form_id: '',
                                from: '',
                                to: '',
                            })
                        }
                    >
                        Manage Forms
                    </Button>
                    <Button
                        variant={
                            filters.tab === 'responses'
                                ? 'contained'
                                : 'outlined'
                        }
                        onClick={() =>
                            visit({
                                tab: 'responses',
                                search: '',
                                form_id: '',
                                from: '',
                                to: '',
                            })
                        }
                    >
                        Responses
                    </Button>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Total forms', stats.total],
                        ['Active forms', stats.active],
                        ['Responses', stats.responses],
                    ].map(([label, value]) => (
                        <div className={panel} key={label}>
                            <p className="text-xs font-bold text-slate-500 uppercase">
                                {label}
                            </p>
                            <p className="mt-2 text-2xl font-extrabold">
                                {value}
                            </p>
                        </div>
                    ))}
                </div>
                <section className={panel}>
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-extrabold">
                                {filters.tab === 'manage'
                                    ? 'Form Builder'
                                    : 'Employee Responses'}
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                {filters.tab === 'manage'
                                    ? 'Build custom forms and assign them by team.'
                                    : 'View submitted answers and awarded points. Dates shown in Asia/Manila.'}
                            </p>
                        </div>
                        {filters.tab === 'manage' && (
                            <Button
                                variant="contained"
                                startIcon={<Plus size={16} />}
                                onClick={() => showEditor()}
                            >
                                Add Form
                            </Button>
                        )}
                    </div>
                    <form
                        className="mb-5 flex flex-wrap items-end gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            visit(query);
                        }}
                    >
                        <TextField
                            label={
                                filters.tab === 'manage'
                                    ? 'Search forms'
                                    : 'Search employee, ID, or form'
                            }
                            size="small"
                            value={query.search}
                            onChange={(e) =>
                                setQuery({ ...query, search: e.target.value })
                            }
                            sx={{ width: { xs: '100%', sm: 260 } }}
                        />
                        {filters.tab === 'responses' && (
                            <>
                                <TextField
                                    select
                                    label="Form"
                                    size="small"
                                    value={query.form_id}
                                    onChange={(e) =>
                                        setQuery({
                                            ...query,
                                            form_id: e.target.value,
                                        })
                                    }
                                    sx={{ width: { xs: '100%', sm: 260 } }}
                                >
                                    <MenuItem value="">All forms</MenuItem>
                                    {formOptions.map((form) => (
                                        <MenuItem key={form.id} value={form.id}>
                                            {form.title}
                                            {form.deleted_at
                                                ? ' (deleted)'
                                                : ''}
                                        </MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    label="From"
                                    type="date"
                                    size="small"
                                    value={query.from}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                    onChange={(e) =>
                                        setQuery({
                                            ...query,
                                            from: e.target.value,
                                        })
                                    }
                                />
                                <TextField
                                    label="To"
                                    type="date"
                                    size="small"
                                    value={query.to}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                    onChange={(e) =>
                                        setQuery({
                                            ...query,
                                            to: e.target.value,
                                        })
                                    }
                                />
                            </>
                        )}
                        <Button type="submit" variant="outlined">
                            Search
                        </Button>
                        <Button
                            onClick={() =>
                                visit({
                                    tab: filters.tab,
                                    search: '',
                                    form_id: '',
                                    from: '',
                                    to: '',
                                })
                            }
                        >
                            Clear
                        </Button>
                    </form>
                    <div className="max-h-[560px] overflow-auto rounded-xl border border-slate-200">
                        {filters.tab === 'manage' ? (
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="sticky top-0 z-10 bg-[#fff5f5] text-xs text-red-900 uppercase">
                                    <tr>
                                        {[
                                            'Form',
                                            'Teams',
                                            'Fields',
                                            'Responses',
                                            'Status',
                                            'Created',
                                            'Actions',
                                        ].map((label) => (
                                            <th key={label} className="p-4">
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {forms.data.map((form) => (
                                        <tr
                                            key={form.id}
                                            className="border-t border-slate-100"
                                        >
                                            <td className="max-w-xs p-4">
                                                <strong>{form.title}</strong>
                                                <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                                                    {form.description ||
                                                        'No description'}
                                                </p>
                                                {form.ranking_enabled && (
                                                    <p className="mt-2 text-xs text-amber-700">
                                                        ★ 1 ranking point per
                                                        response
                                                    </p>
                                                )}
                                            </td>
                                            <td className="max-w-xs p-4">
                                                <div className="flex flex-wrap gap-1">
                                                    {form.teams.map((team) => (
                                                        <Chip
                                                            key={team.id}
                                                            label={teamLabel(
                                                                team,
                                                            )}
                                                            size="small"
                                                        />
                                                    ))}
                                                    {!form.teams.length && (
                                                        <span className="text-slate-400">
                                                            Not assigned
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                {form.fields.length}
                                            </td>
                                            <td className="p-4">
                                                <button
                                                    className="font-bold text-red-700 underline"
                                                    aria-label={`View responses for ${form.title}`}
                                                    onClick={() =>
                                                        visit({
                                                            tab: 'responses',
                                                            form_id: form.id,
                                                            search: '',
                                                            from: '',
                                                            to: '',
                                                        })
                                                    }
                                                >
                                                    {form.responses_count}
                                                </button>
                                            </td>
                                            <td className="p-4">
                                                <Chip
                                                    size="small"
                                                    label={form.status}
                                                    color={
                                                        form.status === 'active'
                                                            ? 'success'
                                                            : 'default'
                                                    }
                                                />
                                            </td>
                                            <td className="p-4 text-xs">
                                                {formatDate(form.created_at)}
                                            </td>
                                            <td className="p-4">
                                                <div className="flex">
                                                    <IconButton
                                                        aria-label={`Edit ${form.title}`}
                                                        onClick={() =>
                                                            showEditor(form)
                                                        }
                                                    >
                                                        <Pencil size={17} />
                                                    </IconButton>
                                                    <IconButton
                                                        color="error"
                                                        disabled={deleting}
                                                        aria-label={`Delete ${form.title}`}
                                                        onClick={() =>
                                                            remove(form)
                                                        }
                                                    >
                                                        <Trash2 size={17} />
                                                    </IconButton>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!forms.data.length && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="p-10 text-center text-slate-500"
                                            >
                                                No forms found. Add a form to
                                                get started.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        ) : (
                            <table className="w-full min-w-[800px] text-left text-sm">
                                <thead className="sticky top-0 z-10 bg-[#fff5f5] text-xs text-red-900 uppercase">
                                    <tr>
                                        {[
                                            'Employee',
                                            'Form',
                                            'Team at submission',
                                            'Submitted',
                                            'Points',
                                            'Answers',
                                        ].map((label) => (
                                            <th className="p-4" key={label}>
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {responses?.data.map((response) => (
                                        <tr
                                            key={response.id}
                                            className="border-t border-slate-100"
                                        >
                                            <td className="p-4">
                                                <strong>
                                                    {response.employee_name}
                                                </strong>
                                                <p className="text-xs text-slate-500">
                                                    {response.employee_username}
                                                </p>
                                            </td>
                                            <td className="p-4">
                                                {response.form_title}
                                            </td>
                                            <td className="p-4">
                                                {response.teams
                                                    .map((team) => team.name)
                                                    .join(', ')}
                                            </td>
                                            <td className="p-4 text-xs">
                                                {formatDate(
                                                    response.created_at,
                                                )}
                                            </td>
                                            <td className="p-4">
                                                {response.points}
                                            </td>
                                            <td className="p-4">
                                                <Button
                                                    size="small"
                                                    onClick={() =>
                                                        setDetail(response)
                                                    }
                                                >
                                                    View answers
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {!responses?.data.length && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="p-10 text-center text-slate-500"
                                            >
                                                No responses match these
                                                filters.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        )}
                    </div>
                    <Pagination
                        page={filters.tab === 'manage' ? forms : responses!}
                    />
                </section>
            </div>
            <Dialog
                open={open}
                onClose={() => {
                    if (!builder.processing) {
                        setOpen(false);
                    }
                }}
                maxWidth="lg"
                fullWidth
                scroll="paper"
            >
                <DialogTitle
                    component="div"
                    sx={{
                        background: 'linear-gradient(120deg,#601d20,#b42325)',
                        color: 'white',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <h2 className="text-xl font-bold">
                        {editing ? 'Edit Form' : 'Add Form'}
                    </h2>
                    <IconButton
                        aria-label="Close form editor"
                        disabled={builder.processing}
                        onClick={() => setOpen(false)}
                        sx={{ color: 'white' }}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers sx={{ bgcolor: '#faf9fb' }}>
                    <fieldset
                        disabled={builder.processing}
                        className="space-y-4 border-0 p-0"
                    >
                        {Object.keys(builder.errors).length > 0 && (
                            <Alert severity="error">
                                <ul>
                                    {Object.entries(builder.errors).map(
                                        ([key, value]) => (
                                            <li key={key}>{value}</li>
                                        ),
                                    )}
                                </ul>
                            </Alert>
                        )}
                        <section className={`${panel} space-y-4`}>
                            <h3 className="font-bold">Form Details</h3>
                            <div className="grid gap-4 sm:grid-cols-[2fr_1fr]">
                                <TextField
                                    label="Form title"
                                    required
                                    value={builder.data.title}
                                    onChange={(e) =>
                                        builder.setData('title', e.target.value)
                                    }
                                    fullWidth
                                    size="small"
                                />
                                <TextField
                                    select
                                    label="Status"
                                    value={builder.data.status}
                                    onChange={(e) =>
                                        builder.setData(
                                            'status',
                                            e.target.value,
                                        )
                                    }
                                    size="small"
                                >
                                    {['draft', 'active', 'inactive'].map(
                                        (status) => (
                                            <MenuItem
                                                key={status}
                                                value={status}
                                            >
                                                {status}
                                            </MenuItem>
                                        ),
                                    )}
                                </TextField>
                            </div>
                            <TextField
                                label="Description"
                                placeholder="Short instructions for employees"
                                multiline
                                minRows={2}
                                fullWidth
                                value={builder.data.description}
                                onChange={(e) =>
                                    builder.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                            />
                            <FormControlLabel
                                control={
                                    <Checkbox
                                        checked={builder.data.ranking_enabled}
                                        onChange={(e) =>
                                            builder.setData(
                                                'ranking_enabled',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                }
                                label="Part of ranking point system — 1 point per response"
                            />
                        </section>
                        <section className={panel}>
                            <h3 className="mb-2 font-bold">Assigned Teams</h3>
                            <p className="mb-3 text-xs text-slate-500">
                                Active forms require at least one team. Team
                                members and team leaders can respond.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {teams.map((team) => (
                                    <FormControlLabel
                                        key={team.id}
                                        control={
                                            <Checkbox
                                                checked={builder.data.team_ids.includes(
                                                    team.id,
                                                )}
                                                onChange={(e) =>
                                                    builder.setData(
                                                        'team_ids',
                                                        e.target.checked
                                                            ? [
                                                                  ...builder
                                                                      .data
                                                                      .team_ids,
                                                                  team.id,
                                                              ]
                                                            : builder.data.team_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      team.id,
                                                              ),
                                                    )
                                                }
                                            />
                                        }
                                        label={teamLabel(team)}
                                    />
                                ))}
                                {!teams.length && (
                                    <Alert severity="info">
                                        Create a team in Team Management before
                                        activating a form.
                                    </Alert>
                                )}
                            </div>
                        </section>
                        <section className={`${panel} space-y-4`}>
                            <div className="flex items-center justify-between">
                                <h3 className="font-bold">Fields</h3>
                                <Button
                                    startIcon={<Plus size={16} />}
                                    disabled={builder.data.fields.length >= 50}
                                    onClick={() =>
                                        builder.setData('fields', [
                                            ...builder.data.fields,
                                            newField(),
                                        ])
                                    }
                                >
                                    Add Field
                                </Button>
                            </div>
                            {builder.data.fields.map((field, index) => (
                                <div
                                    key={field.id}
                                    className="space-y-4 rounded-xl border border-slate-200 p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-sm font-bold">
                                            Field {index + 1}
                                        </h4>
                                        <div>
                                            <IconButton
                                                aria-label={`Move field ${index + 1} up`}
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveField(index, -1)
                                                }
                                            >
                                                <ArrowUp size={16} />
                                            </IconButton>
                                            <IconButton
                                                aria-label={`Move field ${index + 1} down`}
                                                disabled={
                                                    index ===
                                                    builder.data.fields.length -
                                                        1
                                                }
                                                onClick={() =>
                                                    moveField(index, 1)
                                                }
                                            >
                                                <ArrowDown size={16} />
                                            </IconButton>
                                            <IconButton
                                                color="error"
                                                aria-label={`Remove field ${index + 1}`}
                                                disabled={
                                                    builder.data.fields
                                                        .length === 1
                                                }
                                                onClick={() =>
                                                    builder.setData(
                                                        'fields',
                                                        builder.data.fields.filter(
                                                            (f) =>
                                                                f.id !==
                                                                field.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 size={16} />
                                            </IconButton>
                                        </div>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <TextField
                                            size="small"
                                            label={`Field ${index + 1} label`}
                                            required
                                            value={field.label}
                                            onChange={(e) =>
                                                updateField(index, {
                                                    label: e.target.value,
                                                })
                                            }
                                        />
                                        <TextField
                                            size="small"
                                            select
                                            label={`Field ${index + 1} type`}
                                            value={field.type}
                                            onChange={(e) =>
                                                updateField(index, {
                                                    type: e.target.value,
                                                })
                                            }
                                        >
                                            {types.map((type) => (
                                                <MenuItem
                                                    value={type}
                                                    key={type}
                                                >
                                                    {type}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                        <TextField
                                            size="small"
                                            label="Placeholder"
                                            value={field.placeholder}
                                            onChange={(e) =>
                                                updateField(index, {
                                                    placeholder: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <FormControlLabel
                                        control={
                                            <Checkbox
                                                checked={field.required}
                                                onChange={(e) =>
                                                    updateField(index, {
                                                        required:
                                                            e.target.checked,
                                                    })
                                                }
                                            />
                                        }
                                        label="Required"
                                    />
                                    {choiceTypes.includes(field.type) && (
                                        <TextField
                                            fullWidth
                                            multiline
                                            minRows={2}
                                            label="Options"
                                            helperText="One option per line, or separated by commas."
                                            value={field.optionsText}
                                            onChange={(e) =>
                                                updateField(index, {
                                                    optionsText: e.target.value,
                                                })
                                            }
                                        />
                                    )}
                                </div>
                            ))}
                        </section>
                    </fieldset>
                </DialogContent>
                <DialogActions sx={{ p: 2 }}>
                    <Button
                        disabled={builder.processing}
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={builder.processing}
                        onClick={save}
                    >
                        {builder.processing ? 'Saving…' : 'Save Form'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!detail}
                onClose={() => setDetail(null)}
                maxWidth="md"
                fullWidth
            >
                <DialogTitle>Response details</DialogTitle>
                <DialogContent dividers>
                    {detail && (
                        <div className="space-y-5">
                            <div>
                                <h3 className="text-lg font-bold">
                                    {detail.form_title}
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    {detail.employee_name} ·{' '}
                                    {detail.employee_username} ·{' '}
                                    {formatDate(detail.created_at)}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Form version {detail.revision} ·{' '}
                                    {detail.points} ranking point(s)
                                </p>
                                {detail.form_description && (
                                    <p className="mt-3 text-sm whitespace-pre-wrap">
                                        {detail.form_description}
                                    </p>
                                )}
                            </div>
                            {detail.answers.map((answer, index) => (
                                <div
                                    key={answer.id}
                                    className="rounded-xl border border-slate-200 p-4"
                                >
                                    <p className="mb-2 text-sm font-bold">
                                        {index + 1}. {answer.label}
                                    </p>
                                    <p className="text-sm break-words whitespace-pre-wrap">
                                        {Array.isArray(answer.value)
                                            ? answer.value.join(', ') ||
                                              'No answer'
                                            : answer.value === null ||
                                                answer.value === ''
                                              ? 'No answer'
                                              : String(answer.value)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDetail(null)}>Close</Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}
