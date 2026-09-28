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
import { Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { money } from '@/components/training-allowance-details';

type View = 'invoices' | 'receivable' | 'payable';
type RecordRow = {
    id: number;
    direction: 'receivable' | 'payable';
    invoice_number: string | null;
    party_name: string;
    party_email: string | null;
    description: string;
    issue_date: string;
    due_date: string;
    amount_cents: number;
    paid_cents: number;
    balance_cents: number;
    status: string;
    notes: string | null;
    attachment_name: string | null;
    void_reason: string | null;
};
type Detail = RecordRow & {
    payments: {
        id: number;
        payment_date: string;
        amount_cents: number;
        method: string;
        reference: string;
    }[];
};
type Result = {
    records: {
        data: RecordRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    summary: { total_cents: number; paid_cents: number; balance_cents: number };
};
const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
const label = (view: View) =>
    view === 'invoices'
        ? 'Invoice'
        : view === 'receivable'
          ? 'Receivable'
          : 'Payable';

export default function AccountingDocuments({ view }: { view: View }) {
    const [result, setResult] = useState<Result | null>(null);
    const [filters, setFilters] = useState({
        search: '',
        status: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [open, setOpen] = useState(false);
    const [detail, setDetail] = useState<Detail | null>(null);
    const [action, setAction] = useState<'payment' | 'void' | ''>('');
    const record = useForm({
        request_key: '',
        record_type: view,
        direction: view === 'payable' ? 'payable' : 'receivable',
        invoice_number: '',
        party_name: '',
        party_email: '',
        description: '',
        issue_date: today(),
        due_date: today(),
        amount: '',
        notes: '',
        attachment: null as File | null,
    });
    const payment = useForm({
        request_key: '',
        amount: '',
        payment_date: today(),
        method: 'bank_transfer',
        reference: '',
    });
    const voidForm = useForm({ void_reason: '' });
    useEffect(() => {
        const controller = new AbortController();
        fetch(
            `/accounting/documents?${new URLSearchParams({ ...applied, view })}`,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
throw new Error(data.message || 'Unable to load records.');
}

                return data as Result;
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
    }, [applied, revision, view]);
    const refresh = (message: string) => {
        setNotice(message);
        setLoading(true);
        setRevision((value) => value + 1);
    };
    const inspect = async (id: number) => {
        setLoading(true);
        setError('');
        setAction('');
        payment.clearErrors();
        voidForm.clearErrors();

        try {
            const response = await fetch(`/accounting/documents/${id}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) {
throw new Error('Unable to load record.');
}

            setDetail(await response.json());
        } catch (error) {
            setError(
                error instanceof Error
                    ? error.message
                    : 'Unable to load record.',
            );
        } finally {
            setLoading(false);
        }
    };
    const busy = record.processing || payment.processing || voidForm.processing;
    const title = (text: string, close: () => void) => (
        <DialogTitle className="flex items-center justify-between gap-3">
            {text}
            <IconButton
                aria-label="Close modal"
                disabled={busy}
                onClick={close}
            >
                <X />
            </IconButton>
        </DialogTitle>
    );
    const errors = (items: Record<string, string>) =>
        Object.values(items).map((message, i) => (
            <Alert key={i} severity="error">
                {message}
            </Alert>
        ));

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-bold">
                        {view === 'invoices'
                            ? 'Invoices'
                            : view === 'receivable'
                              ? 'Receivables'
                              : 'Payables'}
                    </h2>
                    <p className="text-sm text-slate-500">
                        {view === 'invoices'
                            ? 'Keep client and supplier invoices with their payment history.'
                            : view === 'receivable'
                              ? 'Track amounts clients owe you and collections received.'
                              : 'Track amounts owed to suppliers and payments made.'}
                    </p>
                </div>
                <Button
                    variant="contained"
                    startIcon={<Plus size={17} />}
                    onClick={() => {
                        record.reset();
                        record.clearErrors();
                        record.setData('request_key', crypto.randomUUID());
                        setOpen(true);
                    }}
                >
                    Add {label(view).toLowerCase()}
                </Button>
            </div>
            {notice && <Alert severity="success">{notice}</Alert>}
            {error && <Alert severity="error">{error}</Alert>}
            <div className="grid gap-3 sm:grid-cols-3">
                {[
                    ['Record value', result?.summary.total_cents],
                    ['Payments recorded', result?.summary.paid_cents],
                    ['Outstanding balance', result?.summary.balance_cents],
                ].map(([text, value]) => (
                    <div
                        key={text}
                        className="rounded-xl border border-slate-200 p-4"
                    >
                        <p className="text-sm text-slate-500">{text}</p>
                        <p className="mt-1 text-xl font-bold">
                            {loading ? 'Loading…' : money(Number(value || 0))}
                        </p>
                    </div>
                ))}
            </div>
            <p className="text-xs text-slate-500">
                PHP totals follow the filters and exclude void records. Client
                invoices also appear in Receivables; supplier invoices appear in
                Payables.
            </p>
            <form
                className="grid gap-3 sm:grid-cols-[1fr_170px_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setLoading(true);
                    setApplied({ ...filters, page: '1' });
                }}
            >
                <TextField
                    size="small"
                    label="Search name, invoice or description"
                    value={filters.search}
                    onChange={(e) =>
                        setFilters({ ...filters, search: e.target.value })
                    }
                />
                <TextField
                    size="small"
                    select
                    label="Record status"
                    value={filters.status}
                    onChange={(e) =>
                        setFilters({ ...filters, status: e.target.value })
                    }
                >
                    <MenuItem value="">All statuses</MenuItem>
                    {['open', 'partial', 'overdue', 'settled', 'void'].map(
                        (status) => (
                            <MenuItem key={status} value={status}>
                                {status[0].toUpperCase() + status.slice(1)}
                            </MenuItem>
                        ),
                    )}
                </TextField>
                <Button variant="outlined" type="submit" disabled={loading}>
                    Filter records
                </Button>
            </form>
            <div
                className="max-h-[55vh] overflow-auto rounded-xl border border-slate-200"
                aria-busy={loading}
            >
                <table className="w-full min-w-[920px] text-left text-sm">
                    <thead className="sticky top-0 bg-red-50">
                        <tr>
                            {[
                                'Invoice / record',
                                'Client / supplier',
                                'Due date',
                                'Amount',
                                'Paid',
                                'Balance',
                                'Status',
                                '',
                            ].map((text) => (
                                <th key={text} className="p-3">
                                    {text}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {result?.records.data.map((row) => (
                            <tr
                                key={row.id}
                                className="border-t border-slate-100"
                            >
                                <td className="max-w-60 p-3 break-words">
                                    <p className="font-semibold">
                                        {row.invoice_number || `REC-${row.id}`}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.description}
                                    </p>
                                </td>
                                <td className="max-w-52 p-3 break-words">
                                    {row.party_name}
                                    <p className="text-xs text-slate-500">
                                        {row.direction === 'receivable'
                                            ? 'Receivable'
                                            : 'Payable'}
                                    </p>
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    {row.due_date}
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    {money(row.amount_cents)}
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    {money(row.paid_cents)}
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    {money(row.balance_cents)}
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        label={row.status}
                                        color={
                                            row.status === 'overdue'
                                                ? 'error'
                                                : row.status === 'settled'
                                                  ? 'success'
                                                  : 'default'
                                        }
                                    />
                                </td>
                                <td className="p-3">
                                    <Button
                                        disabled={loading}
                                        onClick={() => inspect(row.id)}
                                    >
                                        View
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {!loading && !result?.records.data.length && (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="p-8 text-center text-slate-500"
                                >
                                    No records for these filters.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500">
                <span>
                    {result?.records.total || 0} records · Page{' '}
                    {result?.records.current_page || 1} of{' '}
                    {result?.records.last_page || 1}
                </span>
                <div>
                    <Button
                        disabled={
                            loading ||
                            !result ||
                            result.records.current_page <= 1
                        }
                        onClick={() => {
                            setLoading(true);
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.records.current_page || 1) - 1,
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
                            result.records.current_page >=
                                result.records.last_page
                        }
                        onClick={() => {
                            setLoading(true);
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.records.current_page || 1) + 1,
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
                onClose={() => !busy && setOpen(false)}
            >
                {title(`Add ${label(view).toLowerCase()}`, () =>
                    setOpen(false),
                )}
                <DialogContent dividers>
                    <div className="grid gap-5 pt-2">
                        {errors(record.errors)}
                        {view === 'invoices' && (
                            <TextField
                                select
                                label="Invoice type"
                                value={record.data.direction}
                                onChange={(e) =>
                                    record.setData('direction', e.target.value)
                                }
                            >
                                <MenuItem value="receivable">
                                    Client invoice — receivable
                                </MenuItem>
                                <MenuItem value="payable">
                                    Supplier invoice — payable
                                </MenuItem>
                            </TextField>
                        )}
                        <TextField
                            label={
                                view === 'invoices'
                                    ? 'Invoice number'
                                    : 'Invoice number (optional)'
                            }
                            required={view === 'invoices'}
                            value={record.data.invoice_number}
                            onChange={(e) =>
                                record.setData('invoice_number', e.target.value)
                            }
                        />
                        <TextField
                            label={
                                record.data.direction === 'receivable'
                                    ? 'Client / payor name'
                                    : 'Supplier / payee name'
                            }
                            required
                            value={record.data.party_name}
                            onChange={(e) =>
                                record.setData('party_name', e.target.value)
                            }
                        />
                        <TextField
                            type="email"
                            label="Contact email (optional)"
                            value={record.data.party_email}
                            onChange={(e) =>
                                record.setData('party_email', e.target.value)
                            }
                        />
                        <TextField
                            required
                            label="Description"
                            value={record.data.description}
                            onChange={(e) =>
                                record.setData('description', e.target.value)
                            }
                        />
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextField
                                type="date"
                                label="Issue date"
                                slotProps={{ inputLabel: { shrink: true } }}
                                value={record.data.issue_date}
                                onChange={(e) =>
                                    record.setData('issue_date', e.target.value)
                                }
                            />
                            <TextField
                                type="date"
                                label="Due date"
                                slotProps={{ inputLabel: { shrink: true } }}
                                value={record.data.due_date}
                                onChange={(e) =>
                                    record.setData('due_date', e.target.value)
                                }
                            />
                        </div>
                        <TextField
                            required
                            label="Total amount (PHP)"
                            slotProps={{ htmlInput: { inputMode: 'decimal' } }}
                            helperText="Enter the final invoice total, including any applicable adjustments."
                            value={record.data.amount}
                            onChange={(e) =>
                                record.setData('amount', e.target.value)
                            }
                        />
                        <TextField
                            multiline
                            minRows={2}
                            label="Notes (optional)"
                            value={record.data.notes}
                            onChange={(e) =>
                                record.setData('notes', e.target.value)
                            }
                        />
                        <label className="grid gap-2 text-sm">
                            Invoice / supporting document (optional)
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={(e) =>
                                    record.setData(
                                        'attachment',
                                        e.target.files?.[0] || null,
                                    )
                                }
                                className="w-full min-w-0 rounded-xl border border-slate-200 p-3"
                            />
                            <span className="text-xs text-slate-500">
                                PDF, JPG, PNG or WebP · up to 5 MB
                            </span>
                        </label>
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={busy}
                        onClick={() =>
                            record.post('/accounting/documents', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    record.reset();
                                    refresh(`${label(view)} recorded.`);
                                },
                            })
                        }
                    >
                        Save record
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!detail}
                fullWidth
                maxWidth="md"
                onClose={() => !busy && setDetail(null)}
            >
                {title(
                    detail?.invoice_number || `Record REC-${detail?.id || ''}`,
                    () => setDetail(null),
                )}
                <DialogContent dividers>
                    {detail && (
                        <div className="grid gap-5">
                            <div>
                                <h3 className="text-xl font-bold break-words">
                                    {detail.party_name}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    {detail.direction === 'receivable'
                                        ? 'Receivable'
                                        : 'Payable'}{' '}
                                    · Issued {detail.issue_date} · Due{' '}
                                    {detail.due_date}
                                </p>
                                {detail.party_email && (
                                    <p className="text-sm break-words">
                                        {detail.party_email}
                                    </p>
                                )}
                            </div>
                            <p className="break-words">{detail.description}</p>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    ['Amount', detail.amount_cents],
                                    ['Paid', detail.paid_cents],
                                    ['Balance', detail.balance_cents],
                                ].map(([text, value]) => (
                                    <div
                                        key={text}
                                        className="rounded-xl bg-slate-50 p-3"
                                    >
                                        <p className="text-sm text-slate-500">
                                            {text}
                                        </p>
                                        <p className="font-bold">
                                            {money(Number(value))}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            {detail.notes && (
                                <p className="break-words whitespace-pre-wrap">
                                    {detail.notes}
                                </p>
                            )}
                            {detail.attachment_name && (
                                <Button
                                    component="a"
                                    href={`/accounting/documents/${detail.id}/attachment`}
                                    variant="outlined"
                                >
                                    Download supporting document
                                </Button>
                            )}
                            {detail.status === 'void' && (
                                <Alert severity="warning">
                                    Voided: {detail.void_reason}
                                </Alert>
                            )}
                            <div>
                                <h4 className="mb-2 font-semibold">
                                    Payment history
                                </h4>
                                {!detail.payments.length ? (
                                    <p className="text-sm text-slate-500">
                                        No payments recorded.
                                    </p>
                                ) : (
                                    <div className="max-h-60 overflow-auto">
                                        <table className="w-full min-w-[450px] text-left text-sm">
                                            <thead>
                                                <tr>
                                                    {[
                                                        'Date',
                                                        'Amount',
                                                        'Method',
                                                        'Reference',
                                                    ].map((text) => (
                                                        <th
                                                            key={text}
                                                            className="p-2"
                                                        >
                                                            {text}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {detail.payments.map((item) => (
                                                    <tr
                                                        key={item.id}
                                                        className="border-t border-slate-100"
                                                    >
                                                        <td className="p-2">
                                                            {item.payment_date}
                                                        </td>
                                                        <td className="p-2">
                                                            {money(
                                                                Number(
                                                                    item.amount_cents,
                                                                ),
                                                            )}
                                                        </td>
                                                        <td className="p-2">
                                                            {item.method.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </td>
                                                        <td className="p-2 break-words">
                                                            {item.reference}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                            {action === 'payment' && (
                                <>
                                    <Alert severity="info">
                                        Record a payment{' '}
                                        {detail.direction === 'receivable'
                                            ? 'already received'
                                            : 'already made'}
                                        . This updates the balance; it does not
                                        transfer money or create an expense
                                        entry.
                                    </Alert>
                                    {errors(payment.errors)}
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <TextField
                                            label="Payment amount (PHP)"
                                            value={payment.data.amount}
                                            slotProps={{
                                                htmlInput: {
                                                    inputMode: 'decimal',
                                                },
                                            }}
                                            onChange={(e) =>
                                                payment.setData(
                                                    'amount',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <TextField
                                            type="date"
                                            label="Payment date"
                                            value={payment.data.payment_date}
                                            slotProps={{
                                                inputLabel: { shrink: true },
                                            }}
                                            onChange={(e) =>
                                                payment.setData(
                                                    'payment_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <TextField
                                            select
                                            label="Payment method"
                                            value={payment.data.method}
                                            onChange={(e) =>
                                                payment.setData(
                                                    'method',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {[
                                                'bank_transfer',
                                                'cash',
                                                'check',
                                                'e_wallet',
                                            ].map((method) => (
                                                <MenuItem
                                                    key={method}
                                                    value={method}
                                                >
                                                    {method.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </MenuItem>
                                            ))}
                                        </TextField>
                                        <TextField
                                            label="Payment reference"
                                            value={payment.data.reference}
                                            onChange={(e) =>
                                                payment.setData(
                                                    'reference',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </>
                            )}
                            {action === 'void' && (
                                <>
                                    {errors(voidForm.errors)}
                                    <Alert severity="info">
                                        Voiding retains this record and removes
                                        it from outstanding totals.
                                    </Alert>
                                    <TextField
                                        label="Reason for voiding"
                                        multiline
                                        minRows={2}
                                        value={voidForm.data.void_reason}
                                        onChange={(e) =>
                                            voidForm.setData(
                                                'void_reason',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </>
                            )}
                        </div>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3, gap: 1, flexWrap: 'wrap' }}>
                    {detail && !action && detail.status !== 'void' && (
                        <>
                            {detail.balance_cents > 0 && (
                                <Button
                                    variant="contained"
                                    onClick={() => {
                                        payment.reset();
                                        payment.setData({
                                            request_key: crypto.randomUUID(),
                                            amount: (
                                                detail.balance_cents / 100
                                            ).toFixed(2),
                                            payment_date: today(),
                                            method: 'bank_transfer',
                                            reference: '',
                                        });
                                        setAction('payment');
                                    }}
                                >
                                    Record payment
                                </Button>
                            )}
                            {detail.paid_cents === 0 && (
                                <Button
                                    color="error"
                                    onClick={() => {
                                        voidForm.reset();
                                        setAction('void');
                                    }}
                                >
                                    Void record
                                </Button>
                            )}
                        </>
                    )}
                    {detail && action === 'payment' && (
                        <Button
                            variant="contained"
                            disabled={busy}
                            onClick={() =>
                                payment.post(
                                    `/accounting/documents/${detail.id}/payments`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setDetail(null);
                                            refresh(
                                                'Payment recorded and balance updated.',
                                            );
                                        },
                                    },
                                )
                            }
                        >
                            Save payment
                        </Button>
                    )}
                    {detail && action === 'void' && (
                        <Button
                            color="error"
                            variant="contained"
                            disabled={busy}
                            onClick={() =>
                                voidForm.patch(
                                    `/accounting/documents/${detail.id}/void`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setDetail(null);
                                            refresh('Record voided.');
                                        },
                                    },
                                )
                            }
                        >
                            Confirm void
                        </Button>
                    )}
                </DialogActions>
            </Dialog>
        </div>
    );
}
