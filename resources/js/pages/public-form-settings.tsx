import { Head, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    MenuItem,
    Tab,
    Tabs,
    TextField,
} from '@mui/material';
import { Eye, Plus, Trash2, X, LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import { PublicFormFields } from '@/components/public-form-fields';
import type {
    PublicFormDefinition,
    PublicField,
    FieldValue,
} from '@/components/public-form-fields';

export default function PublicFormSettings({
    definitions,
    statusMessage,
}: {
    definitions: Record<'application' | 'business', PublicFormDefinition>;
    statusMessage?: string;
}) {
    const [tab, setTab] = useState('application');

    return (
        <main className="p-5 text-slate-900 lg:p-8">
            <Head title="Form Settings" />
            <div className="mx-auto max-w-6xl space-y-6">
                <header>
                    <p className="text-xs font-bold tracking-widest text-red-700 uppercase">
                        Public forms
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">Form Settings</h1>
                    <p className="mt-2 text-slate-500">
                        Customize applications and business inquiries without
                        changing their existing records.
                    </p>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                <Tabs value={tab} onChange={(_, value) => setTab(value)}>
                    <Tab value="application" label="Application form" />
                    <Tab value="business" label="Business inquiry" />
                </Tabs>
                {(['application', 'business'] as const).map((kind) => (
                    <div key={kind} hidden={tab !== kind}>
                        <Editor kind={kind} definition={definitions[kind]} />
                    </div>
                ))}
            </div>
        </main>
    );
}

function Editor({
    kind,
    definition,
}: {
    kind: string;
    definition: PublicFormDefinition;
}) {
    const form = useForm<PublicFormDefinition>(definition);
    const [preview, setPreview] = useState(false);
    const [previewValues, setPreviewValues] = useState<
        Record<string, FieldValue>
    >({});
    const change = (index: number, patch: Partial<PublicField>) =>
        form.setData(
            'fields',
            form.data.fields.map((field, i) =>
                i === index ? { ...field, ...patch } : field,
            ),
        );
    const errors = form.errors as Record<string, string>;

    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/public-form-settings/${kind}`, {
                    preserveScroll: true,
                });
            }}
        >
            <Alert severity="info">
                Core contact and workflow fields stay visible and required. You
                can rename them, edit dropdown choices, hide optional fields,
                and add extra questions. Changes apply when you save.
            </Alert>
            <section className="grid gap-6 rounded-2xl border bg-white p-6 sm:grid-cols-2">
                <TextField
                    size="small"
                    label="Form title"
                    required
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    error={!!errors.title}
                    helperText={errors.title}
                />
                <TextField
                    size="small"
                    label="Submit button text"
                    required
                    value={form.data.submit_label}
                    onChange={(e) =>
                        form.setData('submit_label', e.target.value)
                    }
                    error={!!errors.submit_label}
                    helperText={errors.submit_label}
                />
                <TextField
                    className="sm:col-span-2"
                    size="small"
                    multiline
                    minRows={2}
                    label="Introduction"
                    required
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    error={!!errors.description}
                    helperText={errors.description}
                />
            </section>
            {form.data.fields.map((field, index) => (
                <section
                    key={field.id}
                    className={`space-y-5 rounded-2xl border bg-white p-5 ${field.visible ? 'border-slate-200' : 'border-dashed border-slate-300'}`}
                >
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="flex items-center gap-2 font-bold">
                            {index + 1}. {field.label || 'Untitled question'}
                            {field.locked && (
                                <LockKeyhole
                                    size={15}
                                    className="text-slate-400"
                                />
                            )}
                        </h2>
                        {field.custom && (
                            <IconButton
                                aria-label={`Remove ${field.label || 'question'}`}
                                onClick={() =>
                                    form.setData(
                                        'fields',
                                        form.data.fields.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 size={18} />
                            </IconButton>
                        )}
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <TextField
                            size="small"
                            label="Field label"
                            required
                            value={field.label}
                            onChange={(e) =>
                                change(index, { label: e.target.value })
                            }
                            error={!!errors[`fields.${index}.label`]}
                            helperText={errors[`fields.${index}.label`]}
                        />
                        <TextField
                            size="small"
                            select
                            label="Field type"
                            disabled={!field.custom}
                            value={field.type}
                            onChange={(e) =>
                                change(index, { type: e.target.value })
                            }
                        >
                            {[
                                ...new Set([
                                    'text',
                                    'textarea',
                                    'select',
                                    'email',
                                    'number',
                                    'date',
                                    'checkbox',
                                    field.type,
                                ]),
                            ].map((type) => (
                                <MenuItem key={type} value={type}>
                                    {type
                                        .replace('textarea', 'Long text')
                                        .replace('select', 'Dropdown')}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            size="small"
                            label="Placeholder (optional)"
                            value={field.placeholder}
                            onChange={(e) =>
                                change(index, { placeholder: e.target.value })
                            }
                        />
                        <div className="flex flex-wrap">
                            <FormControlLabel
                                label="Visible"
                                control={
                                    <Checkbox
                                        disabled={field.locked}
                                        checked={field.visible}
                                        onChange={(e) =>
                                            change(index, {
                                                visible: e.target.checked,
                                            })
                                        }
                                    />
                                }
                            />
                            <FormControlLabel
                                label="Required"
                                control={
                                    <Checkbox
                                        disabled={field.locked}
                                        checked={field.required}
                                        onChange={(e) =>
                                            change(index, {
                                                required: e.target.checked,
                                            })
                                        }
                                    />
                                }
                            />
                        </div>
                        {field.type === 'select' && (
                            <Options
                                key={field.id}
                                options={field.options}
                                onChange={(options) =>
                                    change(index, { options })
                                }
                                error={errors[`fields.${index}.options`]}
                            />
                        )}
                    </div>
                </section>
            ))}
            {Object.keys(errors).length > 0 && (
                <Alert severity="error">
                    {Object.values(errors).map((error, i) => (
                        <p key={i}>{error}</p>
                    ))}
                </Alert>
            )}
            <div className="sticky bottom-0 flex flex-wrap justify-between gap-3 rounded-2xl border bg-white/95 p-4 shadow-sm backdrop-blur">
                <Button
                    startIcon={<Plus size={17} />}
                    disabled={form.data.fields.length >= 30}
                    onClick={() =>
                        form.setData('fields', [
                            ...form.data.fields,
                            {
                                id: `custom_${crypto.randomUUID().replaceAll('-', '')}`,
                                label: '',
                                placeholder: '',
                                type: 'text',
                                required: false,
                                visible: true,
                                custom: true,
                                locked: false,
                                options: [],
                            },
                        ])
                    }
                >
                    Add question
                </Button>
                <div className="flex gap-3">
                    <Button
                        startIcon={<Eye size={17} />}
                        onClick={() => {
                            setPreviewValues({});
                            setPreview(true);
                        }}
                    >
                        Preview
                    </Button>
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Saving…' : 'Save settings'}
                    </Button>
                </div>
            </div>
            <Dialog
                open={preview}
                onClose={() => setPreview(false)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle
                    sx={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    Form preview
                    <IconButton
                        aria-label="Close preview"
                        onClick={() => setPreview(false)}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers sx={{ py: 3 }}>
                    <h2 className="text-xl font-bold">{form.data.title}</h2>
                    <p className="mt-2 mb-6 text-sm text-slate-500">
                        {form.data.description}
                    </p>
                    <PublicFormFields
                        fields={form.data.fields}
                        values={previewValues}
                        customValues={previewValues}
                        onChange={(id, value) =>
                            setPreviewValues({ ...previewValues, [id]: value })
                        }
                    />
                    <Button
                        disabled
                        fullWidth
                        variant="contained"
                        sx={{ mt: 3 }}
                    >
                        {form.data.submit_label}
                    </Button>
                </DialogContent>
            </Dialog>
        </form>
    );
}
function Options({
    options,
    onChange,
    error,
}: {
    options: string[];
    onChange: (value: string[]) => void;
    error?: string;
}) {
    const [text, setText] = useState(options.join('\n'));

    return (
        <TextField
            className="sm:col-span-2"
            label="Dropdown options — one per line"
            multiline
            minRows={3}
            value={text}
            onChange={(e) => {
                setText(e.target.value);
                onChange(
                    e.target.value
                        .split('\n')
                        .map((value) => value.trim())
                        .filter(Boolean),
                );
            }}
            error={!!error}
            helperText={
                error ||
                'Removing an option does not change existing submissions.'
            }
        />
    );
}
PublicFormSettings.layout = {
    breadcrumbs: [{ title: 'Form Settings', href: '/public-form-settings' }],
};
