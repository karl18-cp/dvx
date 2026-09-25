import { Head, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    TextField,
} from '@mui/material';
import { Megaphone, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';
import '../../css/task-tracker.css';

type Announcement = {
    id: number;
    title: string;
    body: string;
    author_name: string;
    created_at: string;
    updated_at: string;
};
type Props = {
    announcements: {
        data: Announcement[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    canManage: boolean;
    search: string;
    statusMessage?: string;
};
const dateLabel = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Manila',
    }).format(new Date(value));

export default function Announcements({
    announcements,
    canManage,
    search,
    statusMessage,
}: Props) {
    const confirm = useConfirmation();
    const [query, setQuery] = useState(search);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Announcement | null>(null);
    const [viewing, setViewing] = useState<Announcement | null>(null);
    const [deleting, setDeleting] = useState<number | null>(null);
    const form = useForm({ title: '', body: '' });
    const compose = (item: Announcement | null = null) => {
        setEditing(item);
        form.clearErrors();
        form.setData({ title: item?.title ?? '', body: item?.body ?? '' });
        setOpen(true);
    };
    const save = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(`/announcements/${editing.id}`, options);
        } else {
            form.post('/announcements', options);
        }
    };
    const remove = async (item: Announcement) => {
        if (
            !(await confirm(
                `Delete the announcement “${item.title}”? Employees will no longer see it.`,
            ))
        ) {
            return;
        }

        setDeleting(item.id);
        router.delete(`/announcements/${item.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title="Announcements" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-6xl space-y-5">
                    <header className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="mb-2 text-xs font-bold tracking-[0.2em] text-red-800 uppercase">
                                Company updates
                            </p>
                            <h1 className="flex items-center gap-3 text-3xl font-bold text-slate-900">
                                <Megaphone className="text-red-800" />
                                Announcements
                            </h1>
                            <p className="mt-2 text-sm text-slate-500">
                                News, reminders, and updates for everyone at
                                Divertex.
                            </p>
                        </div>
                        {canManage && (
                            <Button
                                variant="contained"
                                startIcon={<Plus size={18} />}
                                onClick={() => compose()}
                            >
                                Post announcement
                            </Button>
                        )}
                    </header>
                    {statusMessage && (
                        <Alert severity="success">{statusMessage}</Alert>
                    )}
                    <form
                        className="task-tracker-fields flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                '/announcements',
                                { search: query },
                                { preserveState: true, preserveScroll: true },
                            );
                        }}
                    >
                        <TextField
                            label="Search announcements"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            sx={{ flex: 1, minWidth: 180 }}
                            slotProps={{ htmlInput: { maxLength: 100 } }}
                        />
                        <Button
                            variant="outlined"
                            type="submit"
                            startIcon={<Search size={17} />}
                        >
                            Search
                        </Button>
                    </form>
                    <div className="space-y-4">
                        {announcements.data.map((item) => (
                            <article
                                key={item.id}
                                className="rounded-2xl border border-t-4 border-slate-200 border-t-red-800 bg-white p-5 shadow-sm sm:p-6"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <h2 className="text-xl font-bold break-words text-slate-900">
                                            {item.title}
                                        </h2>
                                        <p className="mt-2 text-xs text-slate-500">
                                            Posted by {item.author_name} ·{' '}
                                            {dateLabel(item.created_at)}
                                            {item.updated_at !== item.created_at
                                                ? ' · Edited'
                                                : ''}
                                        </p>
                                    </div>
                                    {canManage && (
                                        <div className="flex gap-1">
                                            <Button
                                                size="small"
                                                aria-label={`Edit ${item.title}`}
                                                onClick={() => compose(item)}
                                                startIcon={<Pencil size={15} />}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="small"
                                                color="error"
                                                disabled={deleting !== null}
                                                aria-label={`Delete ${item.title}`}
                                                onClick={() =>
                                                    void remove(item)
                                                }
                                                startIcon={<Trash2 size={15} />}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    )}
                                </div>
                                <p className="mt-4 line-clamp-4 text-sm leading-7 break-words whitespace-pre-wrap text-slate-600">
                                    {item.body}
                                </p>
                                <Button
                                    sx={{ mt: 2 }}
                                    onClick={() => setViewing(item)}
                                    aria-label={`Read ${item.title}`}
                                >
                                    Read announcement
                                </Button>
                            </article>
                        ))}
                        {!announcements.data.length && (
                            <div className="rounded-2xl border border-dashed border-red-200 bg-white px-5 py-14 text-center text-slate-500">
                                {search
                                    ? 'No announcements match your search.'
                                    : 'No announcements yet.'}
                            </div>
                        )}
                    </div>
                    <div className="flex items-center justify-between gap-2">
                        <Button
                            disabled={!announcements.prev_page_url}
                            onClick={() =>
                                announcements.prev_page_url &&
                                router.get(announcements.prev_page_url)
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-xs text-slate-500">
                            {announcements.total} announcements · Page{' '}
                            {announcements.current_page} of{' '}
                            {announcements.last_page}
                        </span>
                        <Button
                            disabled={!announcements.next_page_url}
                            onClick={() =>
                                announcements.next_page_url &&
                                router.get(announcements.next_page_url)
                            }
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </main>
            <Dialog
                open={open}
                onClose={() => {
                    if (!form.processing) {
                        setOpen(false);
                    }
                }}
                fullWidth
                maxWidth="sm"
                scroll="paper"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '90dvh' } },
                }}
            >
                <DialogTitle sx={{ fontWeight: 800 }}>
                    {editing ? 'Edit announcement' : 'Post announcement'}
                </DialogTitle>
                <form
                    onSubmit={save}
                    className="flex min-h-0 flex-col overflow-hidden"
                >
                    <DialogContent
                        className="task-tracker-fields space-y-5"
                        dividers
                    >
                        <Alert severity="info">
                            Published announcements are visible to all
                            employees.
                        </Alert>
                        <TextField
                            autoFocus
                            fullWidth
                            required
                            label="Title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            error={!!form.errors.title}
                            helperText={form.errors.title}
                            slotProps={{ htmlInput: { maxLength: 180 } }}
                        />
                        <TextField
                            fullWidth
                            required
                            multiline
                            minRows={6}
                            maxRows={12}
                            label="Announcement"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            error={!!form.errors.body}
                            helperText={
                                form.errors.body ??
                                'Share the details your team needs to know.'
                            }
                            slotProps={{ htmlInput: { maxLength: 10000 } }}
                        />
                    </DialogContent>
                    <DialogActions sx={{ p: 2 }}>
                        <Button
                            disabled={form.processing}
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="contained"
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.title.trim() ||
                                !form.data.body.trim()
                            }
                        >
                            {form.processing
                                ? 'Saving…'
                                : editing
                                  ? 'Save changes'
                                  : 'Publish announcement'}
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
            <Dialog
                open={!!viewing}
                onClose={() => setViewing(null)}
                fullWidth
                maxWidth="md"
                scroll="paper"
                slotProps={{
                    paper: { sx: { borderRadius: 3, maxHeight: '90dvh' } },
                }}
            >
                <DialogTitle sx={{ fontWeight: 800, overflowWrap: 'anywhere' }}>
                    {viewing?.title}
                </DialogTitle>
                <DialogContent dividers>
                    {viewing && (
                        <>
                            <p className="mb-4 text-xs text-slate-500">
                                Posted by {viewing.author_name} ·{' '}
                                {dateLabel(viewing.created_at)}
                            </p>
                            <p className="text-sm leading-7 break-words whitespace-pre-wrap text-slate-700">
                                {viewing.body}
                            </p>
                        </>
                    )}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setViewing(null)}>Close</Button>
                </DialogActions>
            </Dialog>
        </>
    );
}

Announcements.layout = {
    breadcrumbs: [{ title: 'Announcements', href: '/announcements' }],
};
