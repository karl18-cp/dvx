import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { Download, FileSignature, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';

type Employee = {
    id: number;
    name: string;
    username: string;
    role: string;
    status: string;
};
type Contract = {
    id: number;
    user_id: number;
    contract_type: 'training' | 'employment';
    title: string;
    reference_number: string | null;
    effective_date: string;
    end_date: string | null;
    status: string;
    notes: string | null;
    original_filename: string;
    mime_type: string;
    file_size: number;
    archived_at: string | null;
    archive_reason: string | null;
    employee: Employee;
    creator?: { name: string };
    updater?: { name: string };
};
type Result = {
    records: {
        data: Contract[];
        current_page: number;
        last_page: number;
        total: number;
    };
    summary: {
        training: number;
        employment: number;
        active: number;
        archived: number;
    };
};

const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
const bytes = (value: number) =>
    value >= 1024 * 1024
        ? `${(value / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(value / 1024))} KB`;

export default function EmployeeContracts({
    employees,
}: {
    employees: Employee[];
}) {
    const [result, setResult] = useState<Result | null>(null);
    const [filters, setFilters] = useState({
        search: '',
        type: '',
        status: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Contract | null>(null);
    const [detail, setDetail] = useState<Contract | null>(null);
    const [archiveOpen, setArchiveOpen] = useState(false);
    const form = useForm({
        request_key: '',
        user_id: '',
        contract_type: 'employment',
        title: '',
        reference_number: '',
        effective_date: today(),
        end_date: '',
        status: 'active',
        notes: '',
        document: null as File | null,
    });
    const archive = useForm({ archive_reason: '' });

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        fetch(
            `/accounting/contracts?${new URLSearchParams(applied).toString()}`,
            {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(
                        data.message || 'Unable to load contracts.',
                    );
                }
                return data as Result;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setResult(data);
                    setError('');
                }
            })
            .catch((reason: Error) => {
                if (!controller.signal.aborted) setError(reason.message);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [applied, revision]);

    const refresh = (message: string) => {
        setNotice(message);
        setRevision((value) => value + 1);
    };
    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData({
            request_key: crypto.randomUUID(),
            user_id: '',
            contract_type: 'employment',
            title: '',
            reference_number: '',
            effective_date: today(),
            end_date: '',
            status: 'active',
            notes: '',
            document: null,
        });
        setFormOpen(true);
    };
    const openEdit = (record: Contract) => {
        setEditing(record);
        form.clearErrors();
        form.setData({
            request_key: '',
            user_id: String(record.user_id),
            contract_type: record.contract_type,
            title: record.title,
            reference_number: record.reference_number || '',
            effective_date: record.effective_date,
            end_date: record.end_date || '',
            status: record.status,
            notes: record.notes || '',
            document: null,
        });
        setDetail(null);
        setFormOpen(true);
    };
    const inspect = async (id: number) => {
        setLoading(true);
        setError('');
        try {
            const response = await fetch(`/accounting/contracts/${id}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('Unable to load this contract.');
            setDetail(await response.json());
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to load this contract.',
            );
        } finally {
            setLoading(false);
        }
    };
    const submit = () => {
        const url = editing
            ? `/accounting/contracts/${editing.id}/update`
            : '/accounting/contracts';
        form.post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setFormOpen(false);
                refresh(editing ? 'Contract updated.' : 'Contract stored.');
            },
        });
    };
    const busy = form.processing || archive.processing;
    const errorList = (items: Record<string, string | undefined>) =>
        Object.values(items).map((message, index) =>
            message ? (
                <Alert key={index} severity="error">
                    {message}
                </Alert>
            ) : null,
        );

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 text-xl font-bold">
                        <FileSignature className="text-red-700" size={22} />
                        Employee contracts
                    </h2>
                    <p className="text-sm text-slate-500">
                        Keep private training and employment contracts attached
                        to the correct employee account.
                    </p>
                </div>
                <Button
                    variant="contained"
                    startIcon={<Plus size={17} />}
                    onClick={openCreate}
                >
                    Add contract
                </Button>
            </div>
            {notice && (
                <Alert severity="success" onClose={() => setNotice('')}>
                    {notice}
                </Alert>
            )}
            {error && (
                <Alert severity="error" onClose={() => setError('')}>
                    {error}
                </Alert>
            )}
            <div className="grid gap-3 sm:grid-cols-4">
                {[
                    ['Training contracts', result?.summary.training ?? 0],
                    ['Employment contracts', result?.summary.employment ?? 0],
                    ['Active', result?.summary.active ?? 0],
                    ['Archived', result?.summary.archived ?? 0],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-xl border border-slate-200 p-4"
                    >
                        <p className="text-xs text-slate-500">{label}</p>
                        <p className="mt-1 text-2xl font-bold">{value}</p>
                    </div>
                ))}
            </div>
            <form
                className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setApplied({ ...filters, page: '1' });
                }}
            >
                <TextField
                    size="small"
                    label="Search employee, title, or reference"
                    value={filters.search}
                    onChange={(event) =>
                        setFilters({ ...filters, search: event.target.value })
                    }
                />
                <TextField
                    size="small"
                    select
                    label="Contract type"
                    value={filters.type}
                    onChange={(event) =>
                        setFilters({ ...filters, type: event.target.value })
                    }
                >
                    <MenuItem value="">All types</MenuItem>
                    <MenuItem value="training">Training</MenuItem>
                    <MenuItem value="employment">Employment</MenuItem>
                </TextField>
                <TextField
                    size="small"
                    select
                    label="Status"
                    value={filters.status}
                    onChange={(event) =>
                        setFilters({ ...filters, status: event.target.value })
                    }
                >
                    <MenuItem value="">Current records</MenuItem>
                    {[
                        'draft',
                        'active',
                        'expired',
                        'terminated',
                        'superseded',
                        'archived',
                    ].map((status) => (
                        <MenuItem key={status} value={status}>
                            {status}
                        </MenuItem>
                    ))}
                </TextField>
                <Button type="submit" variant="outlined">
                    Filter
                </Button>
            </form>
            <div className="overflow-auto rounded-xl border border-slate-200">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="bg-red-50 text-red-800">
                        <tr>
                            <th className="p-3">Employee</th>
                            <th className="p-3">Contract</th>
                            <th className="p-3">Effective period</th>
                            <th className="p-3">Document</th>
                            <th className="p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {result?.records.data.map((record) => (
                            <tr
                                key={record.id}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">
                                    <p className="font-bold">
                                        {record.employee.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {record.employee.username} ·{' '}
                                        {record.employee.role.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </p>
                                </td>
                                <td className="p-3">
                                    <p className="font-semibold">
                                        {record.title}
                                    </p>
                                    <p className="text-xs text-slate-500 capitalize">
                                        {record.contract_type} contract
                                        {record.reference_number
                                            ? ` · ${record.reference_number}`
                                            : ''}
                                    </p>
                                </td>
                                <td className="p-3">
                                    {record.effective_date}
                                    <span className="text-slate-400"> to </span>
                                    {record.end_date || 'Open-ended'}
                                </td>
                                <td className="p-3">
                                    <p>{record.original_filename}</p>
                                    <p className="text-xs text-slate-500">
                                        {bytes(record.file_size)}
                                    </p>
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        color={
                                            record.archived_at
                                                ? 'default'
                                                : record.status === 'active'
                                                  ? 'success'
                                                  : 'warning'
                                        }
                                        label={
                                            record.archived_at
                                                ? 'archived'
                                                : record.status
                                        }
                                    />
                                </td>
                                <td className="p-3 text-right">
                                    <Button
                                        disabled={loading}
                                        onClick={() => inspect(record.id)}
                                    >
                                        Review
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {!loading && !result?.records.data.length && (
                    <p className="p-10 text-center text-slate-500">
                        No contracts match this view.
                    </p>
                )}
            </div>
            {result && result.records.last_page > 1 && (
                <div className="flex justify-end gap-3">
                    <Button
                        disabled={result.records.current_page <= 1}
                        onClick={() =>
                            setApplied({
                                ...applied,
                                page: String(result.records.current_page - 1),
                            })
                        }
                    >
                        Previous
                    </Button>
                    <span className="py-2 text-sm">
                        Page {result.records.current_page} of{' '}
                        {result.records.last_page}
                    </span>
                    <Button
                        disabled={
                            result.records.current_page >=
                            result.records.last_page
                        }
                        onClick={() =>
                            setApplied({
                                ...applied,
                                page: String(result.records.current_page + 1),
                            })
                        }
                    >
                        Next
                    </Button>
                </div>
            )}

            <Dialog
                open={formOpen}
                onClose={() => !busy && setFormOpen(false)}
                fullWidth
                maxWidth="md"
            >
                <DialogTitle className="flex items-center justify-between">
                    {editing
                        ? 'Edit employee contract'
                        : 'Add employee contract'}
                    <IconButton
                        aria-label="Close modal"
                        disabled={busy}
                        onClick={() => setFormOpen(false)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    <div className="grid gap-4 pt-2">
                        <Alert severity="info">
                            Contract files are stored privately and can only be
                            accessed by Admin and Accounting accounts.
                        </Alert>
                        {errorList(form.errors)}
                        <TextField
                            select
                            required
                            label="Employee"
                            value={form.data.user_id}
                            onChange={(event) =>
                                form.setData('user_id', event.target.value)
                            }
                        >
                            {employees.map((employee) => (
                                <MenuItem key={employee.id} value={employee.id}>
                                    {employee.name} · {employee.username} ·{' '}
                                    {employee.role.replaceAll('_', ' ')}
                                </MenuItem>
                            ))}
                        </TextField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                select
                                required
                                label="Contract type"
                                value={form.data.contract_type}
                                onChange={(event) =>
                                    form.setData(
                                        'contract_type',
                                        event.target.value,
                                    )
                                }
                            >
                                <MenuItem value="training">
                                    Training contract
                                </MenuItem>
                                <MenuItem value="employment">
                                    Employment contract
                                </MenuItem>
                            </TextField>
                            <TextField
                                select
                                required
                                label="Status"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                {[
                                    'draft',
                                    'active',
                                    'expired',
                                    'terminated',
                                    'superseded',
                                ].map((status) => (
                                    <MenuItem key={status} value={status}>
                                        {status}
                                    </MenuItem>
                                ))}
                            </TextField>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                required
                                label="Contract title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <TextField
                                label="Reference number"
                                value={form.data.reference_number}
                                onChange={(event) =>
                                    form.setData(
                                        'reference_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                type="date"
                                required
                                label="Effective date"
                                value={form.data.effective_date}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_date',
                                        event.target.value,
                                    )
                                }
                                slotProps={{ inputLabel: { shrink: true } }}
                            />
                            <TextField
                                type="date"
                                label="End date (optional)"
                                value={form.data.end_date}
                                onChange={(event) =>
                                    form.setData('end_date', event.target.value)
                                }
                                slotProps={{ inputLabel: { shrink: true } }}
                            />
                        </div>
                        <TextField
                            multiline
                            minRows={3}
                            label="Notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <div className="rounded-xl border border-slate-200 p-4">
                            <label className="mb-2 block text-sm font-semibold">
                                {editing
                                    ? 'Replace contract file (optional)'
                                    : 'Contract file *'}
                            </label>
                            <input
                                type="file"
                                required={!editing}
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                                onChange={(event) =>
                                    form.setData(
                                        'document',
                                        event.target.files?.[0] || null,
                                    )
                                }
                            />
                            <p className="mt-2 text-xs text-slate-500">
                                PDF, DOC, DOCX, JPG, PNG, or WebP · up to 10 MB
                            </p>
                        </div>
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={busy}
                        onClick={submit}
                    >
                        {form.processing
                            ? 'Saving…'
                            : editing
                              ? 'Save changes'
                              : 'Store contract'}
                    </Button>
                </DialogActions>
            </Dialog>

            <Dialog
                open={!!detail}
                onClose={() => !busy && setDetail(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Contract details
                    <IconButton
                        aria-label="Close modal"
                        onClick={() => setDetail(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    {detail && (
                        <div className="grid gap-4">
                            <div>
                                <h3 className="text-xl font-bold">
                                    {detail.title}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    {detail.employee.name} ·{' '}
                                    {detail.employee.username}
                                </p>
                            </div>
                            <div className="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                                <div>
                                    <p className="text-slate-500">Type</p>
                                    <p className="font-semibold capitalize">
                                        {detail.contract_type}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-slate-500">Status</p>
                                    <p className="font-semibold capitalize">
                                        {detail.archived_at
                                            ? 'Archived'
                                            : detail.status}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-slate-500">Effective</p>
                                    <p>{detail.effective_date}</p>
                                </div>
                                <div>
                                    <p className="text-slate-500">End date</p>
                                    <p>{detail.end_date || 'Open-ended'}</p>
                                </div>
                            </div>
                            {detail.reference_number && (
                                <p className="text-sm">
                                    Reference: {detail.reference_number}
                                </p>
                            )}
                            {detail.notes && (
                                <p className="text-sm whitespace-pre-wrap">
                                    {detail.notes}
                                </p>
                            )}
                            <a
                                className="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold text-red-700 hover:bg-red-50"
                                href={`/accounting/contracts/${detail.id}/document`}
                            >
                                <span>
                                    {detail.original_filename} ·{' '}
                                    {bytes(detail.file_size)}
                                </span>
                                <Download size={18} />
                            </a>
                            {detail.archived_at && (
                                <Alert severity="warning">
                                    Archived: {detail.archive_reason}
                                </Alert>
                            )}
                            {errorList(archive.errors)}
                        </div>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3, gap: 1 }}>
                    {detail && !detail.archived_at && (
                        <>
                            <Button onClick={() => openEdit(detail)}>
                                Edit contract
                            </Button>
                            <Button
                                color="error"
                                onClick={() => {
                                    archive.reset();
                                    setArchiveOpen(true);
                                }}
                            >
                                Archive
                            </Button>
                        </>
                    )}
                </DialogActions>
            </Dialog>

            <Dialog
                open={archiveOpen}
                onClose={() => !busy && setArchiveOpen(false)}
                fullWidth
                maxWidth="xs"
            >
                <DialogTitle className="flex items-center justify-between">
                    Archive contract
                    <IconButton
                        aria-label="Close modal"
                        disabled={busy}
                        onClick={() => setArchiveOpen(false)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    <div className="grid gap-4 pt-2">
                        <Alert severity="warning">
                            The record and document will remain available for
                            audit and record keeping.
                        </Alert>
                        {errorList(archive.errors)}
                        <TextField
                            required
                            multiline
                            minRows={3}
                            label="Reason for archiving"
                            value={archive.data.archive_reason}
                            onChange={(event) =>
                                archive.setData(
                                    'archive_reason',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        color="error"
                        variant="contained"
                        disabled={busy || !detail}
                        onClick={() =>
                            detail &&
                            archive.patch(
                                `/accounting/contracts/${detail.id}/archive`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setArchiveOpen(false);
                                        setDetail(null);
                                        refresh('Contract archived.');
                                    },
                                },
                            )
                        }
                    >
                        Confirm archive
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
}
