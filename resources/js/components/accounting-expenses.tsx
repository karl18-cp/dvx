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
import { Pencil, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { money } from '@/components/training-allowance-details';

type Category = { id: number; name: string };
type Expense = {
    id: number;
    category_id: number;
    expense_date: string;
    description: string;
    payee: string | null;
    amount_cents: number;
    reference: string | null;
    notes: string | null;
    voided_at: string | null;
    void_reason: string | null;
};
type Result = {
    categories: Category[];
    totals: {
        category_id: number;
        total_cents: number;
        expense_count: number;
    }[];
    filtered_total_cents: number;
    expenses: {
        data: Expense[];
        current_page: number;
        last_page: number;
        total: number;
    };
};
const today = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());

export default function AccountingExpenses() {
    const [result, setResult] = useState<Result | null>(null);
    const [filters, setFilters] = useState({
        category_id: '',
        from: '',
        to: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const category = useForm({ name: '' });
    const [expenseOpen, setExpenseOpen] = useState(false);
    const expense = useForm({
        request_key: '',
        category_id: '',
        expense_date: today(),
        description: '',
        payee: '',
        amount: '',
        reference: '',
        notes: '',
    });
    const [selected, setSelected] = useState<Expense | null>(null);
    const [voidOpen, setVoidOpen] = useState(false);
    const voidForm = useForm({ void_reason: '' });
    useEffect(() => {
        const controller = new AbortController();
        fetch(`/accounting/expenses?${new URLSearchParams(applied)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
throw new Error(data.message || 'Unable to load expenses.');
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
    }, [applied, revision]);
    const refresh = (message: string) => {
        setNotice(message);
        setLoading(true);
        setRevision((value) => value + 1);
    };
    const categories = result?.categories || [];
    const categoryName = (id: number) =>
        categories.find((row) => row.id === id)?.name || 'Category';
    const editCategory = (row?: Category) => {
        setCategoryId(row?.id || null);
        category.setData('name', row?.name || '');
        category.clearErrors();
        setCategoryOpen(true);
    };
    const heading = (text: string, close: () => void, busy: boolean) => (
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
                    <h2 className="text-xl font-bold">Expenses</h2>
                    <p className="text-sm text-slate-500">
                        Record business expenses and organize spending by
                        category. Amounts are in PHP.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outlined" onClick={() => editCategory()}>
                        Add category
                    </Button>
                    <Button
                        variant="contained"
                        startIcon={<Plus size={17} />}
                        disabled={!categories.length || loading}
                        onClick={() => {
                            expense.reset();
                            expense.clearErrors();
                            expense.setData({
                                request_key: crypto.randomUUID(),
                                category_id:
                                    applied.category_id ||
                                    String(categories[0].id),
                                expense_date: today(),
                                description: '',
                                payee: '',
                                amount: '',
                                reference: '',
                                notes: '',
                            });
                            setExpenseOpen(true);
                        }}
                    >
                        Add expense
                    </Button>
                </div>
            </div>
            {notice && <Alert severity="success">{notice}</Alert>}
            {error && <Alert severity="error">{error}</Alert>}
            {!loading && !categories.length && (
                <Alert severity="info">
                    Create your first category, such as Rent, Utilities, or
                    Office supplies, to start recording expenses.
                </Alert>
            )}
            <form
                className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setLoading(true);
                    setApplied({ ...filters, page: '1' });
                }}
            >
                <TextField
                    select
                    size="small"
                    label="Expense category"
                    value={filters.category_id}
                    onChange={(e) =>
                        setFilters({
                            ...filters,
                            category_id: String(e.target.value),
                        })
                    }
                >
                    <MenuItem value="">All categories</MenuItem>
                    {categories.map((row) => (
                        <MenuItem key={row.id} value={String(row.id)}>
                            {row.name}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    size="small"
                    type="date"
                    label="From date"
                    value={filters.from}
                    slotProps={{ inputLabel: { shrink: true } }}
                    onChange={(e) =>
                        setFilters({ ...filters, from: e.target.value })
                    }
                />
                <TextField
                    size="small"
                    type="date"
                    label="To date"
                    value={filters.to}
                    slotProps={{ inputLabel: { shrink: true } }}
                    onChange={(e) =>
                        setFilters({ ...filters, to: e.target.value })
                    }
                />
                <Button variant="outlined" type="submit" disabled={loading}>
                    Filter expenses
                </Button>
            </form>
            <div className="rounded-xl border border-red-100 bg-red-50/40 p-4">
                <p className="text-sm text-slate-500">
                    Total expenses · selected category and dates · excludes
                    voided entries
                </p>
                <p className="mt-1 text-2xl font-bold">
                    {loading
                        ? 'Loading…'
                        : money(result?.filtered_total_cents || 0)}
                </p>
            </div>
            <div>
                <h3 className="mb-3 font-semibold">
                    By category{' '}
                    <span className="text-sm font-normal text-slate-500">
                        · all categories within selected dates
                    </span>
                </h3>
                <div className="grid max-h-72 gap-3 overflow-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                    {categories.map((row) => {
                        const total = result?.totals.find(
                            (item) => item.category_id === row.id,
                        );

                        return (
                            <div
                                key={row.id}
                                className="flex min-w-0 items-start justify-between gap-2 rounded-xl border border-slate-200 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold break-words">
                                        {row.name}
                                    </p>
                                    <p className="mt-1 text-lg font-bold">
                                        {loading
                                            ? '…'
                                            : money(
                                                  Number(
                                                      total?.total_cents || 0,
                                                  ),
                                              )}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {total?.expense_count || 0} expenses
                                    </p>
                                </div>
                                <IconButton
                                    size="small"
                                    aria-label={`Rename ${row.name}`}
                                    onClick={() => editCategory(row)}
                                >
                                    <Pencil size={16} />
                                </IconButton>
                            </div>
                        );
                    })}
                </div>
            </div>
            <div
                className="max-h-[55vh] overflow-auto rounded-xl border border-slate-200"
                aria-busy={loading}
            >
                <table className="w-full min-w-[780px] text-left text-sm">
                    <thead className="sticky top-0 bg-red-50">
                        <tr>
                            {[
                                'Date / reference',
                                'Description / payee',
                                'Category',
                                'Amount',
                                'Status',
                                '',
                            ].map((label) => (
                                <th key={label} className="p-3">
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {result?.expenses.data.map((row) => (
                            <tr
                                key={row.id}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">
                                    {row.expense_date}
                                    <p className="text-xs text-slate-500">
                                        {row.reference || `EXP-${row.id}`}
                                    </p>
                                </td>
                                <td className="max-w-xs p-3 break-words">
                                    <p className="font-semibold">
                                        {row.description}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.payee}
                                    </p>
                                </td>
                                <td className="max-w-48 p-3 break-words">
                                    {categoryName(row.category_id)}
                                </td>
                                <td className="p-3 whitespace-nowrap">
                                    {money(row.amount_cents)}
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        label={
                                            row.voided_at ? 'Void' : 'Recorded'
                                        }
                                        color={
                                            row.voided_at
                                                ? 'default'
                                                : 'success'
                                        }
                                    />
                                </td>
                                <td className="p-3">
                                    <Button
                                        size="small"
                                        onClick={() => {
                                            setSelected(row);
                                            setVoidOpen(false);
                                            voidForm.reset();
                                            voidForm.clearErrors();
                                        }}
                                    >
                                        View
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {!loading && !result?.expenses.data.length && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="p-8 text-center text-slate-500"
                                >
                                    No expenses for these filters.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500">
                <span>
                    {result?.expenses.total || 0} records · Page{' '}
                    {result?.expenses.current_page || 1} of{' '}
                    {result?.expenses.last_page || 1}
                </span>
                <div>
                    <Button
                        disabled={
                            loading ||
                            !result ||
                            result.expenses.current_page <= 1
                        }
                        onClick={() => {
                            setLoading(true);
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.expenses.current_page || 1) - 1,
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
                            result.expenses.current_page >=
                                result.expenses.last_page
                        }
                        onClick={() => {
                            setLoading(true);
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.expenses.current_page || 1) + 1,
                                ),
                            });
                        }}
                    >
                        Next
                    </Button>
                </div>
            </div>

            <Dialog
                open={categoryOpen}
                fullWidth
                maxWidth="xs"
                onClose={() => !category.processing && setCategoryOpen(false)}
            >
                {heading(
                    categoryId ? 'Rename category' : 'Add expense category',
                    () => setCategoryOpen(false),
                    category.processing,
                )}
                <DialogContent dividers>
                    <div className="grid gap-4 pt-2">
                        {errors(category.errors)}
                        <TextField
                            autoFocus
                            fullWidth
                            label="Category name"
                            value={category.data.name}
                            onChange={(e) =>
                                category.setData('name', e.target.value)
                            }
                            slotProps={{ htmlInput: { maxLength: 100 } }}
                        />
                        {categoryId && (
                            <p className="text-sm text-slate-500">
                                Existing expenses will use the updated category
                                name.
                            </p>
                        )}
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={category.processing}
                        onClick={() => {
                            const options = {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setCategoryOpen(false);
                                    refresh('Category saved.');
                                },
                            };

                            if (categoryId) {
category.patch(
                                    `/accounting/expense-categories/${categoryId}`,
                                    options,
                                );
} else {
category.post(
                                    '/accounting/expense-categories',
                                    options,
                                );
}
                        }}
                    >
                        Save category
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={expenseOpen}
                fullWidth
                maxWidth="sm"
                onClose={() => !expense.processing && setExpenseOpen(false)}
            >
                {heading(
                    'Add expense',
                    () => setExpenseOpen(false),
                    expense.processing,
                )}
                <DialogContent dividers>
                    <div className="grid gap-5 pt-2">
                        {errors(expense.errors)}
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextField
                                fullWidth
                                select
                                label="Category"
                                value={expense.data.category_id}
                                onChange={(e) =>
                                    expense.setData(
                                        'category_id',
                                        String(e.target.value),
                                    )
                                }
                            >
                                {categories.map((row) => (
                                    <MenuItem
                                        key={row.id}
                                        value={String(row.id)}
                                    >
                                        {row.name}
                                    </MenuItem>
                                ))}
                            </TextField>
                            <TextField
                                fullWidth
                                type="date"
                                label="Expense date"
                                value={expense.data.expense_date}
                                slotProps={{
                                    inputLabel: { shrink: true },
                                    htmlInput: { max: today() },
                                }}
                                onChange={(e) =>
                                    expense.setData(
                                        'expense_date',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <TextField
                            label="Description"
                            required
                            value={expense.data.description}
                            onChange={(e) =>
                                expense.setData('description', e.target.value)
                            }
                        />
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextField
                                label="Amount (PHP)"
                                required
                                slotProps={{
                                    htmlInput: { inputMode: 'decimal' },
                                }}
                                value={expense.data.amount}
                                onChange={(e) =>
                                    expense.setData('amount', e.target.value)
                                }
                            />
                            <TextField
                                label="Payee / supplier"
                                value={expense.data.payee}
                                onChange={(e) =>
                                    expense.setData('payee', e.target.value)
                                }
                            />
                        </div>
                        <TextField
                            label="Receipt / invoice reference"
                            value={expense.data.reference}
                            onChange={(e) =>
                                expense.setData('reference', e.target.value)
                            }
                        />
                        <TextField
                            label="Notes"
                            multiline
                            minRows={2}
                            value={expense.data.notes}
                            onChange={(e) =>
                                expense.setData('notes', e.target.value)
                            }
                        />
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={expense.processing}
                        onClick={() =>
                            expense.post('/accounting/expenses', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setExpenseOpen(false);
                                    refresh('Expense recorded.');
                                },
                            })
                        }
                    >
                        Save expense
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!selected}
                fullWidth
                maxWidth="sm"
                onClose={() => !voidForm.processing && setSelected(null)}
            >
                {heading(
                    `Expense EXP-${selected?.id || ''}`,
                    () => setSelected(null),
                    voidForm.processing,
                )}
                <DialogContent dividers>
                    {selected && (
                        <div className="grid gap-4">
                            <h3 className="text-xl font-bold break-words">
                                {selected.description}
                            </h3>
                            <p>
                                {categoryName(selected.category_id)} ·{' '}
                                {selected.expense_date}
                            </p>
                            <p className="text-2xl font-bold">
                                {money(selected.amount_cents)}
                            </p>
                            <p className="break-words">
                                Payee: {selected.payee || '—'}
                                <br />
                                Reference: {selected.reference || '—'}
                            </p>
                            {selected.notes && (
                                <p className="break-words whitespace-pre-wrap">
                                    {selected.notes}
                                </p>
                            )}
                            {selected.voided_at && (
                                <Alert severity="warning">
                                    Voided: {selected.void_reason}
                                </Alert>
                            )}
                            {voidOpen && (
                                <>
                                    <Alert severity="info">
                                        Voiding excludes this entry from expense
                                        totals and preserves its history. It
                                        does not reverse a payment.
                                    </Alert>
                                    {errors(voidForm.errors)}
                                    <TextField
                                        multiline
                                        minRows={2}
                                        label="Reason for voiding"
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
                <DialogActions sx={{ p: 3 }}>
                    {selected &&
                        !selected.voided_at &&
                        (voidOpen ? (
                            <Button
                                color="error"
                                variant="contained"
                                disabled={voidForm.processing}
                                onClick={() =>
                                    voidForm.patch(
                                        `/accounting/expenses/${selected.id}/void`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setSelected(null);
                                                refresh('Expense voided.');
                                            },
                                        },
                                    )
                                }
                            >
                                Confirm void
                            </Button>
                        ) : (
                            <Button
                                color="error"
                                onClick={() => setVoidOpen(true)}
                            >
                                Void expense
                            </Button>
                        ))}
                </DialogActions>
            </Dialog>
        </div>
    );
}
