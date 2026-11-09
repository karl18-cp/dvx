import { Head, router, useForm } from '@inertiajs/react';
import { Alert, Button, Chip, FormControlLabel, Switch } from '@mui/material';
import { RotateCcw, Save, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

type Definition = {
    group: string;
    label: string;
    description: string;
    default_roles: string[];
};

type Props = {
    roles: Record<string, string>;
    definitions: Record<string, Definition>;
    matrix: Record<string, Record<string, boolean>>;
    statusMessage?: string;
};

export default function RolePermissions({
    roles,
    definitions,
    matrix,
    statusMessage,
}: Props) {
    const [role, setRole] = useState(Object.keys(roles)[0]);
    const form = useForm<{ permissions: Record<string, boolean> }>({
        permissions: matrix[role],
    });
    const groups = useMemo(
        () =>
            Object.entries(definitions).reduce<
                Record<string, [string, Definition][]>
            >((result, entry) => {
                (result[entry[1].group] ||= []).push(entry);
                return result;
            }, {}),
        [definitions],
    );

    const selectRole = (nextRole: string) => {
        setRole(nextRole);
        form.setData('permissions', matrix[nextRole]);
        form.clearErrors();
    };
    const changed = Object.keys(definitions).some(
        (permission) =>
            Boolean(form.data.permissions[permission]) !==
            Boolean(matrix[role][permission]),
    );

    return (
        <main className="min-h-screen bg-[linear-gradient(135deg,#fff7f7_0%,#f7f8fb_55%,#fff_100%)] p-5 md:p-8">
            <Head title="Role & Permission Manager" />
            <div className="mx-auto grid max-w-7xl gap-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.22em] text-red-700 uppercase">
                            Access control
                        </p>
                        <div className="mt-2 flex items-center gap-3">
                            <ShieldCheck className="text-red-700" />
                            <h1 className="text-3xl font-bold">
                                Role & Permission Manager
                            </h1>
                        </div>
                        <p className="mt-2 max-w-3xl text-slate-600">
                            Configure what each role may do. Team ownership and
                            record-level scope rules still apply after a
                            permission is granted.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outlined"
                            color="warning"
                            startIcon={<RotateCcw size={17} />}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        `Restore ${roles[role]} to the system defaults?`,
                                    )
                                ) {
                                    router.delete(`/role-permissions/${role}`, {
                                        preserveScroll: true,
                                    });
                                }
                            }}
                        >
                            Restore defaults
                        </Button>
                        <Button
                            variant="contained"
                            startIcon={<Save size={17} />}
                            disabled={form.processing || !changed}
                            onClick={() =>
                                form.put(`/role-permissions/${role}`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Save permissions
                        </Button>
                    </div>
                </header>

                {statusMessage && (
                    <Alert severity="success">{statusMessage}</Alert>
                )}
                {Object.values(form.errors).map((message, index) => (
                    <Alert severity="error" key={index}>
                        {String(message)}
                    </Alert>
                ))}
                <Alert severity="info">
                    Access to this permission manager always remains restricted
                    to active administrators, preventing accidental lockout.
                </Alert>

                <div className="grid gap-5 lg:grid-cols-[260px_1fr]">
                    <aside className="grid content-start gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        {Object.entries(roles).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => selectRole(value)}
                                className={`flex items-center justify-between rounded-xl px-4 py-3 text-left transition ${
                                    value === role
                                        ? 'bg-red-50 font-bold text-red-800 ring-1 ring-red-200'
                                        : 'hover:bg-slate-50'
                                }`}
                            >
                                {label}
                                <Chip
                                    size="small"
                                    label={
                                        Object.values(matrix[value]).filter(
                                            Boolean,
                                        ).length
                                    }
                                />
                            </button>
                        ))}
                    </aside>

                    <section className="grid gap-5">
                        {Object.entries(groups).map(([group, permissions]) => (
                            <div
                                key={group}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                            >
                                <div className="border-b border-slate-200 bg-slate-50 px-5 py-3">
                                    <h2 className="font-bold">{group}</h2>
                                </div>
                                <div className="divide-y divide-slate-100">
                                    {permissions.map(
                                        ([permission, definition]) => (
                                            <div
                                                key={permission}
                                                className="grid gap-3 p-5 sm:grid-cols-[1fr_auto] sm:items-center"
                                            >
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h3 className="font-bold">
                                                            {definition.label}
                                                        </h3>
                                                        {definition.default_roles.includes(
                                                            role,
                                                        ) && (
                                                            <Chip
                                                                size="small"
                                                                variant="outlined"
                                                                label="Default"
                                                            />
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-sm text-slate-600">
                                                        {definition.description}
                                                    </p>
                                                    <code className="mt-2 inline-block rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                                        {permission}
                                                    </code>
                                                </div>
                                                <FormControlLabel
                                                    label={
                                                        form.data.permissions[
                                                            permission
                                                        ]
                                                            ? 'Allowed'
                                                            : 'Denied'
                                                    }
                                                    control={
                                                        <Switch
                                                            checked={Boolean(
                                                                form.data
                                                                    .permissions[
                                                                    permission
                                                                ],
                                                            )}
                                                            onChange={(event) =>
                                                                form.setData(
                                                                    'permissions',
                                                                    {
                                                                        ...form
                                                                            .data
                                                                            .permissions,
                                                                        [permission]:
                                                                            event
                                                                                .target
                                                                                .checked,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    }
                                                />
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        ))}
                    </section>
                </div>
            </div>
        </main>
    );
}
