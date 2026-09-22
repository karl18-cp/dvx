import { Head, Link, useForm } from '@inertiajs/react';
import { DateTimeField } from '@/components/date-time-field';
type Scorecard = {
    id: number;
    name: string;
    applies_to_all_campaigns: boolean;
    campaigns: { id: number; name: string }[];
};
type Employee = {
    id: number;
    name: string;
    username: string;
    team_membership: {
        team: { name: string; campaign: { id: number; name: string } };
    };
};
export default function Create({
    employees,
    scorecards,
}: {
    employees: Employee[];
    scorecards: Scorecard[];
}) {
    const form = useForm({
        employee_id: '',
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
    const compatibleScorecards = (selectedEmployee?: Employee) =>
        scorecards.filter(
            (scorecard) =>
                !selectedEmployee ||
                scorecard.applies_to_all_campaigns ||
                scorecard.campaigns.some(
                    (campaign) =>
                        campaign.id ===
                        selectedEmployee.team_membership.team.campaign.id,
                ),
        );
    const eligible = compatibleScorecards(employee);
    const selectEmployee = (employeeId: string) => {
        const selectedEmployee = employees.find(
            (item) => String(item.id) === employeeId,
        );
        const matches = compatibleScorecards(selectedEmployee);

        form.setData({
            ...form.data,
            employee_id: employeeId,
            scorecard_id: matches.length === 1 ? String(matches[0].id) : '',
        });
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/management/call-evaluations', { forceFormData: true });
    };

    return (
        <main className="assessment-admin min-h-full bg-[#f7f7fa] p-6 text-slate-900">
            <Head title="New Call Evaluation" />
            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-5">
                <header>
                    <Link
                        className="font-bold text-red-700"
                        href="/management/call-evaluations"
                    >
                        ← Call Evaluations
                    </Link>
                    <h1 className="mt-3">New Evaluation</h1>
                    <p>
                        Select the employee first; Campaign and Team are
                        resolved automatically.
                    </p>
                </header>
                <section className="space-y-4 rounded-2xl border bg-white p-6">
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
                    {employee && (
                        <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                            <div>
                                <small>Campaign</small>
                                <b className="block">
                                    {
                                        employee.team_membership.team.campaign
                                            .name
                                    }
                                </b>
                            </div>
                            <div>
                                <small>Team</small>
                                <b className="block">
                                    {employee.team_membership.team.name}
                                </b>
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
                            disabled={!employee}
                        >
                            <option value="">
                                {!employee
                                    ? 'Select an employee first'
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
                        {employee && eligible.length === 0 && (
                            <span className="mt-2 block text-xs font-normal text-red-700">
                                Activate a scorecard for{' '}
                                {employee.team_membership.team.campaign.name}{' '}
                                before creating this evaluation.
                            </span>
                        )}
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Call Date & Time">
                            <DateTimeField
                                type="datetime-local"
                                value={form.data.call_at}
                                onChange={(e) =>
                                    form.setData('call_at', e.target.value)
                                }
                                required
                            />
                        </Field>
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
                    <div className="flex justify-end">
                        <button
                            disabled={form.processing}
                            className="rounded-xl bg-red-700 px-5 py-3 font-bold text-white"
                        >
                            Create Draft
                        </button>
                    </div>
                </section>
            </form>
        </main>
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
