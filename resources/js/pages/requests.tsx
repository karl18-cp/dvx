import { Head, router } from '@inertiajs/react';
import {
    Box,
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
    Tooltip,
} from '@mui/material';
import { Check, Inbox, RefreshCw, X } from 'lucide-react';
import { useMemo, useState } from 'react';

type BaseRequest = {
    id: number;
    employeeId: string;
    requester: string;
    role: string;
    team: string | null;
    status: string;
    submittedAt: string;
    reason: string;
};

type TimeRequest = BaseRequest & {
    requestDate: string;
    requestTime: string;
};

type LeaveRequest = BaseRequest & {
    initialApproval: string;
    leaderName: string | null;
    leaderNotes: string | null;
    startDate: string;
    endDate: string;
    numberOfDays: number;
    leaveType: string;
    isPaid: boolean;
};

type RequestsProps = {
    undertimeRequests: TimeRequest[];
    overtimeRequests: TimeRequest[];
    leaveRequests: LeaveRequest[];
};

const requestTypes = ['undertime', 'overtime', 'leave'] as const;
type RequestType = (typeof requestTypes)[number];

const dateText = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));

const dateTimeText = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));

const statusStyle: Record<string, { label: string; classes: string }> = {
    needs_review: {
        label: 'Needs Review',
        classes: 'bg-cyan-100 text-cyan-800',
    },
    approved: { label: 'Approved', classes: 'bg-emerald-100 text-emerald-800' },
    rejected: { label: 'Rejected', classes: 'bg-red-100 text-red-800' },
};

