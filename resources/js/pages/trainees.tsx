import { Head, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
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
};
export default function Trainees({
    trainees,
    statusMessage,
    nextTraineeId,
    trainingCampaigns,
}: {
    trainees: {
        data: Trainee[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    statusMessage?: string;
    nextTraineeId: string;
    trainingCampaigns: { id: number; name: string }[];
}) {
    const [onboardingOpen, setOnboardingOpen] = useState(false);
    const [selected, setSelected] = useState<Trainee | null>(null);
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
                        Review trainees and graduate them into the Agent
                        workspace.
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
                                        {trainee.schedule ??
                                            'Assign a schedule to enable attendance'}
                                    </td>
                                    <td className="p-4 capitalize">
                                        {trainee.status.replaceAll('_', ' ')}
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
                                                    Approve graduation
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
                                                    Reject
                                                </Button>
                                            </div>
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
                open={!!selected}
                onClose={() => !form.processing && setSelected(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    {form.data.decision === 'graduated'
                        ? 'Approve graduation'
                        : 'Reject trainee'}
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
                            ? `${selected?.name} will become an Agent. Attendance and coaching history will be retained.`
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
                              ? 'Graduate to Agent'
                              : 'Reject and end access'}
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
}
