import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Alert,
    Autocomplete,
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
import { ClipboardCheck, Plus, RefreshCw, X } from 'lucide-react';
import { useState } from 'react';
import { formatDate, Pagination } from './forms/shared';
import type { Page } from './forms/shared';

const categories = [
    'quality',
    'productivity',
    'attendance',
    'communication',
    'professionalism',
] as const;
type Category = (typeof categories)[number];
type Employee = {
    id: number;
    name: string;
    username: string | null;
    role: string;
};
type Rating = Record<Category, number> & {
    id: number;
    employee_name: string;
    employee_username: string | null;
    employee_role: string;
    reviewer_name: string;
    reviewer_username: string | null;
    teams: { name: string; campaign: string | null }[];
    average: string;
    comments: string | null;
    created_at: string;
};
type Props = {
    employees: Employee[];
    roles: string[];
    records: Page<Rating>;
    filters: { search: string; role: string };
    statusMessage: string | null;
};
const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const scoreColor = (average: number) =>
    average >= 4 ? 'success' : average >= 3 ? 'warning' : 'error';

export default function SatisfactionResults({
    employees,
    roles,
    records,
    filters,
    statusMessage,
}: Props) {
    const { errors } = usePage().props;
    const [open, setOpen] = useState(false);
    const [detail, setDetail] = useState<Rating | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role);
    const [refreshing, setRefreshing] = useState(false);
    const rating = useForm<
        Record<Category, string> & {
            employee_id: string;
            request_id: string;
            comments: string;
        }
    >({
        employee_id: '',
        request_id: '',
        quality: '',
        productivity: '',
        attendance: '',
        communication: '',
        professionalism: '',
        comments: '',
    });
    const employee =
        employees.find((item) => String(item.id) === rating.data.employee_id) ??
        null;
    const complete = categories.every(
        (category) => rating.data[category] !== '',
    );
    const average = complete
        ? (
              categories.reduce(
                  (sum, category) => sum + Number(rating.data[category]),
                  0,
              ) / categories.length
          ).toFixed(2)
        : null;
    const start = () => {
        rating.reset();
        rating.clearErrors();
        rating.setData('request_id', crypto.randomUUID());
        setOpen(true);
    };
    const submit = () =>
        rating.post('/satisfaction-results', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setSearch('');
                setRole('');
            },
        });

    return (
        <main className="min-h-full p-4 text-[#17202d] lg:p-6">
            <Head title="Satisfaction Results" />
            <div className="mx-auto max-w-[1600px] space-y-5 rounded-3xl border-t-4 border-[#96272b] bg-white p-5 shadow-sm lg:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-3 text-2xl font-extrabold">
                            <ClipboardCheck className="text-[#96272b]" />
                            Satisfaction Survey Results
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Rate employees across five categories and review
                            submitted results.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outlined"
                            startIcon={<RefreshCw size={16} />}
                            disabled={refreshing}
                            onClick={() =>
                                router.reload({
                                    onStart: () => setRefreshing(true),
                                    onFinish: () => setRefreshing(false),
                                })
                            }
                        >
                            Refresh
                        </Button>
                        <Button
                            variant="contained"
                            startIcon={<Plus size={16} />}
                            onClick={start}
                        >
                            Rate Employee
                        </Button>
                    </div>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                {!open && Object.keys(errors).length > 0 && (
                    <Alert severity="error">
                        {Object.values(errors).join(' ')}
                    </Alert>
                )}
                <section className="rounded-2xl border border-[#f0c9c5] bg-[#fffafa] p-4 lg:p-5">
                    <form
                        className="mb-5 flex flex-wrap items-end gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                '/satisfaction-results',
                                { search, role },
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <TextField
                            size="small"
                            label="Search employee, ID, or reviewer"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            slotProps={{ htmlInput: { maxLength: 100 } }}
                            sx={{ width: { xs: '100%', sm: 300 } }}
                        />
                        <TextField
                            select
                            size="small"
                            label="Employee role"
                            value={role}
                            onChange={(e) => setRole(e.target.value)}
                            sx={{ width: { xs: '100%', sm: 210 } }}
                        >
                            <MenuItem value="">All roles</MenuItem>
                            {roles.map((value) => (
                                <MenuItem key={value} value={value}>
                                    {label(value)}
                                </MenuItem>
                            ))}
                        </TextField>
                        <Button type="submit" variant="outlined">
                            Search
                        </Button>
                        <Button
                            onClick={() => {
                                setSearch('');
                                setRole('');
                                router.get(
                                    '/satisfaction-results',
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            Clear
                        </Button>
                        <p className="ml-auto text-xs text-slate-500">
                            Scores: 1–5 · Dates: Asia/Manila
                        </p>
                    </form>
                    <div
                        className="max-h-[540px] [scrollbar-gutter:stable] overflow-auto overscroll-contain rounded-2xl border border-slate-200 bg-white"
                        role="region"
                        aria-label="Satisfaction ratings"
                        tabIndex={0}
                    >
                        <table className="w-full min-w-[1250px] text-left text-xs">
                            <thead className="sticky top-0 z-10 bg-[#f7f8fa]">
                                <tr>
                                    {[
                                        'Employee',
                                        'Rated by',
                                        'Campaign / Team',
                                        'Average',
                                        ...categories.map(label),
                                        'Submitted',
                                        'Comments',
                                    ].map((text) => (
                                        <th
                                            key={text}
                                            className="p-4 whitespace-nowrap"
                                        >
                                            {text}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {records.data.map((record) => (
                                    <tr
                                        key={record.id}
                                        className="border-t border-slate-200"
                                    >
                                        <td className="min-w-40 p-4">
                                            <strong>
                                                {record.employee_name}
                                            </strong>
                                            <p className="mt-1 text-slate-500">
                                                {record.employee_username}
                                            </p>
                                            <p className="mt-1 text-slate-500">
                                                {label(record.employee_role)}
                                            </p>
                                        </td>
                                        <td className="min-w-40 p-4">
                                            <strong>
                                                {record.reviewer_name}
                                            </strong>
                                            <p className="mt-1 text-slate-500">
                                                {record.reviewer_username}
                                            </p>
                                        </td>
                                        <td className="min-w-44 p-4">
                                            {record.teams.length ? (
                                                record.teams.map(
                                                    (team, index) => (
                                                        <p
                                                            key={index}
                                                            className="mb-1"
                                                        >
                                                            {team.campaign
                                                                ? `${team.campaign} / `
                                                                : ''}
                                                            {team.name}
                                                        </p>
                                                    ),
                                                )
                                            ) : (
                                                <span className="text-slate-400">
                                                    Not assigned
                                                </span>
                                            )}
                                        </td>
                                        <td className="p-4">
                                            <Chip
                                                size="small"
                                                color={scoreColor(
                                                    Number(record.average),
                                                )}
                                                label={Number(
                                                    record.average,
                                                ).toFixed(2)}
                                            />
                                        </td>
                                        {categories.map((category) => (
                                            <td key={category} className="p-4">
                                                {record[category]}
                                            </td>
                                        ))}
                                        <td className="min-w-40 p-4">
                                            {formatDate(record.created_at)}
                                        </td>
                                        <td className="min-w-40 p-4">
                                            <p className="line-clamp-2 max-w-52 break-words text-slate-500">
                                                {record.comments || 'None'}
                                            </p>
                                            <button
                                                className="mt-2 font-semibold text-red-700 hover:underline"
                                                onClick={() =>
                                                    setDetail(record)
                                                }
                                                aria-label={`View rating ${record.id} for ${record.employee_name}`}
                                            >
                                                View details
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {!records.data.length && (
                                    <tr>
                                        <td
                                            colSpan={11}
                                            className="p-10 text-center text-slate-500"
                                        >
                                            {filters.search || filters.role
                                                ? 'No ratings match these filters.'
                                                : 'No ratings submitted yet. Select Rate Employee to add the first one.'}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination page={records} />
                </section>
            </div>
            <Dialog
                open={open}
                onClose={() => {
                    if (!rating.processing) {
                        setOpen(false);
                    }
                }}
                maxWidth="sm"
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
                    <h2 className="text-xl font-bold">Rate Employee</h2>
                    <IconButton
                        aria-label="Close rating"
                        disabled={rating.processing}
                        onClick={() => setOpen(false)}
                        sx={{ color: 'white' }}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    <div className="space-y-5">
                        <p className="text-sm text-slate-500">
                            Select any employee, including agents and team
                            leaders. Rate each category from 1 (lowest) to 5
                            (highest).
                        </p>
                        {Object.keys(rating.errors).length > 0 && (
                            <Alert severity="error">
                                <ul>
                                    {Object.entries(rating.errors).map(
                                        ([key, value]) => (
                                            <li key={key}>{value}</li>
                                        ),
                                    )}
                                </ul>
                            </Alert>
                        )}
                        <Autocomplete
                            options={employees}
                            value={employee}
                            disabled={rating.processing}
                            isOptionEqualToValue={(a, b) => a.id === b.id}
                            getOptionLabel={(item) =>
                                `${item.name} · ${item.username ?? ''} · ${label(item.role)}`
                            }
                            onChange={(_, value) =>
                                rating.setData(
                                    'employee_id',
                                    value ? String(value.id) : '',
                                )
                            }
                            renderInput={(params) => (
                                <TextField
                                    {...params}
                                    label="Employee"
                                    required
                                    error={!!rating.errors.employee_id}
                                />
                            )}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            {categories.map((category) => (
                                <TextField
                                    key={category}
                                    select
                                    required
                                    fullWidth
                                    size="small"
                                    label={label(category)}
                                    value={rating.data[category]}
                                    disabled={rating.processing}
                                    error={!!rating.errors[category]}
                                    onChange={(e) =>
                                        rating.setData(category, e.target.value)
                                    }
                                >
                                    <MenuItem value="">Select score</MenuItem>
                                    {[1, 2, 3, 4, 5].map((score) => (
                                        <MenuItem
                                            key={score}
                                            value={String(score)}
                                        >
                                            {score}
                                            {score === 1
                                                ? ' — Lowest'
                                                : score === 5
                                                  ? ' — Highest'
                                                  : ''}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            ))}
                        </div>
                        <div className="flex items-center justify-between rounded-xl border border-red-100 bg-red-50 p-4">
                            <div>
                                <p className="text-sm font-bold">
                                    Average score
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    Calculated from all five categories
                                </p>
                            </div>
                            <strong className="text-2xl text-red-800">
                                {average ? `${average} / 5` : '—'}
                            </strong>
                        </div>
                        <TextField
                            label="Comments"
                            placeholder="Optional feedback"
                            multiline
                            minRows={3}
                            fullWidth
                            value={rating.data.comments}
                            disabled={rating.processing}
                            onChange={(e) =>
                                rating.setData('comments', e.target.value)
                            }
                            slotProps={{ htmlInput: { maxLength: 5000 } }}
                        />
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 2 }}>
                    <Button
                        disabled={rating.processing}
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={rating.processing}
                        onClick={submit}
                    >
                        {rating.processing ? 'Submitting…' : 'Submit Rating'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!detail}
                onClose={() => setDetail(null)}
                maxWidth="sm"
                fullWidth
            >
                <DialogTitle>Rating details</DialogTitle>
                <DialogContent dividers>
                    {detail && (
                        <div className="space-y-4">
                            <div>
                                <h3 className="text-lg font-bold">
                                    {detail.employee_name}
                                </h3>
                                <p className="text-sm text-slate-500">
                                    {detail.employee_username} ·{' '}
                                    {label(detail.employee_role)}
                                </p>
                                <p className="mt-2 text-sm text-slate-500">
                                    Rated by {detail.reviewer_name} ·{' '}
                                    {formatDate(detail.created_at)}
                                </p>
                            </div>
                            <dl className="divide-y divide-slate-100">
                                {categories.map((category) => (
                                    <div
                                        className="flex justify-between py-3"
                                        key={category}
                                    >
                                        <dt>{label(category)}</dt>
                                        <dd className="font-bold">
                                            {detail[category]} / 5
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex justify-between py-3 font-bold text-red-800">
                                    <dt>Average</dt>
                                    <dd>
                                        {Number(detail.average).toFixed(2)} / 5
                                    </dd>
                                </div>
                            </dl>
                            <div>
                                <h4 className="mb-2 font-bold">Comments</h4>
                                <p className="text-sm break-words whitespace-pre-wrap">
                                    {detail.comments || 'None'}
                                </p>
                            </div>
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

SatisfactionResults.layout = {
    breadcrumbs: [
        { title: 'Satisfaction Results', href: '/satisfaction-results' },
    ],
};
