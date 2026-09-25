import { Head, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControl,
    FormControlLabel,
    FormLabel,
    MenuItem,
    Radio,
    RadioGroup,
    TextField,
} from '@mui/material';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { panel } from './shared';
import type { Field } from './shared';

type AssignedForm = {
    id: number;
    title: string;
    description: string | null;
    fields: Field[];
    revision: number;
    my_responses_count: number;
};
export default function MyForms({
    forms,
    statusMessage,
}: {
    forms: AssignedForm[];
    statusMessage: string | null;
}) {
    const [selected, setSelected] = useState<AssignedForm | null>(null);
    const response = useForm<{
        request_id: string;
        revision: number;
        answers: Record<string, string | string[]>;
    }>({ request_id: '', revision: 1, answers: {} });
    const open = (form: AssignedForm) => {
        response.setData({
            request_id: crypto.randomUUID(),
            revision: form.revision,
            answers: Object.fromEntries(
                form.fields.map((field) => [
                    field.id,
                    field.type === 'checkbox' ? [] : '',
                ]),
            ),
        });
        response.clearErrors();
        setSelected(form);
    };
    const answer = (id: string, value: string | string[]) =>
        response.setData('answers', { ...response.data.answers, [id]: value });

    return (
        <main className="min-h-full p-4 text-[#17202d] lg:p-6">
            <Head title="My Forms" />
            <div className="mx-auto max-w-6xl space-y-5">
                <header>
                    <h1 className="flex items-center gap-3 text-3xl font-extrabold">
                        <FileText className="text-red-800" />
                        My Forms
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        Complete active forms assigned to your team. Each
                        submission is saved separately.
                    </p>
                </header>
                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    {forms.map((form) => (
                        <section
                            className={`${panel} flex flex-col items-start gap-3`}
                            key={form.id}
                        >
                            <h2 className="text-lg font-bold">{form.title}</h2>
                            <p className="text-sm whitespace-pre-wrap text-slate-500">
                                {form.description ||
                                    'No additional instructions.'}
                            </p>
                            <p className="text-xs text-slate-500">
                                {form.fields.length} fields ·{' '}
                                {form.my_responses_count} response(s) submitted
                                by you
                            </p>
                            <Button
                                variant="contained"
                                onClick={() => open(form)}
                            >
                                Fill out form
                            </Button>
                        </section>
                    ))}
                </div>
                {!forms.length && (
                    <section className={`${panel} text-center text-slate-500`}>
                        No active forms are assigned to your team yet.
                    </section>
                )}
            </div>
            <Dialog
                open={!!selected}
                onClose={() => {
                    if (!response.processing) {
                        setSelected(null);
                    }
                }}
                maxWidth="md"
                fullWidth
                scroll="paper"
            >
                <DialogTitle>{selected?.title}</DialogTitle>
                <DialogContent dividers>
                    <div className="space-y-5">
                        {selected?.description && (
                            <p className="text-sm whitespace-pre-wrap text-slate-500">
                                {selected.description}
                            </p>
                        )}
                        <p className="text-xs text-slate-500">
                            Fields marked * are required.
                        </p>
                        {Object.keys(response.errors).length > 0 && (
                            <Alert severity="error">
                                <ul>
                                    {Object.entries(response.errors).map(
                                        ([key, value]) => (
                                            <li key={key}>{value}</li>
                                        ),
                                    )}
                                </ul>
                            </Alert>
                        )}
                        {selected?.fields.map((field) => {
                            const value = response.data.answers[field.id] ?? '';
                            const choices = Array.isArray(value) ? value : [];

                            if (
                                field.type === 'radio' ||
                                field.type === 'checkbox'
                            ) {
                                return (
                                    <FormControl
                                        key={field.id}
                                        component="fieldset"
                                        required={field.required}
                                        disabled={response.processing}
                                        fullWidth
                                    >
                                        <FormLabel component="legend">
                                            {field.label}
                                        </FormLabel>
                                        {field.type === 'radio' ? (
                                            <RadioGroup
                                                value={value}
                                                onChange={(e) =>
                                                    answer(
                                                        field.id,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {field.options.map((option) => (
                                                    <FormControlLabel
                                                        key={option}
                                                        value={option}
                                                        control={<Radio />}
                                                        label={option}
                                                    />
                                                ))}
                                                {!field.required && (
                                                    <Button
                                                        size="small"
                                                        onClick={() =>
                                                            answer(field.id, '')
                                                        }
                                                    >
                                                        Clear selection
                                                    </Button>
                                                )}
                                            </RadioGroup>
                                        ) : (
                                            <div>
                                                {field.options.map((option) => (
                                                    <FormControlLabel
                                                        key={option}
                                                        label={option}
                                                        control={
                                                            <Checkbox
                                                                checked={choices.includes(
                                                                    option,
                                                                )}
                                                                onChange={(e) =>
                                                                    answer(
                                                                        field.id,
                                                                        e.target
                                                                            .checked
                                                                            ? [
                                                                                  ...choices,
                                                                                  option,
                                                                              ]
                                                                            : choices.filter(
                                                                                  (
                                                                                      item,
                                                                                  ) =>
                                                                                      item !==
                                                                                      option,
                                                                              ),
                                                                    )
                                                                }
                                                            />
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        )}
                                    </FormControl>
                                );
                            }

                            return (
                                <TextField
                                    key={field.id}
                                    label={field.label}
                                    required={field.required}
                                    disabled={response.processing}
                                    fullWidth
                                    value={value}
                                    onChange={(e) =>
                                        answer(field.id, e.target.value)
                                    }
                                    placeholder={field.placeholder || undefined}
                                    type={
                                        ['number', 'email', 'date'].includes(
                                            field.type,
                                        )
                                            ? field.type
                                            : 'text'
                                    }
                                    select={field.type === 'dropdown'}
                                    multiline={field.type === 'textarea'}
                                    minRows={
                                        field.type === 'textarea'
                                            ? 3
                                            : undefined
                                    }
                                    slotProps={{
                                        inputLabel:
                                            field.type === 'date'
                                                ? { shrink: true }
                                                : undefined,
                                        htmlInput:
                                            field.type === 'number'
                                                ? { step: 'any' }
                                                : undefined,
                                    }}
                                >
                                    {field.type === 'dropdown'
                                        ? [
                                              <MenuItem key="empty" value="">
                                                  Select an option
                                              </MenuItem>,
                                              ...field.options.map((option) => (
                                                  <MenuItem
                                                      key={option}
                                                      value={option}
                                                  >
                                                      {option}
                                                  </MenuItem>
                                              )),
                                          ]
                                        : undefined}
                                </TextField>
                            );
                        })}
                    </div>
                </DialogContent>
                <DialogActions sx={{ p: 2 }}>
                    <Button
                        disabled={response.processing}
                        onClick={() => setSelected(null)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="contained"
                        disabled={response.processing}
                        onClick={() => {
                            if (selected) {
                                response.post(
                                    `/my-forms/${selected.id}/responses`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setSelected(null),
                                    },
                                );
                            }
                        }}
                    >
                        {response.processing
                            ? 'Submitting…'
                            : 'Submit response'}
                    </Button>
                </DialogActions>
            </Dialog>
        </main>
    );
}
