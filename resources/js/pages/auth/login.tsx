import { Form, Head } from '@inertiajs/react';
import '../../../css/login.css';
import {
    ArrowRight,
    BookOpenCheck,
    CalendarDays,
    UsersRound,
} from 'lucide-react';
import DivertexLogo from '@/components/divertex-logo';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
/* @end-chisel-passkeys */

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <main className="login-screen flex min-h-svh items-center justify-center bg-[#f6f6f9] p-4 text-[#17202d] [--background:white] [--foreground:#17202d] [--input:#e4d7d9] [--muted-foreground:#64748b] [--primary-foreground:white] [--primary:#ae1b20] [--ring:#ae1b20] sm:p-6">
            <Head title="Employee Login" />
            <div className="login-card grid w-full max-w-[480px] overflow-hidden rounded-3xl border border-[#eadfe1] bg-white shadow-xl shadow-[#3d1320]/5 lg:max-w-[940px] lg:grid-cols-[1fr_1.05fr]">
                <section className="login-intro relative hidden flex-col overflow-hidden bg-[linear-gradient(145deg,#191426_0%,#401414_58%,#981d25_100%)] p-8 text-white lg:flex">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute -right-44 -bottom-48 size-[580px] rounded-full border border-white/10 p-14"
                    >
                        <div className="h-full rounded-full border border-white/10" />
                    </div>
                    <div className="relative">
                        <p className="text-xs font-bold tracking-[0.25em] text-red-200 uppercase">
                            Divertex employee portal
                        </p>
                        <h2 className="mt-8 text-4xl leading-tight font-extrabold tracking-tight">
                            Your team.
                            <br />
                            Your work.
                            <br />
                            <span className="text-red-200">One workspace.</span>
                        </h2>
                        <p className="mt-4 max-w-sm text-sm leading-6 text-white/70">
                            Stay connected to your schedule, your team, and your
                            next opportunity to grow.
                        </p>
                    </div>
                    <div className="relative mt-8 space-y-4">
                        {[
                            {
                                icon: CalendarDays,
                                title: 'Stay on schedule',
                                text: 'Keep up with shifts and attendance.',
                            },
                            {
                                icon: UsersRound,
                                title: 'Connect with your team',
                                text: 'Find your people and campaign updates.',
                            },
                            {
                                icon: BookOpenCheck,
                                title: 'Keep growing',
                                text: 'Access training, assessments, and coaching.',
                            },
                        ].map(({ icon: Icon, title, text }) => (
                            <div
                                key={title}
                                className="flex items-center gap-3"
                            >
                                <span className="rounded-xl border border-white/10 bg-white/5 p-2.5">
                                    <Icon className="size-5 text-red-200" />
                                </span>
                                <div>
                                    <p className="text-sm font-bold">{title}</p>
                                    <p className="mt-1 text-xs text-white/60">
                                        {text}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
                <section className="login-panel p-6 sm:px-8">
                    <DivertexLogo className="login-logo mx-auto mb-4 w-36" />
                    <div className="login-heading mb-5">
                        <p className="mb-2 text-xs font-bold tracking-[0.18em] text-[#ae1b20] uppercase">
                            Employee access
                        </p>
                        <h1 className="text-2xl font-extrabold tracking-tight">
                            Welcome back
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Sign in to your Divertex workspace.
                        </p>
                    </div>
                    {status && (
                        <div
                            role="status"
                            className="mb-6 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800"
                        >
                            {status}
                        </div>
                    )}

                    {/* @chisel-passkeys */}
                    <div className="login-passkey">
                        <PasskeyVerify separator="Or use your employee ID or email" />
                    </div>
                    {/* @end-chisel-passkeys */}

                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        className="flex flex-col gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="login-fields grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Employee ID or email
                                        </Label>
                                        <Input
                                            id="email"
                                            type="text"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="username"
                                            placeholder="DVX002 or name@example.com"
                                            className="h-11 rounded-xl bg-white focus-visible:border-[#ae1b20]"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center">
                                            <Label htmlFor="password">
                                                Password
                                            </Label>
                                            {canResetPassword && (
                                                <TextLink
                                                    href={request()}
                                                    className="ml-auto text-xs text-[#ae1b20]"
                                                    tabIndex={5}
                                                >
                                                    Forgot your password?
                                                </TextLink>
                                            )}
                                        </div>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="Password"
                                            className="h-11 rounded-xl bg-white focus-visible:border-[#ae1b20]"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center space-x-3">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                        />
                                        <Label htmlFor="remember">
                                            Remember me
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="h-11 w-full rounded-xl bg-gradient-to-r from-[#8b2525] to-[#ce2029] font-bold text-white shadow-md shadow-red-100 hover:brightness-110"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing && <Spinner />}
                                        Employee Login
                                        {!processing && (
                                            <ArrowRight className="size-4" />
                                        )}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>

                    <p className="login-footer mt-5 border-t border-slate-100 pt-4 text-center text-xs text-slate-400">
                        Divertex · Employee Management System
                    </p>
                </section>
            </div>
        </main>
    );
}

Login.layout = {
    immersive: true,
    title: 'Employee Login',
    description: 'Enter your employee ID or email and password',
};
