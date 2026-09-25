import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Autocomplete,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    TextField,
} from '@mui/material';
import {
    ChevronDown,
    ClipboardList,
    Gavel,
    History,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';

type Sanction = {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
};
type Employee = {
    id: number;
    name: string;
    username: string | null;
    role: string;
    status: string;
};
type IssuedSanction = {
    id: number;
    sanction_name: string;
    sanction_description: string | null;
    employee_name: string;
    employee_username: string | null;
    employee_role: string;
    issuer_name: string;
    punishment: string;
    notes: string | null;
    created_at: string;
};
type Props = {
    sanctions: Sanction[];
    employees: Employee[];
    punishments: string[];
    records: {
        data: IssuedSanction[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string; punishment: string };
    statusMessage: string | null;
};
const dateTime = (value: string) =>
    new Date(value).toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
const roleLabel = (value: string) => value.replaceAll('_', ' ');
const primaryClass =
    'inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-[#8b2525] to-[#d9301b] px-4 py-2 text-xs font-extrabold text-white shadow-md shadow-red-100 transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50';
const panelClass =
    'min-w-0 overflow-hidden rounded-3xl border border-[#f0c9c5] bg-[#fffafa] p-4 lg:p-6';

export default function Sanctions({
    sanctions,
    employees,
    punishments,
    records,
    filters,
    statusMessage,
}: Props) {
    const confirmAction = useConfirmation();
    const [catalogExpanded, setCatalogExpanded] = useState(false);
    const [historyExpanded, setHistoryExpanded] = useState(
        !!filters.search || !!filters.punishment || records.current_page > 1,
    );
    const [catalogOpen, setCatalogOpen] = useState(false);
    const [editing, setEditing] = useState<Sanction | null>(null);
    const catalog = useForm({ name: '', description: '' });
    const issue = useForm({
        sanction_id: '',
        employee_id: '',
        punishment: '',
        notes: '',
        request_id: '',
    });
    const [reviewOpen, setReviewOpen] = useState(false);
    const [detail, setDetail] = useState<IssuedSanction | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [deleteError, setDeleteError] = useState('');
    const [historySearch, setHistorySearch] = useState(filters.search);
    const [historyPunishment, setHistoryPunishment] = useState(
        filters.punishment,
    );
    const selectedSanction = sanctions.find(
        (sanction) => String(sanction.id) === issue.data.sanction_id,
    );
    const selectedEmployee =
        employees.find(
            (employee) => String(employee.id) === issue.data.employee_id,
        ) ?? null;

    const openCatalog = (sanction?: Sanction) => {
        setEditing(sanction ?? null);
        catalog.setData({
            name: sanction?.name ?? '',
            description: sanction?.description ?? '',
        });
        catalog.clearErrors();
        setCatalogOpen(true);
    };
    const saveCatalog = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setCatalogOpen(false);
                setCatalogExpanded(true);
                setHistorySearch('');
                setHistoryPunishment('');
            },
        };

        if (editing) {
            catalog.put(`/sanctions/${editing.id}`, options);
        } else {
            catalog.post('/sanctions', options);
        }
    };
    const remove = async (sanction: Sanction) => {
        if (
            !(await confirmAction(
                `Delete ${sanction.name} from the catalog? Previously issued employee records will be kept.`,
            ))
        ) {
            return;
        }

        setDeleteError('');
        router.delete(`/sanctions/${sanction.id}`, {
            preserveScroll: true,
            onStart: () => setDeletingId(sanction.id),
            onSuccess: () => {
                setHistorySearch('');
                setHistoryPunishment('');
            },
            onError: (errors) =>
                setDeleteError(Object.values(errors).join(' ')),
            onFinish: () => setDeletingId(null),
        });
    };
    const reviewIssue = () => {
        issue.clearErrors();

        if (!selectedSanction) {
            issue.setError('sanction_id', 'Select a sanction.');
        }

        if (!selectedEmployee) {
            issue.setError('employee_id', 'Select an employee.');
        }

        if (!issue.data.punishment) {
            issue.setError('punishment', 'Select a punishment level.');
        }

        if (!selectedSanction || !selectedEmployee || !issue.data.punishment) {
            return;
        }

        if (!issue.data.request_id) {
            issue.setData('request_id', crypto.randomUUID());
        }

        setReviewOpen(true);
    };

    return (
        <main className="min-h-full bg-[#f6f6f9] p-4 text-[#17202d] lg:p-6">
            <Head title="Sanctions" />
            <div className="mx-auto max-w-[1600px] space-y-6 rounded-3xl bg-white p-5 shadow-sm lg:p-7">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-3 text-2xl font-extrabold">
                            <Gavel className="size-7 text-[#8b2525]" />
                            Sanctions
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Add sanction names and descriptions, then issue them
                            with a punishment level.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => openCatalog()}
                        className={primaryClass}
                    >
                        <Plus className="size-4" />
                        Add Sanction
                    </button>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                {deleteError && <Alert severity="error">{deleteError}</Alert>}
                <section
                    className={panelClass}
                    aria-labelledby="issue-sanction-heading"
                >
                    <h2
                        id="issue-sanction-heading"
                        className="mb-4 flex items-center gap-2 text-xl font-extrabold"
                    >
                        <UserRound className="size-5" />
                        Issue Sanction to Employee
                    </h2>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            reviewIssue();
                        }}
                    >
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-12">
                            <div className="xl:col-span-4">
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Sanction"
                                    value={issue.data.sanction_id}
                                    disabled={issue.processing}
                                    onChange={(event) =>
                                        issue.setData(
                                            'sanction_id',
                                            event.target.value,
                                        )
                                    }
                                    error={!!issue.errors.sanction_id}
                                    helperText={issue.errors.sanction_id}
                                >
                                    <MenuItem value="">
                                        Select Sanction
                                    </MenuItem>
                                    {sanctions.map((sanction) => (
                                        <MenuItem
                                            key={sanction.id}
                                            value={String(sanction.id)}
                                        >
                                            {sanction.name}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </div>
                            <div className="xl:col-span-3">
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Punishment"
                                    value={issue.data.punishment}
                                    disabled={issue.processing}
                                    onChange={(event) =>
                                        issue.setData(
                                            'punishment',
                                            event.target.value,
                                        )
                                    }
                                    error={!!issue.errors.punishment}
                                    helperText={issue.errors.punishment}
                                >
                                    <MenuItem value="">
                                        Select Punishment
                                    </MenuItem>
                                    {punishments.map((punishment) => (
                                        <MenuItem
                                            key={punishment}
                                            value={punishment}
                                        >
                                            {punishment}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </div>
                            <div className="xl:col-span-3">
                                <Autocomplete
                                    options={employees}
                                    value={selectedEmployee}
                                    disabled={issue.processing}
                                    isOptionEqualToValue={(option, value) =>
                                        option.id === value.id
                                    }
                                    getOptionLabel={(employee) =>
                                        `${employee.name}${employee.username ? ` (${employee.username})` : ''} — ${roleLabel(employee.role)}`
                                    }
                                    onChange={(_, employee) =>
                                        issue.setData(
                                            'employee_id',
                                            employee ? String(employee.id) : '',
                                        )
                                    }
                                    renderInput={(params) => (
                                        <TextField
                                            {...params}
                                            size="small"
                                            label="Employee"
                                            placeholder="Search name or employee ID"
                                            error={!!issue.errors.employee_id}
                                            helperText={
                                                issue.errors.employee_id
                                            }
                                        />
                                    )}
                                />
                            </div>
                            <div className="xl:col-span-2">
                                <TextField
                                    fullWidth
                                    size="small"
                                    label="Notes"
                                    placeholder="Optional"
                                    value={issue.data.notes}
                                    disabled={issue.processing}
                                    onChange={(event) =>
                                        issue.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    multiline
                                    maxRows={4}
                                    slotProps={{
                                        htmlInput: { maxLength: 5000 },
                                    }}
                                    error={!!issue.errors.notes}
                                    helperText={issue.errors.notes}
                                />
                            </div>
                        </div>
                        <div className="mt-4 flex flex-wrap items-center gap-4">
                            <button
                                type="submit"
                                disabled={
                                    issue.processing ||
                                    !sanctions.length ||
                                    !employees.length
                                }
                                className={`${primaryClass} min-w-36`}
                            >
                                <Send className="size-4" />
                                Issue Sanction
                            </button>
                            <span className="text-xs text-slate-500">
                                Available for all employee roles. Review the
                                details before issuing.
                            </span>
                        </div>
                        {!sanctions.length && (
                            <p className="mt-3 text-sm text-slate-500">
                                Add a sanction to the catalog to get started.
                            </p>
                        )}
                        {issue.errors.request_id && (
                            <p
                                role="alert"
                                className="mt-3 text-sm text-red-700"
                            >
                                {issue.errors.request_id}
                            </p>
                        )}
                    </form>
                </section>
                <section
                    className={panelClass}
                    aria-labelledby="sanction-catalog-heading"
                >
                    <h2
                        id="sanction-catalog-heading"
                        className="text-xl font-extrabold"
                    >
                        <button
                            type="button"
                            aria-expanded={catalogExpanded}
                            aria-controls="sanction-catalog-content"
                            onClick={() => setCatalogExpanded(!catalogExpanded)}
                            className="flex w-full items-center gap-2 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700"
                        >
                            <ClipboardList className="size-5 shrink-0" />
                            <span className="flex-1">
                                Sanction Catalog{' '}
                                <span className="text-sm font-normal text-slate-500">
                                    ({sanctions.length})
                                </span>
                            </span>
                            <ChevronDown
                                className={`size-5 shrink-0 text-[#8b2525] transition-transform ${catalogExpanded ? 'rotate-180' : ''}`}
                            />
                        </button>
                    </h2>
                    <div
                        id="sanction-catalog-content"
                        hidden={!catalogExpanded}
                        className="mt-4"
                    >
                        <div
                            tabIndex={0}
                            role="region"
                            aria-label="Sanction catalog table"
                            className="max-h-[420px] [scrollbar-gutter:stable] overflow-auto overscroll-contain rounded-2xl border border-slate-200 bg-white"
                        >
                            <table className="w-full min-w-[800px] text-left text-xs">
                                <thead className="sticky top-0 z-10 bg-[#f7f8fa]">
                                    <tr className="border-b border-slate-200 bg-[#f7f8fa]">
                                        <th className="p-4">ID</th>
                                        <th className="p-4">Name</th>
                                        <th className="p-4">Description</th>
                                        <th className="p-4">Created</th>
                                        <th className="p-4">Updated</th>
                                        <th className="p-4 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sanctions.map((sanction) => (
                                        <tr
                                            key={sanction.id}
                                            className="border-b border-slate-200 last:border-0"
                                        >
                                            <td className="p-4">
                                                {sanction.id}
                                            </td>
                                            <td className="min-w-40 p-4 font-extrabold">
                                                {sanction.name}
                                            </td>
                                            <td className="min-w-64 p-4 leading-relaxed whitespace-pre-line">
                                                {sanction.description || (
                                                    <span className="text-slate-500">
                                                        No description
                                                    </span>
                                                )}
                                            </td>
                                            <td className="min-w-32 p-4">
                                                {dateTime(sanction.created_at)}
                                            </td>
                                            <td className="min-w-32 p-4">
                                                {sanction.updated_at !==
                                                sanction.created_at
                                                    ? dateTime(
                                                          sanction.updated_at,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="p-4">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        aria-label={`Edit ${sanction.name}`}
                                                        title="Edit sanction"
                                                        onClick={() =>
                                                            openCatalog(
                                                                sanction,
                                                            )
                                                        }
                                                        className="rounded-full border border-blue-500 p-1.5 text-blue-500 hover:bg-blue-50"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        aria-label={`Delete ${sanction.name}`}
                                                        title="Delete sanction"
                                                        disabled={
                                                            deletingId !== null
                                                        }
                                                        onClick={() =>
                                                            remove(sanction)
                                                        }
                                                        className="rounded-full border border-red-500 p-1.5 text-red-500 hover:bg-red-50 disabled:opacity-50"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {!sanctions.length && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="p-10 text-center text-slate-500"
                                            >
                                                No sanctions in the catalog yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <section
                    className={panelClass}
                    aria-labelledby="issued-sanctions-heading"
                >
                    <h2
                        id="issued-sanctions-heading"
                        className="text-xl font-extrabold"
                    >
                        <button
                            type="button"
                            aria-expanded={historyExpanded}
                            aria-controls="issued-sanctions-content"
                            onClick={() => setHistoryExpanded(!historyExpanded)}
                            className="flex w-full items-center gap-2 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700"
                        >
                            <History className="size-5 shrink-0" />
                            <span className="flex-1">
                                Issued Sanctions{' '}
                                <span className="text-sm font-normal text-slate-500">
                                    ({records.total})
                                </span>
                            </span>
                            <ChevronDown
                                className={`size-5 shrink-0 text-[#8b2525] transition-transform ${historyExpanded ? 'rotate-180' : ''}`}
                            />
                        </button>
                    </h2>
                    <div
                        id="issued-sanctions-content"
                        hidden={!historyExpanded}
                        className="mt-4"
                    >
                        <p className="mb-4 text-xs text-slate-500">
                            Employee history · dates shown in Asia/Manila
                        </p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    '/sanctions',
                                    {
                                        search: historySearch,
                                        punishment: historyPunishment,
                                    },
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                );
                            }}
                            className="mb-4 flex flex-wrap items-start gap-3"
                        >
                            <TextField
                                size="small"
                                label="Search employee, ID, or sanction"
                                value={historySearch}
                                onChange={(event) =>
                                    setHistorySearch(event.target.value)
                                }
                                slotProps={{ htmlInput: { maxLength: 100 } }}
                                sx={{
                                    width: { xs: '100%', sm: 300 },
                                    flexGrow: 1,
                                }}
                            />
                            <TextField
                                select
                                size="small"
                                label="Punishment filter"
                                value={historyPunishment}
                                onChange={(event) =>
                                    setHistoryPunishment(event.target.value)
                                }
                                sx={{ width: { xs: '100%', sm: 280 } }}
                            >
                                <MenuItem value="">All punishments</MenuItem>
                                {punishments.map((punishment) => (
                                    <MenuItem
                                        key={punishment}
                                        value={punishment}
                                    >
                                        {punishment}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <Button
                                type="submit"
                                variant="outlined"
                                startIcon={<Search className="size-4" />}
                            >
                                Search
                            </Button>
                            <Button
                                type="button"
                                onClick={() => {
                                    setHistorySearch('');
                                    setHistoryPunishment('');
                                    router.get(
                                        '/sanctions',
                                        {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    );
                                }}
                            >
                                Clear
                            </Button>
                        </form>
                        <div
                            tabIndex={0}
                            role="region"
                            aria-label="Issued sanctions table"
                            className="max-h-[420px] [scrollbar-gutter:stable] overflow-auto overscroll-contain rounded-2xl border border-slate-200 bg-white"
                        >
                            <table className="w-full min-w-[800px] text-left text-xs">
                                <thead className="sticky top-0 z-10 bg-[#f7f8fa]">
                                    <tr className="border-b border-slate-200 bg-[#f7f8fa]">
                                        <th className="p-4">Employee</th>
                                        <th className="p-4">Sanction</th>
                                        <th className="p-4">Punishment</th>
                                        <th className="p-4">Issued By</th>
                                        <th className="p-4">Issued At</th>
                                        <th className="p-4">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.map((record) => (
                                        <tr
                                            key={record.id}
                                            className="border-b border-slate-200 last:border-0"
                                        >
                                            <td className="p-4">
                                                <strong>
                                                    {record.employee_name}
                                                </strong>
                                                <p className="mt-1 text-slate-500">
                                                    {record.employee_username} ·{' '}
                                                    <span className="capitalize">
                                                        {roleLabel(
                                                            record.employee_role,
                                                        )}
                                                    </span>
                                                </p>
                                            </td>
                                            <td className="p-4 font-bold">
                                                {record.sanction_name}
                                            </td>
                                            <td className="p-4">
                                                <span className="inline-block rounded-full bg-red-50 px-3 py-1 font-semibold text-[#9d2924]">
                                                    {record.punishment}
                                                </span>
                                            </td>
                                            <td className="p-4">
                                                {record.issuer_name}
                                            </td>
                                            <td className="p-4">
                                                {dateTime(record.created_at)}
                                            </td>
                                            <td className="p-4">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setDetail(record)
                                                    }
                                                    className="font-bold text-[#ae1b20] hover:underline"
                                                    aria-label={`View sanction ${record.id} for ${record.employee_name}`}
                                                >
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                    {!records.data.length && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="p-10 text-center text-slate-500"
                                            >
                                                {filters.search ||
                                                filters.punishment
                                                    ? 'No issued sanctions match these filters.'
                                                    : 'No sanctions have been issued yet.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="mt-4 flex items-center justify-between gap-3 text-xs text-slate-500">
                            <span>
                                {records.total} records · Page{' '}
                                {records.current_page} of {records.last_page}
                            </span>
                            <div className="flex gap-3">
                                {records.prev_page_url && (
                                    <Link
                                        href={records.prev_page_url}
                                        preserveScroll
                                        preserveState
                                        className="font-bold text-[#ae1b20]"
                                    >
                                        Previous
                                    </Link>
                                )}
                                {records.next_page_url && (
                                    <Link
                                        href={records.next_page_url}
                                        preserveScroll
                                        preserveState
                                        className="font-bold text-[#ae1b20]"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog
                open={catalogOpen}
                onClose={() => {
                    if (!catalog.processing) {
                        setCatalogOpen(false);
                    }
                }}
                fullWidth
                maxWidth="sm"
                aria-labelledby="catalog-dialog-title"
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        saveCatalog();
                    }}
                >
                    <DialogTitle id="catalog-dialog-title">
                        {editing ? 'Edit Sanction' : 'Add Sanction'}
                    </DialogTitle>
                    <DialogContent>
                        <div className="grid gap-4 pt-2">
                            <TextField
                                label="Sanction name"
                                autoFocus
                                required
                                fullWidth
                                value={catalog.data.name}
                                disabled={catalog.processing}
                                onChange={(event) =>
                                    catalog.setData('name', event.target.value)
                                }
                                slotProps={{ htmlInput: { maxLength: 150 } }}
                                error={!!catalog.errors.name}
                                helperText={catalog.errors.name}
                            />
                            <TextField
                                label="Description"
                                fullWidth
                                multiline
                                minRows={4}
                                value={catalog.data.description}
                                disabled={catalog.processing}
                                onChange={(event) =>
                                    catalog.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                slotProps={{ htmlInput: { maxLength: 5000 } }}
                                error={!!catalog.errors.description}
                                helperText={
                                    catalog.errors.description || 'Optional'
                                }
                            />
                            {editing && (
                                <p className="text-xs text-slate-500">
                                    Changes apply to future sanctions. Issued
                                    records keep their original details.
                                </p>
                            )}
                        </div>
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 3 }}>
                        <Button
                            disabled={catalog.processing}
                            onClick={() => setCatalogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={catalog.processing}
                        >
                            {catalog.processing ? 'Saving…' : 'Save Sanction'}
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
            <Dialog
                open={reviewOpen}
                onClose={() => {
                    if (!issue.processing) {
                        setReviewOpen(false);
                    }
                }}
                fullWidth
                maxWidth="sm"
                aria-labelledby="issue-dialog-title"
            >
                <DialogTitle id="issue-dialog-title">
                    Confirm sanction
                </DialogTitle>
                <DialogContent>
                    <dl className="space-y-4 text-sm">
                        <div>
                            <dt className="text-xs text-slate-500">Employee</dt>
                            <dd className="font-bold">
                                {selectedEmployee?.name}{' '}
                                {selectedEmployee?.username &&
                                    `(${selectedEmployee.username})`}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-500">Sanction</dt>
                            <dd className="font-bold">
                                {selectedSanction?.name}
                            </dd>
                            {selectedSanction?.description && (
                                <dd className="mt-1 whitespace-pre-line text-slate-600">
                                    {selectedSanction.description}
                                </dd>
                            )}
                        </div>
                        <div>
                            <dt className="text-xs text-slate-500">
                                Punishment
                            </dt>
                            <dd className="font-bold text-[#ae1b20]">
                                {issue.data.punishment}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-500">Notes</dt>
                            <dd className="whitespace-pre-line">
                                {issue.data.notes || 'No notes'}
                            </dd>
                        </div>
                    </dl>
                    <p className="mt-4 text-xs text-slate-500">
                        This records the disciplinary action in employee
                        history. Account status is managed separately in
                        Employees.
                    </p>
                    {Object.keys(issue.errors).length > 0 && (
                        <Alert severity="error" sx={{ mt: 2 }}>
                            {Object.values(issue.errors).join(' ')}
                        </Alert>
                    )}
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 3 }}>
                    <Button
                        disabled={issue.processing}
                        onClick={() => setReviewOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={issue.processing}
                        onClick={() =>
                            issue.post('/sanctions/issue', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setReviewOpen(false);
                                    issue.reset();
                                    setHistoryExpanded(true);
                                    setHistorySearch('');
                                    setHistoryPunishment('');
                                },
                            })
                        }
                    >
                        {issue.processing ? 'Issuing…' : 'Confirm & Issue'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!detail}
                onClose={() => setDetail(null)}
                fullWidth
                maxWidth="sm"
                aria-labelledby="sanction-detail-title"
            >
                <DialogTitle id="sanction-detail-title">
                    Issued sanction #{detail?.id}
                </DialogTitle>
                <DialogContent>
                    {detail && (
                        <dl className="space-y-4 text-sm">
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Employee at time of issue
                                </dt>
                                <dd className="font-bold">
                                    {detail.employee_name}{' '}
                                    {detail.employee_username}
                                </dd>
                                <dd className="text-slate-500 capitalize">
                                    {roleLabel(detail.employee_role)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Sanction
                                </dt>
                                <dd className="font-bold">
                                    {detail.sanction_name}
                                </dd>
                                <dd className="mt-1 whitespace-pre-line">
                                    {detail.sanction_description ||
                                        'No description'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Punishment
                                </dt>
                                <dd className="font-bold text-[#ae1b20]">
                                    {detail.punishment}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Notes
                                </dt>
                                <dd className="whitespace-pre-line">
                                    {detail.notes || 'No notes'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Issued by
                                </dt>
                                <dd>
                                    {detail.issuer_name} ·{' '}
                                    {dateTime(detail.created_at)}
                                </dd>
                            </div>
                        </dl>
                    )}
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 3 }}>
                    <Button onClick={() => setDetail(null)}>Close</Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}

Sanctions.layout = {
    breadcrumbs: [{ title: 'Sanctions', href: '/sanctions' }],
};
