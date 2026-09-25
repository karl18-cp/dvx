import { router, useForm } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
} from '@mui/material';
import { X } from 'lucide-react';
import { DateTimeField } from '@/components/date-time-field';
export type Scorecard = {
    id: number;
    name: string;
    applies_to_all_campaigns: boolean;
    campaigns: { id: number; name: string }[];
};
export type Employee = {
    id: number;
    name: string;
    username: string;
    evaluation_teams: {
        id: number;
        name: string;
        campaign: { id: number; name: string };
    }[];
};
export default function Create({
    employees,
    scorecards,
    onClose,
}: {
    employees: Employee[];
    scorecards: Scorecard[];
    onClose?: () => void;
}) {
    const form = useForm({
        employee_id: '',
        team_id: '',
        scorecard_id: '',
        call_at: new Date().toISOString().slice(0, 16),
        call_direction: 'inbound',
        call_reference: '',
        recording: null as File | null,
        evaluation_document: null as File | null,
    });
    const employee = employees.find(
        (e) => String(e.id) === String(form.data.employee_id),
    );
    const team = employee?.evaluation_teams.find(
        (item) => String(item.id) === form.data.team_id,
    );
    const compatibleScorecards = (campaignId?: number) =>
        campaignId
            ? scorecards.filter(
                  (scorecard) =>
                      scorecard.applies_to_all_campaigns ||
                      scorecard.campaigns.some(
                          (campaign) => campaign.id === campaignId,
                      ),
              )
            : [];
    const eligible = compatibleScorecards(team?.campaign.id);
    const selectEmployee = (employeeId: string) => {
        const selectedEmployee = employees.find(
            (item) => String(item.id) === employeeId,
        );
        const selectedTeam =
            selectedEmployee?.evaluation_teams.length === 1
                ? selectedEmployee.evaluation_teams[0]
                : undefined;
        const matches = compatibleScorecards(selectedTeam?.campaign.id);

        form.setData({
            ...form.data,
            employee_id: employeeId,
            team_id: selectedTeam ? String(selectedTeam.id) : '',
            scorecard_id: matches.length === 1 ? String(matches[0].id) : '',
        });
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/management/call-evaluations', { forceFormData: true });
    };

    return (
        <Dialog
            open
            fullWidth
            maxWidth="md"
            aria-labelledby="new-evaluation-title"
            onClose={() => {
                if (!form.processing) {
                    if (onClose) onClose();
                    else router.get('/management/call-evaluations');
                }
            }}
            slotProps={{
                paper: {
                    sx: {
                        m: 2,
                        width: 'calc(100% - 32px)',
                        maxHeight: 'calc(100dvh - 32px)',
                    },
                },
            }}
        >
            <DialogTitle
                id="new-evaluation-title"
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                New Evaluation
                <IconButton
                    aria-label="Close evaluation"
                    disabled={form.processing}
                    onClick={() => {
                        if (onClose) onClose();
                        else router.get('/management/call-evaluations');
                    }}
                >
                    <X />
                </IconButton>
            </DialogTitle>
            <form
                onSubmit={submit}
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    minHeight: 0,
                    overflow: 'hidden',
                }}
            >
                <DialogContent
                    dividers
                    className="assessment-admin"
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 3,
                        px: { xs: 2, sm: 3 },
                        py: 3,
                        '& > *': { flexShrink: 0 },
                    }}
                >
                    <p className="text-sm text-slate-500">
                        Select an employee to load their campaign, team, and
                        available scorecards.
                    </p>
                    <Field label="Employee">
                        <select
                            value={form.data.employee_id}
                            onChange={(e) => selectEmployee(e.target.value)}
                            required
                        >
                            <option value="">Select Employee</option>
                            {employees.map((e) => (
                                <option value={e.id} key={e.id}>
                                    {e.name} ({e.username})
                                </option>
                            ))}
                        </select>
                    </Field>
                    {employee && employee.evaluation_teams.length === 0 && (
                        <p role="alert" className="text-sm text-red-700">
                            This employee has no campaign team assigned. Assign
                            an agent through Team Assigning, or assign a team
                            leader to a team, before creating an evaluation.
                        </p>
                    )}
                    {employee && employee.evaluation_teams.length > 1 && (
                        <Field label="Evaluation team">
                            <select
                                required
                                value={form.data.team_id}
                                onChange={(event) => {
                                    const selected =
                                        employee.evaluation_teams.find(
                                            (item) =>
                                                String(item.id) ===
                                                event.target.value,
                                        );
                                    const matches = compatibleScorecards(
                                        selected?.campaign.id,
                                    );
                                    form.setData({
                                        ...form.data,
                                        team_id: event.target.value,
                                        scorecard_id:
                                            matches.length === 1
                                                ? String(matches[0].id)
                                                : '',
                                    });
                                }}
                            >
                                <option value="">Select a team</option>
                                {employee.evaluation_teams.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} — {item.campaign.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    )}
                    {team && (
                        <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                            <div>
                                <small>Campaign</small>
                                <b className="block">{team.campaign.name}</b>
                            </div>
                            <div>
                                <small>Team</small>
                                <b className="block">{team.name}</b>
                            </div>
                        </div>
                    )}
                    <Field label="Active Campaign-Compatible Scorecard">
                        <select
                            value={form.data.scorecard_id}
                            onChange={(e) =>
                                form.setData('scorecard_id', e.target.value)
                            }
                            required
                            disabled={!team}
                        >
                            <option value="">
                                {!employee
                                    ? 'Select an employee first'
                                    : !team
                                      ? 'Select an assigned team first'
                                      : eligible.length === 0
                                        ? 'No active scorecard matches this campaign'
                                        : 'Select Scorecard'}
                            </option>
                            {eligible.map((s) => (
                                <option value={s.id} key={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                        {!employee && (
                            <span className="mt-2 block text-xs font-normal text-slate-600">
                                Choose an employee above to load scorecards for
                                their Campaign.
                            </span>
                        )}
                        {employee && eligible.length === 1 && (
                            <span className="mt-2 block text-xs font-normal text-emerald-700">
                                The compatible active scorecard was selected
                                automatically.
                            </span>
                        )}
                        {team && eligible.length === 0 && (
                            <span className="mt-2 block text-xs font-normal text-red-700">
                                Activate a scorecard for {team.campaign.name}{' '}
                                before creating this evaluation.
                            </span>
                        )}
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="min-w-0">
                            <div className="mb-1 text-sm font-semibold">
                                Call Date &amp; Time
                            </div>
                            <DateTimeField
                                fullWidth
                                size="small"
                                sx={{
                                    '& .MuiOutlinedInput-root': { height: 42 },
                                    '& .MuiInputBase-input': {
                                        fontSize: '0.875rem',
                                    },
                                }}
                                slotProps={{
                                    htmlInput: {
                                        'aria-label': 'Call Date & Time',
                                    },
                                }}
                                type="datetime-local"
                                value={form.data.call_at}
                                onChange={(e) =>
                                    form.setData('call_at', e.target.value)
                                }
                                required
                            />
                        </div>
                        <Field label="Direction">
                            <select
                                value={form.data.call_direction}
                                onChange={(e) =>
                                    form.setData(
                                        'call_direction',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="inbound">Inbound</option>
                                <option value="outbound">Outbound</option>
                            </select>
                        </Field>
                    </div>
                    <Field label="Call Reference (optional)">
                        <input
                            value={form.data.call_reference}
                            onChange={(e) =>
                                form.setData('call_reference', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Recording (MP3, WAV, or M4A; optional for Draft)">
                        <input
                            type="file"
                            accept=".mp3,.wav,.m4a,audio/*"
                            onChange={(e) =>
                                form.setData(
                                    'recording',
                                    e.target.files?.[0] || null,
                                )
                            }
                        />
                    </Field>
                    <Field label="Evaluation Document (DOCX; optional)">
                        <input
                            type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            onChange={(e) =>
                                form.setData(
                                    'evaluation_document',
                                    e.target.files?.[0] || null,
                                )
                            }
                        />
                    </Field>
                    {Object.values(form.errors).map((error, i) => (
                        <p
                            className="text-sm font-semibold text-red-700"
                            key={i}
                        >
                            {error}
                        </p>
                    ))}
                </DialogContent>
                <DialogActions sx={{ px: 3, py: 2, flexShrink: 0 }}>
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={
                            form.processing ||
                            !employee ||
                            !team ||
                            !form.data.scorecard_id
                        }
                    >
                        {form.processing ? 'Creating…' : 'Create Draft'}
                    </Button>
                </DialogActions>
            </form>
        </Dialog>
    );
}
function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block text-sm font-semibold [&>input]:mt-1 [&>input]:w-full [&>input]:rounded-lg [&>input]:border [&>input]:p-3 [&>select]:mt-1 [&>select]:w-full [&>select]:rounded-lg [&>select]:border [&>select]:p-3">
            {label}
            {children}
        </label>
    );
}
Create.layout = {
    breadcrumbs: [
        { title: 'Quality Assurance', href: '/management/qa-scorecards' },
        { title: 'Call Evaluations', href: '/management/call-evaluations' },
        { title: 'New Evaluation', href: '#' },
    ],
};
