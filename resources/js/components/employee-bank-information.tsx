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
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

type Employee = { id: number; name: string; username: string };
type Account = {
    id: number;
    user_id: number;
    party_type: string;
    bank_name: string;
    branch: string | null;
    masked_number: string;
    version: number;
    employee: Employee;
};
type Detail = Account & {
    account_holder: string;
    account_number: string;
    notes: string | null;
};
type Result = {
    employees: Employee[];
    accounts: {
        data: Account[];
        current_page: number;
        last_page: number;
        total: number;
    };
};
const empty = {
    request_key: '',
    user_id: '',
    party_type: 'payee',
    bank_name: '',
    branch: '',
    account_holder: '',
    account_number: '',
    notes: '',
    version: 1,
};
const partyLabel = (value: string) =>
    value === 'both' ? 'Payee & payor' : value === 'payor' ? 'Payor' : 'Payee';

export default function EmployeeBankInformation() {
    const [result, setResult] = useState<Result | null>(null);
    const [filters, setFilters] = useState({
        search: '',
        party_type: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState<Account | null>(null);
    const form = useForm(empty);
    const deletion = useForm({ version: 1 });
    useEffect(() => {
        const controller = new AbortController();
        fetch(`/accounting/bank-accounts?${new URLSearchParams(applied)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
throw new Error('Unable to load bank information.');
}

                return (await response.json()) as Result;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setResult(data);
                    setError('');
                }
            })
            .catch((error: Error) => {
                if (!controller.signal.aborted) {
setError(error.message);
}
            })
            .finally(() => {
                if (!controller.signal.aborted) {
setLoading(false);
}
            });

        return () => controller.abort();
    }, [applied, revision]);
    const refresh = (message: string) => {
        setNotice(message);
        setLoading(true);
        setRevision((value) => value + 1);
    };
    const close = () => {
        setOpen(false);
        form.setData(empty);
        form.clearErrors();
    };
    const edit = async (row: Account) => {
        setLoading(true);
        setError('');

        try {
            const response = await fetch(
                `/accounting/bank-accounts/${row.id}`,
                { headers: { Accept: 'application/json' }, cache: 'no-store' },
            );

            if (!response.ok) {
throw new Error(
                    'Unable to load this bank account. Refresh the list and try again.',
                );
}

            const detail: Detail = await response.json();
            setEditingId(detail.id);
            form.clearErrors();
            form.setData({
                request_key: '',
                user_id: String(detail.user_id),
                party_type: detail.party_type,
                bank_name: detail.bank_name,
                branch: detail.branch || '',
                account_holder: detail.account_holder,
                account_number: detail.account_number,
                notes: detail.notes || '',
                version: detail.version,
            });
            setOpen(true);
        } catch (error) {
            setError(
                error instanceof Error
                    ? error.message
                    : 'Unable to load bank account.',
            );
        } finally {
            setLoading(false);
        }
    };
    const title = (text: string, onClose: () => void, busy: boolean) => (
        <DialogTitle className="flex items-center justify-between gap-3">
            {text}
            <IconButton
                aria-label="Close modal"
                onClick={onClose}
                disabled={busy}
            >
                <X />
            </IconButton>
        </DialogTitle>
    );
    const showErrors = (errors: Record<string, string>) =>
        Object.values(errors).map((message, i) => (
            <Alert key={i} severity="error">
                {message}
            </Alert>
        ));

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-bold">
                        Employee bank information
                    </h2>
                    <p className="text-sm text-slate-500">
                        Manage accounts for employees receiving payments
                        (payees) or making payments (payors).
                    </p>
                </div>
                <Button
                    variant="contained"
                    startIcon={<Plus size={17} />}
                    disabled={loading}
                    onClick={() => {
                        setEditingId(null);
                        form.clearErrors();
                        form.setData({
                            ...empty,
                            request_key: crypto.randomUUID(),
                        });
                        setOpen(true);
                    }}
                >
                    Add bank information
                </Button>
            </div>
            {notice && <Alert severity="success">{notice}</Alert>}
            {error && <Alert severity="error">{error}</Alert>}
            <form
                className="grid gap-3 sm:grid-cols-[1fr_180px_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setLoading(true);
                    setApplied({ ...filters, page: '1' });
                }}
            >
                <TextField
                    size="small"
                    label="Search employee name or ID"
                    value={filters.search}
                    onChange={(e) =>
                        setFilters({ ...filters, search: e.target.value })
                    }
                />
                <TextField
                    size="small"
                    select
                    label="Payee / payor"
                    value={filters.party_type}
                    onChange={(e) =>
                        setFilters({ ...filters, party_type: e.target.value })
                    }
                >
                    <MenuItem value="">All</MenuItem>
                    <MenuItem value="payee">Payee</MenuItem>
                    <MenuItem value="payor">Payor</MenuItem>
                    <MenuItem value="both">Payee & payor</MenuItem>
                </TextField>
                <Button type="submit" variant="outlined" disabled={loading}>
                    Filter accounts
                </Button>
            </form>
            <p className="text-sm text-slate-500">
                Account numbers are masked here. Open Edit to view or update the
                full details.
            </p>
            <div
                className="max-h-[60vh] overflow-auto rounded-xl border border-slate-200"
                aria-busy={loading}
            >
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="sticky top-0 bg-red-50">
                        <tr>
                            {[
                                'Employee',
                                'Payee / payor',
                                'Bank / branch',
                                'Account number',
                                'Actions',
                            ].map((label) => (
                                <th key={label} className="p-3">
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {result?.accounts.data.map((row) => (
                            <tr
                                key={row.id}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">
                                    <p className="font-semibold">
                                        {row.employee.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.employee.username}
                                    </p>
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        label={partyLabel(row.party_type)}
                                    />
                                </td>
                                <td className="max-w-56 p-3 break-words">
                                    <p>{row.bank_name}</p>
                                    <p className="text-xs text-slate-500">
                                        {row.branch}
                                    </p>
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    {row.masked_number}
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    <IconButton
                                        aria-label={`Edit bank information for ${row.employee.name}`}
                                        disabled={loading}
                                        onClick={() => edit(row)}
                                    >
                                        <Pencil size={17} />
                                    </IconButton>
                                    <IconButton
                                        color="error"
                                        aria-label={`Delete bank information for ${row.employee.name}`}
                                        disabled={loading}
                                        onClick={() => {
                                            deletion.clearErrors();
                                            deletion.setData(
                                                'version',
                                                row.version,
                                            );
                                            setDeleting(row);
                                        }}
                                    >
                                        <Trash2 size={17} />
                                    </IconButton>
                                </td>
                            </tr>
                        ))}
                        {!loading && !result?.accounts.data.length && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="p-8 text-center text-slate-500"
                                >
                                    No bank information found. Add an employee’s
                                    bank account to get started.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                <span>
                    {loading
                        ? 'Loading…'
                        : `${result?.accounts.total || 0} accounts`}{' '}
                    · Page {result?.accounts.current_page || 1} of{' '}
                    {result?.accounts.last_page || 1}
                </span>
                <div>
                    <Button
                        disabled={
                            loading ||
                            !result ||
                            result.accounts.current_page <= 1
                        }
                        onClick={() => {
                            setLoading(true);
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.accounts.current_page || 1) - 1,
                                ),
                            });
                        }}
                    >
                        Previous
                    </Button>
                    <Button
                        disabled={
                            loading ||
                            !result ||
                            result.accounts.current_page >=
                                result.accounts.last_page
                        }
                        onClick={() => {
                            setLoading(true);
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.accounts.current_page || 1) + 1,
                                ),
                            });
                        }}
                    >
                        Next
                    </Button>
                </div>
            </div>
            <Dialog
                open={open}
                fullWidth
                maxWidth="sm"
                onClose={() => !form.processing && close()}
            >
                {title(
                    editingId
                        ? 'Edit bank information'
                        : 'Add bank information',
                    close,
                    form.processing,
                )}
                <DialogContent dividers>
                    <div className="grid gap-5 pt-2">
                        {showErrors(form.errors)}
                        <TextField
                            select
                            fullWidth
                            required
                            label="Employee"
                            value={form.data.user_id}
                            onChange={(e) =>
                                form.setData('user_id', String(e.target.value))
                            }
                        >
                            {result?.employees.map((employee) => (
                                <MenuItem
                                    key={employee.id}
                                    value={String(employee.id)}
                                >
                                    {employee.name} · {employee.username}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="Payment role"
                            value={form.data.party_type}
                            onChange={(e) =>
                                form.setData('party_type', e.target.value)
                            }
                        >
                            <MenuItem value="payee">
                                Payee — receives payments
                            </MenuItem>
                            <MenuItem value="payor">
                                Payor — makes payments
                            </MenuItem>
                            <MenuItem value="both">
                                Both payee and payor
                            </MenuItem>
                        </TextField>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextField
                                label="Bank name"
                                required
                                value={form.data.bank_name}
                                onChange={(e) =>
                                    form.setData('bank_name', e.target.value)
                                }
                            />
                            <TextField
                                label="Branch (optional)"
                                value={form.data.branch}
                                onChange={(e) =>
                                    form.setData('branch', e.target.value)
                                }
                            />
                        </div>
                        <TextField
                            required
                            autoComplete="off"
                            label="Account holder name"
                            value={form.data.account_holder}
                            onChange={(e) =>
                                form.setData('account_holder', e.target.value)
                            }
                        />
                        <TextField
                            required
                            autoComplete="off"
                            label="Account number"
                            value={form.data.account_number}
                            onChange={(e) =>
                                form.setData('account_number', e.target.value)
                            }
                            helperText="Use the bank account number, not a card number, PIN or password."
                        />
                        <TextField
                            multiline
                            minRows={2}
                            label="Notes (optional)"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={form.processing}
                        onClick={() => {
                            const options = {
                                preserveScroll: true,
                                onSuccess: () => {
                                    close();
                                    refresh('Bank information saved.');
                                },
                            };

                            if (editingId) {
form.patch(
                                    `/accounting/bank-accounts/${editingId}`,
                                    options,
                                );
} else {
form.post('/accounting/bank-accounts', options);
}
                        }}
                    >
                        Save bank information
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!deleting}
                fullWidth
                maxWidth="xs"
                onClose={() => !deletion.processing && setDeleting(null)}
            >
                {title(
                    'Delete bank information?',
                    () => setDeleting(null),
                    deletion.processing,
                )}
                <DialogContent dividers>
                    <div className="space-y-4">
                        {showErrors(deletion.errors)}
                        <p>
                            Delete {deleting?.employee.name}’s{' '}
                            {deleting?.bank_name} account ending in{' '}
                            {deleting?.masked_number.slice(-4)}?
                        </p>
                        <p className="text-sm text-slate-500">
                            This removes the saved bank details. Payroll and
                            payment records remain available.
                        </p>
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        onClick={() => setDeleting(null)}
                        disabled={deletion.processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        color="error"
                        variant="contained"
                        disabled={deletion.processing}
                        onClick={() =>
                            deletion.delete(
                                `/accounting/bank-accounts/${deleting?.id}`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setDeleting(null);
                                        refresh('Bank information deleted.');
                                    },
                                },
                            )
                        }
                    >
                        Delete bank information
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
}
