import {
    Checkbox,
    FormControlLabel,
    FormHelperText,
    MenuItem,
    TextField,
} from '@mui/material';

export type PublicField = {
    id: string;
    label: string;
    type: string;
    placeholder: string;
    required: boolean;
    visible: boolean;
    locked: boolean;
    custom: boolean;
    options: string[];
};
export type PublicFormDefinition = {
    title: string;
    description: string;
    submit_label: string;
    fields: PublicField[];
};
export type Answer = {
    id: string;
    label: string;
    type: string;
    value: string | number | boolean | null;
};
export type FieldValue = string | boolean | File | null;

export function PublicFormFields({
    fields,
    values,
    customValues = {},
    errors = {},
    onChange,
    columns = 1,
}: {
    fields: PublicField[];
    values: Record<string, unknown>;
    customValues?: Record<string, unknown>;
    errors?: Record<string, string>;
    onChange: (id: string, value: FieldValue, custom: boolean) => void;
    columns?: 1 | 2;
}) {
    return (
        <div
            className={`grid min-w-0 gap-6 ${columns === 2 ? 'sm:grid-cols-2' : ''}`}
        >
            {fields
                .filter((field) => field.visible)
                .map((field) => {
                    const value = (field.custom ? customValues : values)[
                        field.id
                    ];
                    const error =
                        errors[
                            field.custom
                                ? `custom_fields.${field.id}`
                                : field.id
                        ];
                    const zones =
                        field.type === 'timezone'
                            ? [
                                  ...new Set([
                                      String(value || 'Asia/Manila'),
                                      'UTC',
                                      ...Intl.supportedValuesOf('timeZone'),
                                  ]),
                              ].sort()
                            : [];
                    const select = ['select', 'timezone'].includes(field.type);
                    const wide =
                        columns === 2 &&
                        ['textarea', 'checkbox'].includes(field.type);

                    return (
                        <div
                            key={field.id}
                            className={`min-w-0 ${wide ? 'sm:col-span-2' : ''}`}
                        >
                            {field.type === 'checkbox' ? (
                                <>
                                    <FormControlLabel
                                        control={
                                            <Checkbox
                                                required={field.required}
                                                checked={!!value}
                                                onChange={(e) =>
                                                    onChange(
                                                        field.id,
                                                        e.target.checked,
                                                        field.custom,
                                                    )
                                                }
                                            />
                                        }
                                        label={
                                            field.label +
                                            (field.required ? ' *' : '')
                                        }
                                    />
                                    {error && (
                                        <FormHelperText error>
                                            {error}
                                        </FormHelperText>
                                    )}
                                </>
                            ) : (
                                <TextField
                                    fullWidth
                                    size="small"
                                    label={field.label}
                                    placeholder={field.placeholder}
                                    required={field.required}
                                    error={!!error}
                                    helperText={
                                        error ||
                                        (field.type === 'timezone'
                                            ? 'The meeting date and time use this timezone.'
                                            : undefined)
                                    }
                                    select={select}
                                    multiline={field.type === 'textarea'}
                                    minRows={
                                        field.type === 'textarea'
                                            ? 3
                                            : undefined
                                    }
                                    type={
                                        select || field.type === 'textarea'
                                            ? 'text'
                                            : field.type
                                    }
                                    value={
                                        field.type === 'file'
                                            ? undefined
                                            : (value ?? '')
                                    }
                                    onChange={(e) =>
                                        onChange(
                                            field.id,
                                            field.type === 'file'
                                                ? (e.target as HTMLInputElement)
                                                      .files?.[0] || null
                                                : e.target.value,
                                            field.custom,
                                        )
                                    }
                                    slotProps={{
                                        inputLabel: [
                                            'file',
                                            'date',
                                            'datetime-local',
                                            'timezone',
                                        ].includes(field.type)
                                            ? { shrink: true }
                                            : undefined,
                                        htmlInput:
                                            field.type === 'file'
                                                ? { accept: '.pdf,.doc,.docx' }
                                                : field.id ===
                                                    'years_experience'
                                                  ? { min: 0, max: 50 }
                                                  : undefined,
                                        select: {
                                            MenuProps: {
                                                slotProps: {
                                                    paper: {
                                                        sx: {
                                                            maxHeight: 320,
                                                            mt: 0.5,
                                                            borderRadius:
                                                                '12px',
                                                            '& .MuiMenuItem-root':
                                                                {
                                                                    whiteSpace:
                                                                        'normal',
                                                                    fontSize:
                                                                        '0.875rem',
                                                                    '&.Mui-selected':
                                                                        {
                                                                            bgcolor:
                                                                                '#fff1f2',
                                                                            color: '#a71924',
                                                                        },
                                                                },
                                                        },
                                                    },
                                                },
                                            },
                                        },
                                    }}
                                    sx={{
                                        '& .MuiOutlinedInput-root': {
                                            bgcolor: 'white',
                                            borderRadius: '10px',
                                        },
                                        '& .MuiInputBase-input': {
                                            fontSize: '0.875rem',
                                        },
                                        '& .MuiInputLabel-root': {
                                            fontSize: '0.875rem',
                                        },
                                    }}
                                >
                                    {select
                                        ? (field.type === 'timezone'
                                              ? zones
                                              : field.options
                                          ).map((option) => (
                                              <MenuItem
                                                  key={option}
                                                  value={option}
                                              >
                                                  {option}
                                              </MenuItem>
                                          ))
                                        : undefined}
                                </TextField>
                            )}
                        </div>
                    );
                })}
        </div>
    );
}

export function CustomAnswers({ answers }: { answers?: Answer[] | null }) {
    if (!answers?.length) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 className="font-semibold">Additional responses</h3>
            {answers.map((answer) => (
                <div key={answer.id}>
                    <p className="text-sm font-semibold">{answer.label}</p>
                    <p className="text-sm break-words whitespace-pre-wrap text-slate-600">
                        {typeof answer.value === 'boolean'
                            ? answer.value
                                ? 'Yes'
                                : 'No'
                            : (answer.value ?? 'Not provided')}
                    </p>
                </div>
            ))}
        </section>
    );
}
