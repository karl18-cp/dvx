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
    Stack,
    TextField,
} from '@mui/material';
import { X } from 'lucide-react';
import { useState } from 'react';
import PageLink from '@/components/page-link';
import { CustomAnswers } from '@/components/public-form-fields';
import type { Answer } from '@/components/public-form-fields';

type Inquiry = {
    custom_answers?: Answer[];
    id: number;
    name: string;
    company: string;
    email: string;
    phone: string | null;
    service: string;
    message: string;
    meeting_at: string;
    timezone: string;
    status: string;
    admin_notes: string | null;
    email_status: string;
    created_at: string;
};
const statuses = {
    new: 'New',
    contacted: 'Contacted',
    meeting_scheduled: 'Meeting scheduled',
    closed: 'Closed',
};
const meeting = (row: Inquiry, zone = row.timezone) =>
    new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: zone,
    }).format(new Date(row.meeting_at));

export default function BusinessInquiries({
    inquiries,
    filters,
    statusMessage,
}: {
    inquiries: {
        data: Inquiry[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search?: string; status?: string };
    statusMessage?: string;
}) {
    const [selected, setSelected] = useState<Inquiry | null>(null);
    const [search, setSearch] = useState(filters.search || '');

    return (
        <main className="p-5 text-slate-900 lg:p-8">
            <Head title="Business Inquiries" />
            <div className="mx-auto max-w-7xl space-y-6">
                <header>
                    <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                        Client partnerships
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">
                        Business Inquiries
                    </h1>
                    <p className="mt-2 text-slate-500">
                        Review client inquiries and preferred meeting times.
                        Contact clients to confirm arrangements.
                    </p>
                </header>
                {statusMessage && (
                    <Alert severity="info">{statusMessage}</Alert>
                )}
                <form
                    className="flex flex-wrap items-center gap-4 rounded-2xl border bg-white p-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            '/business-inquiries',
                            { ...filters, search },
                            { preserveState: true },
                        );
                    }}
                >
                    <TextField
                        size="small"
                        label="Search name, company or email"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        sx={{ flex: 1, minWidth: 220 }}
                    />
                    <TextField
                        size="small"
                        select
                        label="Status"
                        value={filters.status || ''}
                        onChange={(e) =>
                            router.get(
                                '/business-inquiries',
                                { search, status: e.target.value },
                                { preserveState: true },
                            )
                        }
                        sx={{ minWidth: 180 }}
                    >
                        <MenuItem value="">All statuses</MenuItem>
                        {Object.entries(statuses).map(([value, label]) => (
                            <MenuItem key={value} value={value}>
                                {label}
                            </MenuItem>
                        ))}
                    </TextField>
                    <Button type="submit" variant="outlined">
                        Search
                    </Button>
                </form>
                <section className="overflow-hidden rounded-2xl border bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[850px] text-left text-sm">
                            <thead className="bg-red-50 text-red-800">
                                <tr>
                                    {[
                                        'Client / Company',
                                        'Service',
                                        'Requested meeting (Philippines)',
                                        'Status',
                                        'Email delivery',
                                        '',
                                    ].map((label) => (
                                        <th key={label} className="p-4">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {inquiries.data.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="p-4">
                                            <b>{row.company}</b>
                                            <p>{row.name}</p>
                                            <p className="text-slate-500">
                                                {row.email}
                                            </p>
                                        </td>
                                        <td className="p-4">{row.service}</td>
                                        <td className="p-4">
                                            {meeting(row, 'Asia/Manila')}
                                            <p className="text-xs text-slate-500">
                                                Asia/Manila
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            {
                                                statuses[
                                                    row.status as keyof typeof statuses
                                                ]
                                            }
                                        </td>
                                        <td className="p-4 capitalize">
                                            {row.email_status.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </td>
                                        <td className="p-4">
                                            <Button
                                                onClick={() => setSelected(row)}
                                            >
                                                Review
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {!inquiries.data.length && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="p-12 text-center text-slate-500"
                                        >
                                            No inquiries found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between p-4">
                        <span>{inquiries.total} inquiries</span>
                        <div className="flex gap-4">
                            {inquiries.prev_page_url && (
                                <PageLink href={inquiries.prev_page_url}>
                                    Previous
                                </PageLink>
                            )}
                            {inquiries.next_page_url && (
                                <PageLink href={inquiries.next_page_url}>
                                    Next
                                </PageLink>
                            )}
                        </div>
                    </div>
                </section>
                {selected && (
                    <Review
                        key={selected.id}
                        inquiry={selected}
                        onClose={() => setSelected(null)}
                    />
                )}
            </div>
        </main>
    );
}
function Review({
    inquiry,
    onClose,
}: {
    inquiry: Inquiry;
    onClose: () => void;
}) {
    const form = useForm({
        status: inquiry.status,
        admin_notes: inquiry.admin_notes || '',
    });
    const retry = useForm({});
    const busy = form.processing || retry.processing;

    return (
        <Dialog
            open
            fullWidth
            maxWidth="sm"
            onClose={() => !busy && onClose()}
            aria-labelledby="review-inquiry-title"
            slotProps={{
                paper: { sx: { m: 2, maxHeight: 'calc(100dvh - 32px)' } },
            }}
        >
            <DialogTitle
                id="review-inquiry-title"
                sx={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                }}
            >
                Business inquiry #{inquiry.id}
                <IconButton
                    aria-label="Close inquiry"
                    disabled={busy}
                    onClick={onClose}
                >
                    <X />
                </IconButton>
            </DialogTitle>
            <form
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    minHeight: 0,
                    overflow: 'hidden',
                }}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(`/business-inquiries/${inquiry.id}`, {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
            >
                <DialogContent dividers sx={{ py: 3 }}>
                    <Stack spacing={3} useFlexGap>
                        <div className="break-words">
                            <h2 className="text-xl font-bold">
                                {inquiry.company}
                            </h2>
                            <p>{inquiry.name}</p>
                            <a
                                className="text-red-700 underline"
                                href={`mailto:${inquiry.email}`}
                            >
                                {inquiry.email}
                            </a>
                            <p>{inquiry.phone || 'No phone provided'}</p>
                            <p className="mt-2">Service: {inquiry.service}</p>
                        </div>
                        <Alert severity="info">
                            Requested: {meeting(inquiry)} ({inquiry.timezone}).
                            <br />
                            Philippines: {meeting(inquiry, 'Asia/Manila')}.
                            <br />
                            Confirm the meeting directly with the client before
                            marking it scheduled.
                        </Alert>
                        <p className="break-words whitespace-pre-wrap">
                            {inquiry.message}
                        </p>
                        <CustomAnswers answers={inquiry.custom_answers} />
                        <TextField
                            size="small"
                            select
                            label="Status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                            error={!!form.errors.status}
                            helperText={form.errors.status}
                        >
                            {Object.entries(statuses).map(([value, label]) => (
                                <MenuItem key={value} value={value}>
                                    {label}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            label="Internal notes"
                            multiline
                            minRows={3}
                            value={form.data.admin_notes}
                            onChange={(e) =>
                                form.setData('admin_notes', e.target.value)
                            }
                            error={!!form.errors.admin_notes}
                            helperText={
                                form.errors.admin_notes ||
                                'Internal only; not sent to the client.'
                            }
                        />
                        {inquiry.email_status !== 'sent' && (
                            <Button
                                disabled={busy}
                                onClick={() =>
                                    retry.post(
                                        `/business-inquiries/${inquiry.id}/email`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: onClose,
                                        },
                                    )
                                }
                            >
                                Retry company email
                            </Button>
                        )}
                    </Stack>
                </DialogContent>
                <DialogActions sx={{ px: 3, py: 2 }}>
                    <Button type="submit" variant="contained" disabled={busy}>
                        Save changes
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
BusinessInquiries.layout = {
    breadcrumbs: [{ title: 'Business Inquiries', href: '/business-inquiries' }],
};