export default function Requests({
    undertimeRequests,
    overtimeRequests,
    leaveRequests,
}: RequestsProps) {
    const [tab, setTab] = useState(0);
    const [status, setStatus] = useState('needs_review');
    const [requestedPage, setPage] = useState(1);
    const [processingKey, setProcessingKey] = useState<string | null>(null);
    const [review, setReview] = useState<{
        type: RequestType;
        item: BaseRequest;
        decision: 'approved' | 'rejected';
    } | null>(null);
    const [isPaid, setIsPaid] = useState(false);
    const [reviewError, setReviewError] = useState('');
    const perPage = 20;
    const activeType = requestTypes[tab];
    const requestGroups = useMemo(
        () => ({
            undertime: undertimeRequests,
            overtime: overtimeRequests,
            leave: leaveRequests,
        }),
        [leaveRequests, overtimeRequests, undertimeRequests],
    );
    const filtered = requestGroups[activeType].filter(
        (item) => status === 'all' || item.status === status,
    );
    const pages = Math.ceil(filtered.length / perPage);
    const page = Math.max(1, Math.min(requestedPage, pages));
    const visible = filtered.slice((page - 1) * perPage, page * perPage);

    const decide = (
        type: RequestType,
        item: BaseRequest,
        decision: 'approved' | 'rejected',
    ) => {
        setReview({ type, item, decision });
        setIsPaid(type === 'leave' && (item as LeaveRequest).isPaid);
        setReviewError('');
    };

    const submitReview = () => {
        if (!review) {
            return;
        }

        const { type, item, decision } = review;
        const key = `${type}-${item.id}-${decision}`;
        setProcessingKey(key);
        router.patch(
            `/requests/${type}/${item.id}/status`,
            {
                status: decision,
                ...(type === 'leave' ? { is_paid: isPaid } : {}),
            },
            {
                preserveScroll: true,
                onSuccess: () => setReview(null),
                onError: (errors) =>
                    setReviewError(
                        Object.values(errors)[0] ?? 'Unable to update request.',
                    ),
                onFinish: () => setProcessingKey(null),
            },
        );
    };

    const countFor = (type: RequestType) =>
        requestGroups[type].filter(
            (item) => status === 'all' || item.status === status,
        ).length;

    return (
        <>
            <Head title="Requests" />
            <main className="min-h-full bg-[#f7f7fa] p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1600px] space-y-6">
                    <header>
                        <p className="mb-2 text-xs font-bold tracking-[0.2em] text-[#b72822] uppercase">
                            Workforce Management
                        </p>
                        <h1 className="text-3xl font-bold tracking-[-0.03em] text-[#1b1d2a] sm:text-4xl">
                            Employee Requests
                        </h1>
                        <p className="mt-2 text-sm text-[#777b8e] sm:text-base">
                            Review undertime, overtime, and leave submissions.
                        </p>
                    </header>

                    <section className="overflow-hidden rounded-3xl border border-t-4 border-[#e6e7ec] border-t-[#ad2924] bg-white shadow-[0_16px_50px_rgba(25,27,38,0.06)]">
                        <div className="flex flex-col justify-between gap-4 border-b border-[#ededf1] p-5 sm:flex-row sm:items-center sm:p-6">
                            <div className="flex items-center gap-3">
                                <div className="grid size-11 place-items-center rounded-xl bg-[#fff0ee] text-[#bd2923]">
                                    <Inbox size={21} />
                                </div>
                                <div>
                                    <h2 className="font-bold text-[#202230]">
                                        Requests for Final Approval
                                    </h2>
                                    <p className="text-sm text-[#888b9b]">
                                        All records are linked to employee
                                        accounts.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <TextField
                                    select
                                    size="small"
                                    value={status}
                                    onChange={(event) => {
                                        setStatus(event.target.value);
                                        setPage(1);
                                    }}
                                    sx={{
                                        minWidth: 180,
                                        '& .MuiOutlinedInput-root': {
                                            height: 44,
                                            borderRadius: 3,
                                        },
                                    }}
                                >
                                    <MenuItem value="needs_review">
                                        Needs Review
                                    </MenuItem>
                                    <MenuItem value="approved">
                                        Approved
                                    </MenuItem>
                                    <MenuItem value="rejected">
                                        Rejected
                                    </MenuItem>
                                    <MenuItem value="all">
                                        All Statuses
                                    </MenuItem>
                                </TextField>
                                <Tooltip title="Refresh requests">
                                    <IconButton
                                        onClick={() => router.reload()}
                                        sx={{
                                            width: 44,
                                            height: 44,
                                            border: '1px solid #e1e2e8',
                                        }}
                                    >
                                        <RefreshCw size={18} />
                                    </IconButton>
                                </Tooltip>
                            </div>
                        </div>

                        <Tabs
                            value={tab}
                            onChange={(_, value: number) => {
                                setTab(value);
                                setPage(1);
                            }}
                            sx={{
                                px: 2.5,
                                borderBottom: '1px solid',
                                borderColor: 'divider',
                            }}
                        >
                            {requestTypes.map((type) => (
                                <Tab
                                    key={type}
                                    label={
                                        <span className="inline-flex items-center gap-2.5">
                                            <span className="capitalize">
                                                {type}
                                            </span>
                                            <span className="inline-grid min-w-6 place-items-center rounded-full bg-amber-500 px-1.5 py-0.5 text-[11px] leading-5 font-bold text-[#3b2400]">
                                                {countFor(type)}
                                            </span>
                                        </span>
                                    }
                                />
                            ))}
                        </Tabs>

                        <div className="overflow-x-auto p-4 sm:p-6">
                            <table className="w-full min-w-[1180px] border-collapse overflow-hidden rounded-2xl border border-[#e5e7ed] text-left">
                                <thead>
                                    {activeType === 'leave' ? (
                                        <tr className="bg-[#fafafd] text-[11px] font-bold tracking-[0.06em] text-[#555869] uppercase">
                                            {[
                                                'Requester',
                                                'Role',
                                                'Employee ID',
                                                'Start Date',
                                                'End Date',
                                                'Days',
                                                'Type',
                                                'Reason',
                                                'Team',
                                                'Submitted',
                                                'Status',
                                                'Actions',
                                            ].map((heading) => (
                                                <th
                                                    key={heading}
                                                    className="px-3 py-4"
                                                >
                                                    {heading}
                                                </th>
                                            ))}
                                        </tr>
                                    ) : (
                                        <tr className="bg-[#fafafd] text-[11px] font-bold tracking-[0.06em] text-[#555869] uppercase">
                                            {[
                                                'Requester',
                                                'Role',
                                                'Employee ID',
                                                'Date',
                                                'Time',
                                                'Reason',
                                                'Team',
                                                'Submitted',
                                                'Status',
                                                'Actions',
                                            ].map((heading) => (
                                                <th
                                                    key={heading}
                                                    className="px-4 py-4"
                                                >
                                                    {heading}
                                                </th>
                                            ))}
                                        </tr>
                                    )}
                                </thead>
                                <tbody className="divide-y divide-[#e9eaf0]">
                                    {visible.map((item) => {
                                        const presentation =
                                            statusStyle[item.status] ??
                                            statusStyle.needs_review;
                                        const leave = item as LeaveRequest;
                                        const initialBlocked =
                                            activeType === 'leave' &&
                                            ['pending', 'rejected'].includes(
                                                leave.initialApproval,
                                            );
                                        const timed = item as TimeRequest;

                                        return (
                                            <tr
                                                key={item.id}
                                                className="align-top transition hover:bg-[#fcfaf9]"
                                            >
                                                <td className="px-4 py-4 font-semibold text-[#202230]">
                                                    {item.requester}
                                                </td>
                                                <td className="px-4 py-4 text-sm text-[#4f5262]">
                                                    {item.role}
                                                </td>
                                                <td className="px-4 py-4 font-mono text-xs font-bold text-[#c12a26]">
                                                    {item.employeeId}
                                                </td>
                                                {activeType === 'leave' ? (
                                                    <>
                                                        <td className="px-3 py-4 text-sm">
                                                            {dateText(
                                                                leave.startDate,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-4 text-sm">
                                                            {dateText(
                                                                leave.endDate,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-4 text-sm font-bold">
                                                            {leave.numberOfDays}
                                                        </td>
                                                        <td className="px-3 py-4 text-sm">
                                                            {leave.leaveType}
                                                            <div className="mt-1 text-xs text-slate-500">
                                                                {leave.isPaid
                                                                    ? 'Paid'
                                                                    : 'Unpaid'}
                                                            </div>
                                                        </td>
                                                    </>
                                                ) : (
                                                    <>
                                                        <td className="px-4 py-4 text-sm">
                                                            {dateText(
                                                                timed.requestDate,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-4 text-sm font-semibold">
                                                            {timed.requestTime}
                                                        </td>
                                                    </>
                                                )}
                                                <td className="max-w-sm px-4 py-4 text-sm leading-5 text-[#4f5262]">
                                                    {item.reason}
                                                    {activeType === 'leave' &&
                                                        leave.initialApproval !==
                                                            'not_required' && (
                                                            <p className="mt-2 text-xs text-red-800">
                                                                Team leader:{' '}
                                                                {
                                                                    leave.initialApproval
                                                                }
                                                                {leave.leaderName &&
                                                                    ` · ${leave.leaderName}`}
                                                                {leave.leaderNotes &&
                                                                    ` — ${leave.leaderNotes}`}
                                                            </p>
                                                        )}
                                                </td>
                                                <td className="px-4 py-4 text-sm">
                                                    {item.team ??
                                                        'Not assigned'}
                                                </td>
                                                <td className="px-4 py-4 text-xs text-[#626576]">
                                                    {dateTimeText(
                                                        item.submittedAt,
                                                    )}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-3 py-1.5 text-xs font-bold whitespace-nowrap ${presentation.classes}`}
                                                    >
                                                        {presentation.label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    {item.status ===
                                                    'needs_review' ? (
                                                        <div className="flex gap-2">
                                                            <Tooltip
                                                                title={
                                                                    initialBlocked
                                                                        ? 'Team-leader approval required first'
                                                                        : 'Approve'
                                                                }
                                                            >
                                                                <span>
                                                                    <IconButton
                                                                        disabled={
                                                                            initialBlocked ||
                                                                            processingKey !==
                                                                                null
                                                                        }
                                                                        onClick={() =>
                                                                            decide(
                                                                                activeType,
                                                                                item,
                                                                                'approved',
                                                                            )
                                                                        }
                                                                        sx={{
                                                                            width: 34,
                                                                            height: 34,
                                                                            bgcolor:
                                                                                '#eaf9f1',
                                                                            color: '#079455',
                                                                            '&:hover':
                                                                                {
                                                                                    bgcolor:
                                                                                        '#d6f3e3',
                                                                                },
                                                                        }}
                                                                    >
                                                                        <Check
                                                                            size={
                                                                                18
                                                                            }
                                                                        />
                                                                    </IconButton>
                                                                </span>
                                                            </Tooltip>
                                                            <Tooltip title="Reject">
                                                                <IconButton
                                                                    disabled={
                                                                        processingKey !==
                                                                        null
                                                                    }
                                                                    onClick={() =>
                                                                        decide(
                                                                            activeType,
                                                                            item,
                                                                            'rejected',
                                                                        )
                                                                    }
                                                                    sx={{
                                                                        width: 34,
                                                                        height: 34,
                                                                        bgcolor:
                                                                            '#fff0ef',
                                                                        color: '#d92d20',
                                                                        '&:hover':
                                                                            {
                                                                                bgcolor:
                                                                                    '#ffe0dd',
                                                                            },
                                                                    }}
                                                                >
                                                                    <X
                                                                        size={
                                                                            18
                                                                        }
                                                                    />
                                                                </IconButton>
                                                            </Tooltip>
                                                        </div>
                                                    ) : (
                                                        <Button
                                                            size="small"
                                                            onClick={() =>
                                                                decide(
                                                                    activeType,
                                                                    item,
                                                                    item.status ===
                                                                        'approved'
                                                                        ? 'rejected'
                                                                        : 'approved',
                                                                )
                                                            }
                                                            disabled={
                                                                (initialBlocked &&
                                                                    item.status !==
                                                                        'approved') ||
                                                                processingKey !==
                                                                    null
                                                            }
                                                        >
                                                            {item.status ===
                                                            'approved'
                                                                ? 'Revoke approval'
                                                                : 'Review again'}
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {visible.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={
                                                    activeType === 'leave'
                                                        ? 12
                                                        : 10
                                                }
                                                className="px-6 py-14 text-center text-sm text-[#888b9b]"
                                            >
                                                No {activeType} requests match
                                                this status.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {pages > 1 && (
                            <Box
                                sx={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                    gap: 1,
                                    px: 3,
                                    pb: 3,
                                }}
                            >
                                {Array.from(
                                    { length: pages },
                                    (_, index) => index + 1,
                                ).map((number) => (
                                    <button
                                        key={number}
                                        type="button"
                                        onClick={() => setPage(number)}
                                        className={`size-9 rounded-lg text-sm font-semibold ${page === number ? 'bg-[#a92420] text-white' : 'text-[#666979] hover:bg-[#f3f3f6]'}`}
                                    >
                                        {number}
                                    </button>
                                ))}
                            </Box>
                        )}
                    </section>
                </div>
            </main>
            <Dialog
                open={review !== null}
                onClose={() => !processingKey && setReview(null)}
                fullWidth
                maxWidth="sm"
                slotProps={{ paper: { sx: { borderRadius: 3 } } }}
            >
                <DialogTitle sx={{ fontWeight: 800 }}>
                    {review?.decision === 'approved' ? 'Approve' : 'Reject'}{' '}
                    {review?.type} request
                </DialogTitle>
                <DialogContent>
                    <p className="mb-4 text-sm text-slate-600">
                        {review?.item.requester} · {review?.item.employeeId}
                    </p>
                    {review?.type === 'leave' &&
                    review.decision === 'approved' ? (
                        <>
                            <TextField
                                select
                                fullWidth
                                label="Leave payment"
                                value={isPaid ? 'paid' : 'unpaid'}
                                onChange={(event) =>
                                    setIsPaid(event.target.value === 'paid')
                                }
                                sx={{ mt: 1 }}
                            >
                                <MenuItem value="paid">Paid leave</MenuItem>
                                <MenuItem value="unpaid">Unpaid leave</MenuItem>
                            </TextField>
                            <p className="mt-3 text-sm text-slate-600">
                                Paid leave credits scheduled working hours after
                                deducting the scheduled break. Rest days and
                                days without a schedule receive no hours. Unpaid
                                leave receives zero hours.
                            </p>
                        </>
                    ) : (
                        <p className="text-sm text-slate-600">
                            {review?.decision === 'approved'
                                ? `Approve clock-out at ${(review?.item as TimeRequest)?.requestTime} for the shift starting ${(review?.item as TimeRequest)?.requestDate}? Actual attendance is still required; hours exclude breaks.`
                                : 'Rejecting this request removes its approval from attendance and recalculates the hours.'}
                        </p>
                    )}
                    {reviewError && (
                        <p role="alert" className="mt-4 text-sm text-red-700">
                            {reviewError}
                        </p>
                    )}
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        disabled={!!processingKey}
                        onClick={() => setReview(null)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={!!processingKey}
                        onClick={submitReview}
                    >
                        {processingKey
                            ? 'Saving…'
                            : review?.decision === 'approved'
                              ? 'Approve request'
                              : 'Reject request'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

Requests.layout = {
    breadcrumbs: [{ title: 'Requests', href: '/requests' }],
};
