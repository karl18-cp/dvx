import { Head, useForm } from '@inertiajs/react';
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
import { X } from 'lucide-react';
import { useState } from 'react';
import PageLink from '@/components/page-link';
import Employees from '@/pages/employees';

type Trainee = {
    id: number;
    name: string;
    username: string;
    campaign: string | null;
    schedule: string | null;
    status: string;
    notes: string | null;
    reviewed_at: string | null;
    employeeUsername: string | null;
    canCreateEmployee: boolean;
    canAssign: boolean;
    trainingCampaignId: number | null;
    teamId: number | null;
    teamName: string | null;
    leaderName: string | null;
};
export default function Trainees({
    trainees,
    statusMessage,
    nextTraineeId,
    trainingCampaigns,
    assignmentTeams,
}: {
    trainees: {
        data: Trainee[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    statusMessage?: string;
    nextTraineeId: string;
    trainingCampaigns: { id: number; name: string }[];
    assignmentTeams: {
        id: number;
        name: string;
        campaign_id: number;
        leader: string;
    }[];
}) {
    const [onboardingOpen, setOnboardingOpen] = useState(false);
    const [selected, setSelected] = useState<Trainee | null>(null);
    const [creating, setCreating] = useState<Trainee | null>(null);
    const employeeForm = useForm({});
    const [assigning, setAssigning] = useState<Trainee | null>(null);
    const assignment = useForm({ team_id: '' });
    const form = useForm<{ decision: 'graduated' | 'rejected'; notes: string }>(
        { decision: 'graduated', notes: '' },
    );
    const review = (trainee: Trainee, decision: 'graduated' | 'rejected') => {
        form.clearErrors();
        form.setData({ decision, notes: '' });
        setSelected(trainee);
    };

    return (
        <div className="space-y-6 p-4 md:p-8">
            <Head title="Trainees" />
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-sm tracking-widest text-red-700 uppercase">
                        Training & development
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">Trainees</h1>
                    <p className="mt-2 text-slate-500">
                        Keep trainee records and create separate employee
                        accounts after passing.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setOnboardingOpen(true)}
                    className="rounded-xl bg-red-700 px-5 py-3 font-semibold text-white"
                >
                    Add trainee
                </button>
            </div>
            {statusMessage && <Alert severity="success">{statusMessage}</Alert>}
            {onboardingOpen && (
                <Employees
                    onboardingOnly
                    createTrainee
                    canManageEmployees
                    trainingCampaigns={trainingCampaigns}
                    nextTraineeId={nextTraineeId}
                    nextEmployeeId=""
                    employees={[]}
                    stats={{ total: 0, active: 0, onLeave: 0, inactive: 0 }}
                    onOnboardingClose={() => setOnboardingOpen(false)}
                />
            )}
            <div className="flex flex-wrap gap-4 text-sm text-red-700">
                <PageLink href="/campaign-schedules">
                    Assign attendance schedules
                </PageLink>
                <PageLink href="/management/assessment-assignments">
                    Assign training
                </PageLink>
                <PageLink href="/management/coaching">
                    Manage coaching logs
                </PageLink>
            </div>
            <div className="overflow-hidden rounded-2xl border border-t-4 border-slate-200 border-t-red-700 bg-white shadow-sm">
                <div className="max-h-[65vh] overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="sticky top-0 bg-red-50">
                            <tr>
                                {[
                                    'Trainee',
                                    'Training campaign',
                                    'Team / Leader',
                                    'Schedule',
                                    'Status',
                                    'Review',
                                    'Actions',
                                ].map((label) => (
                                    <th key={label} className="p-4">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {trainees.data.map((trainee) => (
                                <tr
                                    key={trainee.id}
                                    className="border-t border-slate-100"
                                >
                                    <td className="p-4 font-semibold">
                                        {trainee.name}
                                        <p className="font-normal text-slate-500">
                                            {trainee.username}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        {trainee.campaign ?? 'Not assigned'}
                                    </td>
                                    <td className="p-4">
                                        <p>
                                            {trainee.teamName ?? 'Not assigned'}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {trainee.leaderName ??
                                                'No team leader'}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        {trainee.schedule ??
                                            'Assign a schedule to enable attendance'}
                                    </td>
                                    <td className="p-4 capitalize">
                                        {{
                                            in_training: 'Active Trainee',
                                            graduated: 'Passed',
                                            rejected: 'Failed',
                                        }[trainee.status] ?? trainee.status}
                                    </td>
                                    <td className="max-w-xs p-4 whitespace-pre-wrap">
                                        {trainee.notes ?? '—'}
                                        {trainee.reviewed_at && (
                                            <p className="text-xs text-slate-500">
                                                {trainee.reviewed_at}
                                            </p>
                                        )}
                                    </td>
                                    <td className="p-4">
                                        {trainee.canAssign && (
                                            <Button
                                                onClick={() => {
                                                    assignment.clearErrors();
                                                    assignment.setData(
                                                        'team_id',
                                                        trainee.teamId
                                                            ? String(
                                                                  trainee.teamId,
                                                              )
                                                            : '',
                                                    );
                                                    setAssigning(trainee);
                                                }}
                                            >
                                                Assign team leader
                                            </Button>
                                        )}
                                        {trainee.status === 'in_training' && (
                                            <div className="flex gap-2">
                                                <Button
                                                    onClick={() =>
                                                        review(
                                                            trainee,
                                                            'graduated',
                                                        )
                                                    }
                                                >
                                                    Mark Passed
                                                </Button>
                                                <Button
                                                    color="error"
                                                    onClick={() =>
                                                        review(
                                                            trainee,
                                                            'rejected',
                                                        )
                                                    }
                                                >
                                                    Mark Failed
                                                </Button>
                                            </div>
                                        )}
                                        {trainee.employeeUsername && (
                                            <p className="text-sm text-slate-500">
                                                Employee account:{' '}
                                                {trainee.employeeUsername}
                                            </p>
                                        )}
                                        {trainee.canCreateEmployee && (
                                            <Button
                                                onClick={() => {
                                                    employeeForm.clearErrors();
                                                    setCreating(trainee);
                                                }}
                                            >
                                                Create employee account
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!trainees.data.length && (
                        <p className="p-12 text-center text-slate-500">
                            No trainees yet. Add a trainee and select their
                            training campaign.
                        </p>
                    )}
                </div>
            </div>
            <div className="flex justify-end gap-4">
                {trainees.prev_page_url && (
                    <PageLink href={trainees.prev_page_url}>Previous</PageLink>
                )}
                {trainees.next_page_url && (
                    <PageLink href={trainees.next_page_url}>Next</PageLink>
                )}
            </div>
            <Dialog
                open={!!assigning}
                onClose={() => !assignment.processing && setAssigning(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Assign team leader
                    <IconButton
                        aria-label="Close assignment"
                        disabled={assignment.processing}
                        onClick={() => setAssigning(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    <div className="grid gap-5 py-2">
                        <p className="text-sm text-slate-500">
                            Assign {assigning?.name} to a team in{' '}
                            {assigning?.campaign ?? 'their training campaign'}.
                            The team’s leader will handle this trainee.
                        </p>
                        <TextField
                            select
                            fullWidth
                            label="Team and leader"
                            value={assignment.data.team_id}
                            onChange={(e) =>
                                assignment.setData('team_id', e.target.value)
                            }
                            error={!!assignment.errors.team_id}
                            helperText={assignment.errors.team_id}
                        >
                            {assignmentTeams
                                .filter(
                                    (t) =>
                                        t.campaign_id ===
                                        assigning?.trainingCampaignId,
                                )
                                .map((t) => (
                                    <MenuItem key={t.id} value={String(t.id)}>
                                        {t.name} — {t.leader}
                                    </MenuItem>
                                ))}
                        </TextField>
                        {!assignmentTeams.some(
                            (t) =>
                                t.campaign_id === assigning?.trainingCampaignId,
                        ) && (
                            <Alert severity="info">
                                Assign an active team leader to a team in this
                                training campaign first using Team Assigning.
                            </Alert>
                        )}
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={
                            assignment.processing || !assignment.data.team_id
                        }
                        onClick={() =>
                            assigning &&
                            assignment.put(`/trainees/${assigning.id}/team`, {
                                preserveScroll: true,
                                onSuccess: () => setAssigning(null),
                            })
                        }
                    >
                        Save assignment
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!selected}
                onClose={() => !form.processing && setSelected(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    {form.data.decision === 'graduated'
                        ? 'Mark trainee Passed'
                        : 'Mark trainee Failed'}
                    <IconButton
                        aria-label="Close review"
                        disabled={form.processing}
                        onClick={() => setSelected(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent>
                    <p className="mb-5">
                        {form.data.decision === 'graduated'
                            ? `${selected?.name} will keep their trainee account and all training records. You can create a separate employee account afterward.`
                            : `${selected?.name} will lose access to the system. Their records will be retained.`}
                    </p>
                    <TextField
                        label={
                            form.data.decision === 'rejected'
                                ? 'Reason for rejection'
                                : 'Review notes (optional)'
                        }
                        required={form.data.decision === 'rejected'}
                        fullWidth
                        multiline
                        minRows={3}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        error={!!form.errors.notes}
                        helperText={form.errors.notes}
                    />
                </DialogContent>
                <DialogActions>
                    <Button
                        variant="contained"
                        color="error"
                        disabled={
                            form.processing ||
                            (form.data.decision === 'rejected' &&
                                !form.data.notes.trim())
                        }
                        onClick={() =>
                            form.patch(`/trainees/${selected?.id}/review`, {
                                onSuccess: () => setSelected(null),
                            })
                        }
                    >
                        {form.processing
                            ? 'Saving…'
                            : form.data.decision === 'graduated'
                              ? 'Mark Passed'
                              : 'Reject and end access'}
                    </Button>
                </DialogActions>
            </Dialog>
            <Dialog
                open={!!creating}
                onClose={() => !employeeForm.processing && setCreating(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Create employee account
                    <IconButton
                        aria-label="Close"
                        disabled={employeeForm.processing}
                        onClick={() => setCreating(null)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers>
                    <p>
                        Create a new DVX employee ID for {creating?.name} using
                        their personal details, photo, face enrollment, and
                        assigned schedule. Their DVXTR account, password,
                        attendance, coaching, and training history will remain
                        separate.
                    </p>
                    <Alert severity="info" sx={{ mt: 2 }}>
                        Both accounts keep the same email. Use the separate
                        account IDs to sign in or reset passwords. The new
                        employee’s temporary password matches their new DVX ID.
                    </Alert>
                    {Object.values(employeeForm.errors).map((error, i) => (
                        <Alert key={i} severity="error">
                            {String(error)}
                        </Alert>
                    ))}
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button
                        variant="contained"
                        disabled={employeeForm.processing}
                        onClick={() =>
                            creating &&
                            employeeForm.post(
                                `/trainees/${creating.id}/employee-account`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setCreating(null),
                                },
                            )
                        }
                    >
                        {employeeForm.processing
                            ? 'Creating…'
                            : 'Create employee account'}
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
}
