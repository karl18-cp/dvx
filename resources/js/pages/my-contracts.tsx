import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    Eye,
    FileSignature,
    ShieldCheck,
    X,
    XCircle,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

type Contract = {
    id: number;
    title: string;
    contract_type: string;
    reference_number: string | null;
    effective_date: string;
    end_date: string | null;
    original_filename: string;
    mime_type: string;
    file_size: number;
    version: number;
    signing_status: string;
    issued_at: string;
    signature_due_at: string | null;
    decline_reason: string | null;
    signed_at: string | null;
    signature_hash: string | null;
    signed_pdf_available: boolean;
};
type Props = { contracts: Contract[]; signerName: string; consentText: string };
const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: value.includes('T') ? 'short' : undefined,
              timeZone: 'Asia/Manila',
          }).format(
              new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value),
          )
        : 'Open-ended';

export default function MyContracts({
    contracts,
    signerName,
    consentText,
}: Props) {
    const [reviewing, setReviewing] = useState<Contract | null>(null);
    const [signing, setSigning] = useState<Contract | null>(null);
    const [declining, setDeclining] = useState<Contract | null>(null);
    const sign = useForm({ signature_name: signerName, consent: false });
    const decline = useForm({ reason: '' });
    const submitSign = (event: FormEvent) => {
        event.preventDefault();
        if (!signing) return;
        sign.post(`/my-contracts/${signing.id}/sign`, {
            preserveScroll: true,
            onSuccess: () => setSigning(null),
        });
    };
    const submitDecline = (event: FormEvent) => {
        event.preventDefault();
        if (!declining) return;
        decline.post(`/my-contracts/${declining.id}/decline`, {
            preserveScroll: true,
            onSuccess: () => setDeclining(null),
        });
    };

    return (
        <>
            <Head title="My Contracts" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-[1400px] space-y-6">
                    <header className="relative overflow-hidden rounded-3xl bg-[linear-gradient(125deg,#181321_0%,#431923_56%,#9f1d24_100%)] px-6 py-7 text-white shadow-lg sm:px-8">
                        <div className="absolute -top-20 -right-12 size-64 rounded-full border-[36px] border-white/[0.06]" />
                        <div className="relative">
                            <p className="text-xs font-bold tracking-[.22em] text-red-200 uppercase">
                                Secure employee records
                            </p>
                            <h1 className="mt-3 text-2xl font-bold sm:text-3xl">
                                My Contracts
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-200">
                                Review the exact issued document, provide
                                explicit electronic consent, and download a
                                verifiable signed PDF certificate.
                            </p>
                        </div>
                    </header>
                    <section className="grid gap-5 lg:grid-cols-2">
                        {contracts.map((contract) => (
                            <article
                                key={contract.id}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                            >
                                <div className="border-b border-slate-100 p-5">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex gap-3">
                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-700">
                                                <FileSignature className="size-5" />
                                            </div>
                                            <div>
                                                <h2 className="font-bold text-slate-950">
                                                    {contract.title}
                                                </h2>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {label(
                                                        contract.contract_type,
                                                    )}{' '}
                                                    · Version {contract.version}
                                                </p>
                                            </div>
                                        </div>
                                        <Status
                                            value={contract.signing_status}
                                        />
                                    </div>
                                </div>
                                <div className="space-y-4 p-5">
                                    <div className="grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4 text-sm">
                                        <div>
                                            <p className="text-xs text-slate-400">
                                                Effective
                                            </p>
                                            <p className="mt-1 font-semibold">
                                                {date(contract.effective_date)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-slate-400">
                                                Signature due
                                            </p>
                                            <p className="mt-1 font-semibold">
                                                {date(
                                                    contract.signature_due_at,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-slate-400">
                                                Reference
                                            </p>
                                            <p className="mt-1 font-semibold">
                                                {contract.reference_number ||
                                                    `Contract #${contract.id}`}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-slate-400">
                                                Issued
                                            </p>
                                            <p className="mt-1 font-semibold">
                                                {date(contract.issued_at)}
                                            </p>
                                        </div>
                                    </div>
                                    {contract.signing_status === 'signed' && (
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                            <p className="flex items-center gap-2 font-bold text-emerald-800">
                                                <CheckCircle2 className="size-5" />{' '}
                                                Signed{' '}
                                                {date(contract.signed_at)}
                                            </p>
                                            <p className="mt-2 text-[10px] break-all text-emerald-700">
                                                Verification:{' '}
                                                {contract.signature_hash}
                                            </p>
                                        </div>
                                    )}
                                    {contract.signing_status === 'declined' && (
                                        <div className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                            <strong>Declined:</strong>{' '}
                                            {contract.decline_reason}
                                        </div>
                                    )}
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setReviewing(contract)
                                            }
                                            className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700"
                                        >
                                            <Eye className="size-4" /> Review
                                            document
                                        </button>
                                        <a
                                            href={`/my-contracts/${contract.id}/original`}
                                            className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700"
                                        >
                                            <Download className="size-4" />{' '}
                                            Original
                                        </a>
                                        {contract.signing_status ===
                                            'issued' && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        sign.setData({
                                                            signature_name:
                                                                signerName,
                                                            consent: false,
                                                        });
                                                        setSigning(contract);
                                                    }}
                                                    className="inline-flex h-10 items-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-bold text-white"
                                                >
                                                    <FileSignature className="size-4" />{' '}
                                                    Sign contract
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        decline.reset();
                                                        setDeclining(contract);
                                                    }}
                                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-red-300 px-4 text-sm font-bold text-red-700"
                                                >
                                                    <XCircle className="size-4" />{' '}
                                                    Decline
                                                </button>
                                            </>
                                        )}
                                        {contract.signed_pdf_available && (
                                            <a
                                                href={`/my-contracts/${contract.id}/signed`}
                                                className="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white"
                                            >
                                                <Download className="size-4" />{' '}
                                                Signed PDF
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                        {!contracts.length && (
                            <div className="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white py-20 text-center">
                                <FileSignature className="mx-auto size-11 text-slate-300" />
                                <p className="mt-3 font-bold text-slate-700">
                                    No contracts awaiting your action
                                </p>
                                <p className="mt-1 text-sm text-slate-500">
                                    Issued and signed contract versions will
                                    appear here.
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
            {reviewing && (
                <Modal
                    title={`${reviewing.title} · Version ${reviewing.version}`}
                    onClose={() => setReviewing(null)}
                    wide
                >
                    <div className="space-y-4">
                        <div className="rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                            Review the entire document before signing. Your
                            signature will be bound to this exact version.
                        </div>
                        {reviewing.mime_type === 'application/pdf' ||
                        reviewing.mime_type.startsWith('image/') ? (
                            <iframe
                                title="Contract document"
                                src={`/my-contracts/${reviewing.id}/original?inline=1`}
                                className="h-[62vh] w-full rounded-xl border"
                            />
                        ) : (
                            <div className="rounded-2xl border p-8 text-center">
                                <p className="font-bold">
                                    Preview is unavailable for this file type.
                                </p>
                                <a
                                    href={`/my-contracts/${reviewing.id}/original`}
                                    className="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-bold text-white"
                                >
                                    <Download className="size-4" /> Download to
                                    review
                                </a>
                            </div>
                        )}
                    </div>
                </Modal>
            )}
            {signing && (
                <Modal
                    title="Sign contract electronically"
                    onClose={() => setSigning(null)}
                >
                    <form onSubmit={submitSign} className="space-y-4">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p className="font-bold text-slate-900">
                                {signing.title}
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                Version {signing.version} ·{' '}
                                {signing.original_filename}
                            </p>
                        </div>
                        <label className="block space-y-1.5 text-sm font-semibold text-slate-700">
                            Type your full legal account name
                            <input
                                value={sign.data.signature_name}
                                onChange={(e) =>
                                    sign.setData(
                                        'signature_name',
                                        e.target.value,
                                    )
                                }
                                className="input font-serif text-xl text-red-800 italic"
                                required
                            />
                        </label>
                        <label className="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm leading-6 text-slate-700">
                            <input
                                type="checkbox"
                                checked={sign.data.consent}
                                onChange={(e) =>
                                    sign.setData('consent', e.target.checked)
                                }
                                className="mt-1 size-4"
                            />
                            <span>{consentText}</span>
                        </label>
                        <Errors errors={sign.errors} />
                        <div className="flex justify-end gap-3 border-t pt-4">
                            <button
                                type="button"
                                onClick={() => setSigning(null)}
                                className="h-11 rounded-xl border px-5 font-bold"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={sign.processing || !sign.data.consent}
                                className="inline-flex h-11 items-center gap-2 rounded-xl bg-red-700 px-5 font-bold text-white disabled:opacity-50"
                            >
                                <ShieldCheck className="size-4" />{' '}
                                {sign.processing
                                    ? 'Signing…'
                                    : 'Sign this version'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
            {declining && (
                <Modal
                    title="Decline contract"
                    onClose={() => setDeclining(null)}
                >
                    <form onSubmit={submitDecline} className="space-y-4">
                        <p className="text-sm leading-6 text-slate-600">
                            Explain what needs review. The issued version will
                            remain in the record and cannot be edited.
                        </p>
                        <textarea
                            required
                            minLength={5}
                            rows={5}
                            value={decline.data.reason}
                            onChange={(e) =>
                                decline.setData('reason', e.target.value)
                            }
                            className="input h-auto py-3"
                        />
                        <Errors errors={decline.errors} />
                        <div className="flex justify-end gap-3 border-t pt-4">
                            <button
                                type="button"
                                onClick={() => setDeclining(null)}
                                className="h-11 rounded-xl border px-5 font-bold"
                            >
                                Cancel
                            </button>
                            <button
                                disabled={decline.processing}
                                className="h-11 rounded-xl bg-red-700 px-5 font-bold text-white"
                            >
                                Decline contract
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}
function Status({ value }: { value: string }) {
    const tone =
        value === 'signed'
            ? 'bg-emerald-100 text-emerald-700'
            : value === 'declined'
              ? 'bg-red-100 text-red-700'
              : 'bg-amber-100 text-amber-800';
    return (
        <span className={`rounded-full px-3 py-1 text-xs font-bold ${tone}`}>
            {label(value)}
        </span>
    );
}
function Modal({
    title,
    onClose,
    children,
    wide = false,
}: {
    title: string;
    onClose: () => void;
    children: ReactNode;
    wide?: boolean;
}) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
            onMouseDown={onClose}
        >
            <div
                className={`overflow-hidden rounded-3xl bg-white shadow-2xl ${wide ? 'w-full max-w-5xl' : 'w-full max-w-xl'}`}
                onMouseDown={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b px-6 py-5">
                    <h2 className="text-xl font-bold">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-full p-2 hover:bg-slate-100"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="max-h-[82vh] overflow-y-auto p-6">
                    {children}
                </div>
            </div>
        </div>
    );
}
function Errors({ errors }: { errors: Record<string, string> }) {
    const message = Object.values(errors)[0];
    return message ? (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {message}
        </p>
    ) : null;
}
