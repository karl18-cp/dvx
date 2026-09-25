import { Head, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import PageLink from '@/components/page-link';

type RequestRow = {
    id: number;
    request_date: string;
    request_time: string;
    reason: string;
    status: string;
    review_notes: string | null;
};
export default function MyRequests({
    type,
    today,
    statusMessage,
    requests,
}: {
    type: 'overtime' | 'undertime';
    today: string;
    statusMessage?: string;
    requests: {
        data: RequestRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        type,
        request_date: today,
        request_time: '',
        reason: '',
    });
    const close = () => {
        if (!form.processing) {
            setOpen(false);
        }
    };

    return (
        <main className="space-y-6 p-4 sm:p-6 lg:p-8">
            <Head title="My Requests" />
            <header className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                        Your workday
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">My Requests</h1>
                    <p className="mt-2 text-slate-500">
                        Request overtime or undertime and track your approval
                        status.
                    </p>
                </div>
                <Button
                    variant="contained"
                    startIcon={<Plus size={18} />}
                    onClick={() => {
                        form.reset();
                        form.clearErrors();
                        form.setData('type', type);
                        setOpen(true);
                    }}
                >
                    New request
                </Button>
            </header>
            {statusMessage && <Alert severity="success">{statusMessage}</Alert>}
            <div className="flex flex-wrap items-center gap-3">
                {(['overtime', 'undertime'] as const).map((value) => (
                    <Button
                        key={value}
                        aria-pressed={type === value}
                        variant={type === value ? 'contained' : 'outlined'}
                        onClick={() =>
                            router.get('/my-requests', { type: value })
                        }
                    >
                        {value === 'overtime' ? 'Overtime' : 'Undertime'}
                    </Button>
                ))}
                <PageLink
                    href="/leave-requests"
                    className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-800"
                >
                    Leave requests
                </PageLink>
            </div>
            <section className="overflow-hidden rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[680px] text-left text-sm">
                        <thead className="bg-red-50">
                            <tr>
                                {[
                                    'Shift date',
                                    'Requested clock-out',
                                    'Reason',
                                    'Status',
                                    'Admin notes',
                                ].map((label) => (
                                    <th key={label} className="px-5 py-4">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-slate-100"
                                >
                                    <td className="px-5 py-4">
                                        {row.request_date.slice(0, 10)}
                                    </td>
                                    <td className="px-5 py-4">
                                        {row.request_time.slice(0, 5)}
                                    </td>
                                    <td className="max-w-md px-5 py-4 break-words whitespace-pre-wrap">
                                        {row.reason}
                                    </td>
                                    <td className="px-5 py-4 capitalize">
                                        {row.status.replaceAll('_', ' ')}
                                    </td>
                                    <td className="px-5 py-4 whitespace-pre-wrap">
                                        {row.review_notes || '—'}
                                    </td>
                                </tr>
                            ))}
                            {!requests.data.length && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-10 text-center text-slate-500"
                                    >
                                        No {type} requests yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex justify-end gap-4 border-t border-slate-100 p-4">
                    {requests.prev_page_url && (
                        <PageLink href={requests.prev_page_url}>
                            Previous
                        </PageLink>
                    )}
                    {requests.next_page_url && (
                        <PageLink href={requests.next_page_url}>Next</PageLink>
                    )}
                </div>
            </section>
            <Dialog
                open={open}
                onClose={close}
                fullWidth
                maxWidth="sm"
                aria-labelledby="time-request-title"
            >
                <DialogTitle
                    id="time-request-title"
                    className="flex items-center justify-between"
                >
                    New time request
                    <IconButton
                        aria-label="Close request"
                        disabled={form.processing}
                        onClick={close}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/my-requests', {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <DialogContent dividers>
                        <div className="space-y-5">
                            <TextField
                                select
                                fullWidth
                                label="Request type"
                                value={form.data.type}
                                onChange={(event) =>
                                    form.setData(
                                        'type',
                                        event.target.value as
                                            'overtime' | 'undertime',
                                    )
                                }
                            >
                                <MenuItem value="overtime">Overtime</MenuItem>
                                <MenuItem value="undertime">Undertime</MenuItem>
                            </TextField>
                            <TextField
                                required
                                fullWidth
                                type="date"
                                label="Shift date"
                                slotProps={{ inputLabel: { shrink: true } }}
                                value={form.data.request_date}
                                onChange={(event) =>
                                    form.setData(
                                        'request_date',
                                        event.target.value,
                                    )
                                }
                                error={!!form.errors.request_date}
                                helperText={
                                    form.errors.request_date ||
                                    'For overnight shifts, choose the date your shift starts.'
                                }
                            />
                            <TextField
                                required
                                fullWidth
                                type="time"
                                label="Requested clock-out"
                                slotProps={{ inputLabel: { shrink: true } }}
                                value={form.data.request_time}
                                onChange={(event) =>
                                    form.setData(
                                        'request_time',
                                        event.target.value,
                                    )
                                }
                                error={!!form.errors.request_time}
                                helperText={
                                    form.errors.request_time ||
                                    'Asia/Manila time. Your assigned campaign schedule applies.'
                                }
                            />
                            <TextField
                                required
                                fullWidth
                                multiline
                                minRows={3}
                                label="Reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                error={!!form.errors.reason}
                                helperText={form.errors.reason}
                                slotProps={{ htmlInput: { maxLength: 2000 } }}
                            />
                        </div>
                    </DialogContent>
                    <DialogActions sx={{ p: 3 }}>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Submitting…' : 'Submit request'}
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
        </main>
    );
}
MyRequests.layout = {
    breadcrumbs: [{ title: 'My Requests', href: '/my-requests' }],
};
