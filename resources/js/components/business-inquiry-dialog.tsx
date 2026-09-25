import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    Stack,
} from '@mui/material';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    CheckCircle2,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { PublicFormFields } from '@/components/public-form-fields';
import type {
    PublicFormDefinition,
    FieldValue,
} from '@/components/public-form-fields';

export default function BusinessInquiryDialog({
    onClose,
    definition,
}: {
    onClose: () => void;
    definition: PublicFormDefinition;
}) {
    const [submitted, setSubmitted] = useState(false);
    const form = useForm({
        submission_id: crypto.randomUUID(),
        name: '',
        company: '',
        email: '',
        phone: '',
        service: '',
        message: '',
        meeting_time: '',
        timezone:
            Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Manila',
        website: '',
        custom_fields: {} as Record<string, string | boolean>,
    });
    const changeField = (id: string, value: FieldValue, custom: boolean) => {
        if (custom) {
            form.setData('custom_fields', {
                ...form.data.custom_fields,
                [id]: value as string | boolean,
            });
        } else {
            form.setData({ ...form.data, [id]: value });
        }
    };
    const contactIds = ['name', 'company', 'email', 'phone'];
    const meetingIds = ['service', 'meeting_time', 'timezone'];

    return (
        <Dialog
            open
            fullWidth
            maxWidth="md"
            onClose={() => !form.processing && onClose()}
            aria-labelledby="business-inquiry-title"
            slotProps={{
                paper: {
                    sx: {
                        m: 2,
                        width: 'calc(100% - 32px)',
                        maxHeight: 'calc(100dvh - 32px)',
                        borderRadius: 4,
                    },
                },
            }}
        >
            <DialogTitle
                sx={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'flex-start',
                    px: { xs: 2.5, sm: 4 },
                    py: 3,
                    color: 'white',
                    background:
                        'linear-gradient(120deg, #191321 0%, #55191f 65%, #9f2028 100%)',
                }}
            >
                <div>
                    <p className="mb-2 text-[10px] font-bold tracking-[.24em] text-red-200 uppercase">
                        Divertex · Business partnerships
                    </p>
                    <span
                        id="business-inquiry-title"
                        className="block text-2xl font-bold tracking-tight"
                    >
                        {definition.title}
                    </span>
                    <p className="mt-2 text-sm font-normal text-white/75">
                        {definition.description}
                    </p>
                </div>
                <IconButton
                    aria-label="Close business inquiry"
                    disabled={form.processing}
                    onClick={onClose}
                    sx={{
                        color: 'white',
                        bgcolor: 'rgba(255,255,255,.1)',
                        '&:hover': { bgcolor: 'rgba(255,255,255,.2)' },
                    }}
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
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/business-inquiries', {
                        preserveScroll: true,
                        onSuccess: () => setSubmitted(true),
                    });
                }}
            >
                <DialogContent
                    dividers
                    sx={{
                        px: { xs: 2, sm: 4 },
                        py: 3,
                        background: '#fcfafb',
                        '& .MuiOutlinedInput-root': {
                            bgcolor: 'white',
                            borderRadius: '10px',
                        },
                        '& .MuiInputBase-input': { fontSize: '0.875rem' },
                        '& .MuiInputLabel-root': { fontSize: '0.875rem' },
                    }}
                >
                    {submitted ? (
                        <Alert
                            severity="success"
                            icon={<CheckCircle2 />}
                            sx={{ py: 3, borderRadius: 3 }}
                        >
                            Your inquiry and preferred meeting time have been
                            received. Our team will contact you to confirm
                            availability and meeting details.
                        </Alert>
                    ) : (
                        <Stack spacing={3} useFlexGap>
                            <div className="grid gap-6 md:grid-cols-2">
                                <section
                                    className="min-w-0 rounded-2xl border border-slate-200/80 bg-white p-5"
                                    aria-labelledby="inquiry-contact-heading"
                                >
                                    <div className="mb-6 flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-xl bg-red-50 text-red-700">
                                            <Building2 size={18} />
                                        </span>
                                        <div>
                                            <h3
                                                id="inquiry-contact-heading"
                                                className="text-sm font-bold text-slate-900"
                                            >
                                                Your contact details
                                            </h3>
                                            <p className="mt-0.5 text-xs text-slate-500">
                                                How our team can reach you
                                            </p>
                                        </div>
                                    </div>
                                    <Stack spacing={2.5} useFlexGap>
                                        <PublicFormFields
                                            fields={definition.fields.filter(
                                                (field) =>
                                                    contactIds.includes(
                                                        field.id,
                                                    ),
                                            )}
                                            values={form.data}
                                            errors={form.errors}
                                            onChange={changeField}
                                        />
                                    </Stack>
                                </section>
                                <section
                                    className="min-w-0 rounded-2xl border border-red-100 bg-[#fff6f6] p-5"
                                    aria-labelledby="inquiry-meeting-heading"
                                >
                                    <div className="mb-6 flex items-center gap-3">
                                        <span className="flex size-9 items-center justify-center rounded-xl bg-white text-red-700">
                                            <CalendarDays size={18} />
                                        </span>
                                        <div>
                                            <h3
                                                id="inquiry-meeting-heading"
                                                className="text-sm font-bold text-slate-900"
                                            >
                                                Plan our conversation
                                            </h3>
                                            <p className="mt-0.5 text-xs text-slate-500">
                                                Choose your service and
                                                preferred time
                                            </p>
                                        </div>
                                    </div>
                                    <Stack spacing={2.5} useFlexGap>
                                        <PublicFormFields
                                            fields={definition.fields.filter(
                                                (field) =>
                                                    meetingIds.includes(
                                                        field.id,
                                                    ),
                                            )}
                                            values={form.data}
                                            errors={form.errors}
                                            onChange={changeField}
                                        />
                                        <p className="text-xs leading-5 text-slate-600">
                                            We’ll contact you to confirm
                                            availability and meeting details.
                                        </p>
                                    </Stack>
                                </section>
                            </div>
                            <PublicFormFields
                                fields={definition.fields.filter(
                                    (field) =>
                                        field.id === 'message' || field.custom,
                                )}
                                values={form.data}
                                customValues={form.data.custom_fields}
                                errors={form.errors}
                                onChange={changeField}
                            />
                            <div hidden aria-hidden="true">
                                <input
                                    name="website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    value={form.data.website}
                                    onChange={(e) =>
                                        form.setData('website', e.target.value)
                                    }
                                />
                            </div>
                            {form.errors.submission_id && (
                                <Alert severity="error">
                                    Please reopen the form and try again.
                                </Alert>
                            )}
                        </Stack>
                    )}
                </DialogContent>
                {!submitted && (
                    <DialogActions
                        sx={{
                            px: { xs: 2, sm: 4 },
                            py: 2.5,
                            flexShrink: 0,
                            justifyContent: 'space-between',
                            gap: 2,
                            flexWrap: 'wrap',
                        }}
                    >
                        <p className="max-w-[240px] text-xs leading-5 text-slate-500">
                            A conversation comes first.
                            <br />
                            Your preferred time is a request, not a confirmed
                            booking.
                        </p>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={form.processing}
                            endIcon={<ArrowRight size={17} />}
                            sx={{
                                px: 3,
                                py: 1.25,
                                borderRadius: 2.5,
                                width: { xs: '100%', sm: 'auto' },
                                boxShadow: '0 4px 12px rgba(168, 25, 35, .16)',
                            }}
                        >
                            {form.processing
                                ? 'Sending…'
                                : definition.submit_label}
                        </Button>
                    </DialogActions>
                )}
            </form>
        </Dialog>
    );
}
