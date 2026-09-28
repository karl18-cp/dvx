import { Head } from '@inertiajs/react';
import { Alert } from '@mui/material';
import TrainingAllowanceDetails, {
    type Allowance,
} from '@/components/training-allowance-details';

export default function MyTrainingPlan({
    plan,
    allowance,
}: {
    plan: {
        name: string;
        start_date: string;
        campaign: { name: string };
        first_allowance_day: number;
    } | null;
    allowance: Allowance | null;
}) {
    return (
        <>
            <Head title="Training Plan & Allowance" />
            <main className="space-y-6 p-4 sm:p-8">
                <header>
                    <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                        Your training journey
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">
                        Training Plan & Allowance
                    </h1>
                    <p className="mt-2 text-slate-500">
                        Your training phases, attendance, and first allowance
                        progress.
                    </p>
                </header>
                {plan && allowance ? (
                    <section className="space-y-6 rounded-2xl border border-t-4 border-slate-200 border-t-red-700 bg-white p-5 sm:p-7">
                        <div>
                            <h2 className="text-xl font-bold">{plan.name}</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {plan.campaign.name} · Starts{' '}
                                {plan.start_date.slice(0, 10)} · First allowance
                                after {plan.first_allowance_day} attended days
                            </p>
                        </div>
                        <TrainingAllowanceDetails
                            allowance={allowance}
                            threshold={plan.first_allowance_day}
                        />
                    </section>
                ) : (
                    <Alert severity="info">
                        No training plan has been assigned yet. Your
                        administrator or QA assessment admin can enroll you in
                        your campaign’s training plan.
                    </Alert>
                )}
            </main>
        </>
    );
}
MyTrainingPlan.layout = {
    breadcrumbs: [
        { title: 'Training Plan & Allowance', href: '/my-training-plan' },
    ],
};
