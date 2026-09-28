import { Head, useForm } from '@inertiajs/react';
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
import { X } from 'lucide-react';
import { useState } from 'react';
import EmployeeAvatar from '@/components/employee-avatar';

type Employee = {
    id: number;
    name: string;
    username: string;
    avatar: string;
    role: string;
    status: string;
    team: string | null;
    can_edit: boolean;
    access: boolean;
};
const employeeStatuses = {
    active: 'Active',
    floating: 'Floating',
    resigned: 'Resigned',
    suspended: 'Suspended',
    terminated: 'Terminated',
};
const traineeStatuses = {
    in_training: 'Active Trainee',
    graduated: 'Passed',
    rejected: 'Failed',
};
const label = (employee: Employee) =>
    (employee.role === 'trainee' ? traineeStatuses : employeeStatuses)[
        employee.status as never
    ] ?? employee.status;

export default function AccountStatuses({
    employees,
    statusMessage,
}: {
    employees: Employee[];
    statusMessage?: string;
}) {
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<Employee | null>(null);
    const form = useForm({ status: '', notes: '' });
    const statuses =
        selected?.role === 'trainee' ? traineeStatuses : employeeStatuses;
    return (
        <>
            <Head title="Employee Status" />
            <main className="space-y-6 p-4 sm:p-8">
                <header>
                    <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                        Account management
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">Employee Status</h1>
                    <p className="mt-2 text-slate-500">
                        Update employee and trainee status. Account access
                        follows the selected status.
                    </p>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                <TextField
                    label="Search name or ID"
                    size="small"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="max-h-[65vh] overflow-auto">
                        <table className="w-full min-w-[740px] text-left text-sm">
                            <thead className="sticky top-0 bg-red-50">
                                <tr>
                                    {[
                                        'Employee',
                                        'Role / Team',
                                        'Status',
                                        'Access',
                                        'Actions',
                                    ].map((h) => (
                                        <th key={h} className="p-4">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {employees
                                    .filter((e) =>
                                        `${e.name} ${e.username}`
                                            .toLowerCase()
                                            .includes(search.toLowerCase()),
                                    )
                                    .map((e) => (
                                        <tr
                                            key={e.id}
                                            className="border-t border-slate-100"
                                        >
                                            <td className="p-4">
                                                <div className="flex items-center gap-3">
                                                    <EmployeeAvatar
                                                        name={e.name}
                                                        avatar={e.avatar}
                                                    />
                                                    <div className="font-semibold">
                                                        {e.name}
                                                        <p className="text-xs font-normal text-slate-500">
                                                            {e.username}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-4 capitalize">
                                                {e.role.replaceAll('_', ' ')}
                                                <p className="text-xs text-slate-500">
                                                    {e.team}
                                                </p>
                                            </td>
                                            <td className="p-4">
                                                <Chip
                                                    size="small"
                                                    label={label(e)}
                                                />
                                            </td>
                                            <td className="p-4">
                                                {e.access
                                                    ? 'Allowed'
                                                    : 'Blocked'}
                                            </td>
                                            <td className="p-4">
                                                <Button
                                                    disabled={!e.can_edit}
                                                    onClick={() => {
                                                        form.clearErrors();
                                                        form.setData({
                                                            status: e.status,
                                                            notes: '',
                                                        });
                                                        setSelected(e);
                                                    }}
                                                >
                                                    Change status
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                        {!employees.length && (
                            <p className="p-10 text-center text-slate-500">
                                No employees available within your access.
                            </p>
                        )}
                    </div>
                </section>
            </main>
            <Dialog
                open={!!selected}
                onClose={() => !form.processing && setSelected(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Change account status
                    <IconButton
                        aria-label="Close"
                        disabled={form.processing}
                        onClick={() => setSelected(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    <div className="space-y-5">
                        <p className="font-semibold">
                            {selected?.name} · {selected?.username}
                        </p>
                        <TextField
                            select
                            fullWidth
                            label="Status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                        >
                            {Object.entries(statuses).map(([value, name]) => (
                                <MenuItem key={value} value={value}>
                                    {name}
                                </MenuItem>
                            ))}
                        </TextField>
                        <Alert
                            severity={
                                ['active', 'in_training', 'graduated'].includes(
                                    form.data.status,
                                )
                                    ? 'info'
                                    : 'warning'
                            }
                        >
                            {selected?.role === 'trainee'
                                ? 'Active Trainee and Passed can access the trainee portal. Failed blocks access. Passing preserves this trainee account; an admin creates a separate employee account.'
                                : 'Only Active employees can access their account. Other statuses end access, including existing sessions.'}
                        </Alert>
                        <TextField
                            fullWidth
                            multiline
                            minRows={3}
                            label="Reason / notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        {Object.values(form.errors).map((error, i) => (
                            <Alert key={i} severity="error">
                                {error}
                            </Alert>
                        ))}
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={form.processing}
                        onClick={() =>
                            selected &&
                            form.patch(`/account-statuses/${selected.id}`, {
                                preserveScroll: true,
                                onSuccess: () => setSelected(null),
                            })
                        }
                    >
                        {form.processing ? 'Saving…' : 'Save status'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
AccountStatuses.layout = {
    breadcrumbs: [{ title: 'Employee Status', href: '/account-statuses' }],
};
