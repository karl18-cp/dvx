import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ShieldCheck } from 'lucide-react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import DivertexLogo from '@/components/divertex-logo';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
/* @chisel-passkeys */
/* @end-chisel-passkeys */

export default function ConfirmPassword() {
    return (
        <main className="app-workspace flex min-h-dvh items-center justify-center px-4 py-6 text-[#17202d] [--background:white] [--foreground:#17202d] [--input:#e3dadd] [--muted-foreground:#64748b] [--primary-foreground:white] [--primary:#ae1b20]">
            <Head title="Confirm password" />
            <section
                aria-labelledby="confirm-title"
                className="w-full max-w-md overflow-hidden rounded-3xl border border-red-100 bg-white shadow-xl shadow-red-950/5"
            >
                <div className="h-1.5 bg-gradient-to-r from-[#7f2528] to-[#df1c28]" />
                <div className="p-6 sm:p-8">
                    <Link
                        href="/settings/profile"
                        className="inline-flex items-center gap-2 text-sm text-slate-500 transition-colors hover:text-red-800 focus-visible:outline-2 focus-visible:outline-red-700"
                    >
                        <ArrowLeft className="size-4" /> Back to settings
                    </Link>
                    <DivertexLogo className="mx-auto my-5 w-36" />
                    <div className="mb-6 text-center">
                        <div className="mx-auto mb-3 flex size-11 items-center justify-center rounded-2xl bg-red-50 text-red-800">
                            <ShieldCheck
                                className="size-6"
                                aria-hidden="true"
                            />
                        </div>
                        <p className="mb-2 text-xs font-semibold tracking-[0.18em] text-red-800 uppercase">
                            Account security
                        </p>
                        <h1
                            id="confirm-title"
                            className="text-2xl font-bold tracking-tight"
                        >
                            Confirm it’s you
                        </h1>
                        <p className="mt-2 text-sm leading-relaxed text-slate-500">
                            Enter your current password to continue to your
                            password and security settings.
                        </p>
                    </div>

                    {/* @chisel-passkeys */}
                    <PasskeyVerify
                        routes={{
                            options: confirmOptions(),
                            submit: confirmStore(),
                        }}
                        label="Confirm with passkey"
                        loadingLabel="Confirming..."
                        separator="Or confirm with password"
                    />
                    {/* @end-chisel-passkeys */}

                    <Form {...store.form()} resetOnSuccess={['password']}>
                        {({ processing, errors }) => (
                            <div className="space-y-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        Current password
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        placeholder="Enter your current password"
                                        autoComplete="current-password"
                                        autoFocus
                                        required
                                        aria-invalid={!!errors.password}
                                        aria-describedby={
                                            errors.password
                                                ? 'password-error'
                                                : undefined
                                        }
                                    />

                                    <InputError
                                        id="password-error"
                                        message={errors.password}
                                    />
                                </div>

                                <div className="flex items-center">
                                    <Button
                                        className="h-11 w-full rounded-xl bg-gradient-to-r from-[#922528] to-[#ce1c28] text-white shadow-sm hover:from-[#7f2023] hover:to-[#b71924]"
                                        disabled={processing}
                                        data-test="confirm-password-button"
                                    >
                                        {processing && <Spinner />}
                                        Confirm password
                                        {!processing && (
                                            <ArrowRight className="size-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                    <p className="mt-6 border-t border-slate-100 pt-4 text-center text-xs text-slate-400">
                        Your new password is set on the next screen.
                    </p>
                </div>
            </section>
        </main>
    );
}

ConfirmPassword.layout = {
    immersive: true,
    title: 'Confirm password',
    description:
        'This is a secure area of the application. Please confirm your password before continuing.',
};
