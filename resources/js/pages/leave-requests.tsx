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
    Tab,
    Tabs,
    TextField,
} from '@mui/material';
import { CalendarDays, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { DateTimeField } from '@/components/date-time-field';

type Leave = {
    id: number;
    employee: string;
    employeeId: string;
    startDate: string;
    endDate: string;
    days: number;
    type: string;
    reason: string;
    status: string;
    leaderStatus: string;
    requiresLeader: boolean;
    leaderName: string | null;
    leaderNotes: string | null;
    adminNotes: string | null;
    paid: boolean;
    canReview: boolean;
};
type Props = {
    isLeader: boolean;
    scope: string;
    filterStatus: string;
    today: string;
    statusMessage?: string;
    requests: {
        data: Leave[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
};
const stage = (leave: Leave) =>
    leave.status === 'approved'
        ? `Approved · ${leave.paid ? 'Paid' : 'Unpaid'}`
        : leave.status === 'rejected'
          ? 'Rejected'
          : leave.requiresLeader && leave.leaderStatus === 'pending'
            ? 'Awaiting team leader'
            : 'Awaiting admin';

export default function LeaveRequests({
    isLeader,
    scope,
    filterStatus,
    today,
    statusMessage,
    requests,
}: Props) {
    const [applying, setApplying] = useState(false);
    const [reviewing, setReviewing] = useState<Leave | null>(null);
    const form = useForm({
        request_id: '',
        start_date: today,
        end_date: today,
        leave_type: 'Vacation',
        reason: '',
    });
    const review = useForm({ decision: 'approved', notes: '' });
    const navigate = (nextScope: string, status = filterStatus) =>
        router.get(
            '/leave-requests',
            { scope: nextScope, status },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Leave Requests" />
            <main className="min-w-0 flex-1 space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] text-red-700 uppercase">
                            Time away
                        </p>
                        <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                            <CalendarDays className="text-red-700" />
                            Leave Requests
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            {isLeader
                                ? 'Apply for your leave and review your agents’ requests before final admin approval.'
                                : 'Submit your leave for team-leader review and final admin approval.'}
                        </p>
                    </div>
                    <Button
                        variant="contained"
                        startIcon={<Plus size={18} />}
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                            form.setData('request_id', crypto.randomUUID());
                            setApplying(true);
                        }}
                    >
                        Apply for leave
                    </Button>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                <section className="overflow-hidden rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-4">
                        {isLeader ? (
                            <Tabs
                                value={scope}
                                onChange={(_, value) => navigate(value)}
                                aria-label="Leave request views"
                            >
                                <Tab value="mine" label="My leave" />
                                <Tab value="team" label="Team leave" />
                            </Tabs>
                        ) : (
                            <h2 className="font-bold">My leave</h2>
                        )}
                        <TextField
                            select
                            size="small"
                            label="Status"
                            value={filterStatus}
                            onChange={(e) => navigate(scope, e.target.value)}
                            sx={{ minWidth: 170 }}
                        >
                            {[
                                ['all', 'All requests'],
                                ['needs_review', 'Pending approval'],
                                ['approved', 'Approved'],
                                ['rejected', 'Rejected'],
                            ].map(([value, label]) => (
                                <MenuItem key={value} value={value}>
                                    {label}
                                </MenuItem>
                            ))}
                        </TextField>
                    </div>
                    <div className="max-h-[65vh] space-y-3 overflow-y-auto p-4 sm:p-5">
                        {requests.data.map((leave) => (
                            <article
                                key={leave.id}
                                className="rounded-xl border border-slate-200 p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-bold">
                                            {scope === 'team'
                                                ? `${leave.employee} · ${leave.employeeId}`
                                                : `${leave.type} leave`}
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {leave.startDate} — {leave.endDate}{' '}
                                            · {leave.days} calendar{' '}
                                            {leave.days === 1 ? 'day' : 'days'}
                                            {scope === 'team' &&
                                                ` · ${leave.type}`}
                                        </p>
                                    </div>
                                    <span
                                        className={`rounded-full px-3 py-1 text-xs font-semibold ${leave.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : leave.status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'}`}
                                    >
                                        {stage(leave)}
                                    </span>
                                </div>
                                <p className="mt-3 text-sm break-words whitespace-pre-wrap text-slate-700">
                                    {leave.reason}
                                </p>
                                {leave.leaderName && (
                                    <p className="mt-3 text-xs text-slate-500">
                                        Team-leader review: {leave.leaderStatus}{' '}
                                        by {leave.leaderName}
                                        {leave.leaderNotes &&
                                            ` — ${leave.leaderNotes}`}
                                    </p>
                                )}
                                {leave.adminNotes && (
                                    <p className="mt-2 text-xs text-slate-500">
                                        Admin notes: {leave.adminNotes}
                                    </p>
                                )}
                                {leave.canReview && (
                                    <Button
                                        size="small"
                                        variant="outlined"
                                        sx={{ mt: 2 }}
                                        onClick={() => {
                                            review.reset();
                                            review.clearErrors();
                                            setReviewing(leave);
                                        }}
                                    >
                                        Review request
                                    </Button>
                                )}
                            </article>
                        ))}
                        {!requests.data.length && (
                            <p className="py-12 text-center text-slate-500">
                                No leave requests to show.
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500">
                        <span>
                            {requests.total} requests · Page{' '}
                            {requests.current_page} of {requests.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                disabled={!requests.prev_page_url}
                                onClick={() =>
                                    requests.prev_page_url &&
                                    router.get(requests.prev_page_url)
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                disabled={!requests.next_page_url}
                                onClick={() =>
                                    requests.next_page_url &&
                                    router.get(requests.next_page_url)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </section>
            </main>
            <Dialog
                open={applying}
                onClose={() => !form.processing && setApplying(false)}
                fullWidth
                maxWidth="sm"
                aria-labelledby="apply-leave-title"
                slotProps={{
                    paper: {
                        sx: {
                            m: { xs: 2, sm: 4 },
                            width: { xs: 'calc(100% - 32px)', sm: '100%' },
                            maxHeight: 'calc(100dvh - 32px)',
                        },
                    },
                }}
            >
                <DialogTitle
                    id="apply-leave-title"
                    className="flex items-center justify-between"
                >
                    Apply for leave
                    <IconButton
                        aria-label="Close leave application"
                        disabled={form.processing}
                        onClick={() => setApplying(false)}
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
                        form.post('/leave-requests', {
                            onSuccess: () => setApplying(false),
                        });
                    }}
                >
                    <DialogContent
                        dividers
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 3,
                            px: { xs: 2, sm: 3 },
                            py: 3,
                            '& > *': { flexShrink: 0 },
                        }}
                    >
                        <Alert severity="info">
                            {isLeader
                                ? 'Your request goes directly to an admin.'
                                : 'Your team leader reviews first, then an admin gives final approval.'}{' '}
                            Paid status is determined by the admin.
                        </Alert>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <DateTimeField
                                label="Start date"
                                value={form.data.start_date}
                                onChange={(e) =>
                                    form.setData('start_date', e.target.value)
                                }
                                error={!!form.errors.start_date}
                                helperText={form.errors.start_date}
                                required
                                fullWidth
                            />
                            <DateTimeField
                                label="End date"
                                value={form.data.end_date}
                                onChange={(e) =>
                                    form.setData('end_date', e.target.value)
                                }
                                error={!!form.errors.end_date}
                                helperText={form.errors.end_date}
                                required
                                fullWidth
                            />
                        </div>
                        <TextField
                            select
                            label="Leave type"
                            value={form.data.leave_type}
                            onChange={(e) =>
                                form.setData('leave_type', e.target.value)
                            }
                            error={!!form.errors.leave_type}
                            helperText={form.errors.leave_type}
                            fullWidth
                        >
                            {['Vacation', 'Sick', 'Emergency', 'Other'].map(
                                (type) => (
                                    <MenuItem key={type} value={type}>
                                        {type}
                                    </MenuItem>
                                ),
                            )}
                        </TextField>
                        <TextField
                            label="Reason"
                            multiline
                            minRows={3}
                            fullWidth
                            required
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            error={!!form.errors.reason}
                            helperText={form.errors.reason}
                            slotProps={{ htmlInput: { maxLength: 2000 } }}
                        />
                        {form.errors.request_id && (
                            <Alert severity="error">
                                {form.errors.request_id}
                            </Alert>
                        )}
                    </DialogContent>
                    <DialogActions
                        sx={{ px: { xs: 2, sm: 3 }, py: 2, flexShrink: 0 }}
                    >
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={form.processing}
                        >
                            Submit leave request
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
            <Dialog
                open={!!reviewing}
                onClose={() => !review.processing && setReviewing(null)}
                fullWidth
                maxWidth="sm"
                aria-labelledby="review-leave-title"
            >
                <DialogTitle
                    id="review-leave-title"
                    className="flex items-center justify-between"
                >
                    Initial leave review
                    <IconButton
                        aria-label="Close leave review"
                        disabled={review.processing}
                        onClick={() => setReviewing(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        if (reviewing) {
                            review.patch(
                                `/leave-requests/${reviewing.id}/initial-review`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setReviewing(null),
                                },
                            );
                        }
                    }}
                >
                    <DialogContent className="space-y-5">
                        <p className="text-sm">
                            {reviewing?.employee} · {reviewing?.startDate} —{' '}
                            {reviewing?.endDate}
                        </p>
                        <Alert severity="info">
                            Initial approval forwards this leave to an admin. It
                            does not yet credit attendance or paid hours.
                        </Alert>
                        <TextField
                            select
                            label="Decision"
                            fullWidth
                            value={review.data.decision}
                            onChange={(e) =>
                                review.setData('decision', e.target.value)
                            }
                            error={!!review.errors.decision}
                            helperText={review.errors.decision}
                        >
                            <MenuItem value="approved">
                                Approve and forward to admin
                            </MenuItem>
                            <MenuItem value="rejected">Reject request</MenuItem>
                        </TextField>
                        <TextField
                            label="Review notes"
                            multiline
                            minRows={3}
                            fullWidth
                            required={review.data.decision === 'rejected'}
                            value={review.data.notes}
                            onChange={(e) =>
                                review.setData('notes', e.target.value)
                            }
                            error={!!review.errors.notes}
                            helperText={
                                review.errors.notes ||
                                'Required when rejecting a request.'
                            }
                            slotProps={{ htmlInput: { maxLength: 2000 } }}
                        />
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 3 }}>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={review.processing}
                        >
                            Save review
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
        </>
    );
}

LeaveRequests.layout = {
    breadcrumbs: [{ title: 'Leave Requests', href: '/leave-requests' }],
};
