import { Head, router, useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    IconButton,
    Switch,
    TextField,
} from '@mui/material';
import {
    Check,
    EyeOff,
    MessageCircle,
    Megaphone,
    Pencil,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useConfirmation } from '@/hooks/use-confirmation';

type Update = {
    id: number;
    title: string;
    body: string;
    author_name: string;
    is_published: boolean;
    published_at: string | null;
    comments_count: number;
    pending_comments_count: number;
    approved_comments_count: number;
    created_at: string;
};
type Comment = {
    id: number;
    name: string;
    email: string | null;
    body: string;
    created_at: string;
    public_update: { id: number; title: string };
};
type Props = {
    updates: {
        data: Update[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    pendingComments: {
        data: Comment[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    statusMessage?: string;
};

export default function PublicUpdates({
    updates,
    pendingComments,
    statusMessage,
}: Props) {
    const confirm = useConfirmation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Update | null>(null);
    const form = useForm({ title: '', body: '', is_published: true });
    const compose = (item: Update | null = null) => {
        setEditing(item);
        form.clearErrors();
        form.setData({
            title: item?.title ?? '',
            body: item?.body ?? '',
            is_published: item?.is_published ?? true,
        });
        setOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(`/public-updates/manage/${editing.id}`, options);
        } else {
            form.post('/public-updates/manage', options);
        }
    };
    const remove = async (item: Update) => {
        if (
            !(await confirm(
                `Delete “${item.title}” and all of its public comments?`,
            ))
        ) {
            return;
        }

        router.delete(`/public-updates/manage/${item.id}`, {
            preserveScroll: true,
        });
    };
    const moderate = (comment: Comment, status: 'approved' | 'hidden') =>
        router.patch(
            `/public-updates/comments/${comment.id}`,
            { status },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Public Page Updates" />
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-bold tracking-[.2em] text-red-800 uppercase">
                                Landing page community
                            </p>
                            <h1 className="mt-2 flex items-center gap-3 text-3xl font-bold">
                                <Megaphone className="text-red-800" />
                                Public updates & comments
                            </h1>
                            <p className="mt-2 text-sm text-slate-500">
                                Publish landing-page updates and review visitor
                                comments before they become public.
                            </p>
                        </div>
                        <Button
                            variant="contained"
                            startIcon={<Plus size={17} />}
                            onClick={() => compose()}
                        >
                            New public update
                        </Button>
                    </header>
                    {statusMessage && (
                        <Alert severity="success">{statusMessage}</Alert>
                    )}
                    <div className="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-xl font-bold">
                                    Landing-page posts
                                </h2>
                                <Chip label={`${updates.total} posts`} />
                            </div>
                            {updates.data.map((item) => (
                                <article
                                    key={item.id}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="text-lg font-bold">
                                                    {item.title}
                                                </h3>
                                                <Chip
                                                    size="small"
                                                    color={
                                                        item.is_published
                                                            ? 'success'
                                                            : 'default'
                                                    }
                                                    label={
                                                        item.is_published
                                                            ? 'Live'
                                                            : 'Draft'
                                                    }
                                                />
                                            </div>
                                            <p className="mt-1 text-xs text-slate-500">
                                                By {item.author_name} ·{' '}
                                                {item.comments_count} comments ·{' '}
                                                {item.pending_comments_count}{' '}
                                                pending
                                            </p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                size="small"
                                                startIcon={<Pencil size={15} />}
                                                onClick={() => compose(item)}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="small"
                                                color="error"
                                                startIcon={<Trash2 size={15} />}
                                                onClick={() =>
                                                    void remove(item)
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                    <p className="mt-4 line-clamp-4 text-sm leading-7 whitespace-pre-wrap text-slate-600">
                                        {item.body}
                                    </p>
                                </article>
                            ))}
                            {!updates.data.length && (
                                <div className="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                                    No public updates yet.
                                </div>
                            )}
                            <Pager
                                page={updates.current_page}
                                last={updates.last_page}
                                previous={updates.prev_page_url}
                                next={updates.next_page_url}
                            />
                        </section>
                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h2 className="flex items-center gap-2 text-xl font-bold">
                                    <MessageCircle
                                        size={20}
                                        className="text-red-700"
                                    />
                                    Comments to review
                                </h2>
                                <Chip
                                    color={
                                        pendingComments.total
                                            ? 'warning'
                                            : 'default'
                                    }
                                    label={pendingComments.total}
                                />
                            </div>
                            {pendingComments.data.map((comment) => (
                                <article
                                    key={comment.id}
                                    className="rounded-2xl border border-amber-200 bg-amber-50/40 p-4"
                                >
                                    <p className="text-xs font-bold text-red-800">
                                        {comment.public_update.title}
                                    </p>
                                    <p className="mt-2 font-bold">
                                        {comment.name}
                                    </p>
                                    {comment.email && (
                                        <p className="text-xs text-slate-500">
                                            {comment.email}
                                        </p>
                                    )}
                                    <p className="mt-3 text-sm leading-6 break-words whitespace-pre-wrap text-slate-700">
                                        {comment.body}
                                    </p>
                                    <div className="mt-4 flex justify-end gap-2">
                                        <Button
                                            size="small"
                                            color="inherit"
                                            startIcon={<EyeOff size={15} />}
                                            onClick={() =>
                                                moderate(comment, 'hidden')
                                            }
                                        >
                                            Hide
                                        </Button>
                                        <Button
                                            size="small"
                                            variant="contained"
                                            color="success"
                                            startIcon={<Check size={15} />}
                                            onClick={() =>
                                                moderate(comment, 'approved')
                                            }
                                        >
                                            Approve
                                        </Button>
                                    </div>
                                </article>
                            ))}
                            {!pendingComments.data.length && (
                                <div className="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                                    No comments are waiting for review.
                                </div>
                            )}
                            <Pager
                                page={pendingComments.current_page}
                                last={pendingComments.last_page}
                                previous={pendingComments.prev_page_url}
                                next={pendingComments.next_page_url}
                            />
                        </section>
                    </div>
                </div>
            </main>
            <Dialog
                open={open}
                onClose={() => !form.processing && setOpen(false)}
                fullWidth
                maxWidth="sm"
                slotProps={{
                    paper: { sx: { borderRadius: 4, maxHeight: '90dvh' } },
                }}
            >
                <DialogTitle className="flex items-center justify-between gap-3">
                    {editing ? 'Edit public update' : 'New public update'}
                    <IconButton
                        aria-label="Close public update editor"
                        onClick={() => setOpen(false)}
                        disabled={form.processing}
                    >
                        <X />
                    </IconButton>
                </DialogTitle>
                <form onSubmit={submit}>
                    <DialogContent dividers className="space-y-4">
                        <Alert severity="info">
                            Live posts appear on the public landing page. Drafts
                            remain visible only here.
                        </Alert>
                        <TextField
                            autoFocus
                            fullWidth
                            required
                            label="Post title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            error={Boolean(form.errors.title)}
                            helperText={form.errors.title}
                        />
                        <TextField
                            fullWidth
                            required
                            multiline
                            minRows={7}
                            label="Update"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            error={Boolean(form.errors.body)}
                            helperText={form.errors.body}
                        />
                        <FormControlLabel
                            control={
                                <Switch
                                    checked={form.data.is_published}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_published',
                                            event.target.checked,
                                        )
                                    }
                                />
                            }
                            label="Publish on the landing page"
                        />
                    </DialogContent>
                    <DialogActions sx={{ p: 2 }}>
                        <Button
                            onClick={() => setOpen(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Saving…' : 'Save update'}
                        </Button>
                    </DialogActions>
                </form>
            </Dialog>
        </>
    );
}

function Pager({
    page,
    last,
    previous,
    next,
}: {
    page: number;
    last: number;
    previous: string | null;
    next: string | null;
}) {
    if (last <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-2 text-xs text-slate-500">
            <Button
                size="small"
                disabled={!previous}
                onClick={() => previous && router.get(previous)}
            >
                Previous
            </Button>
            <span>
                Page {page} of {last}
            </span>
            <Button
                size="small"
                disabled={!next}
                onClick={() => next && router.get(next)}
            >
                Next
            </Button>
        </div>
    );
}

PublicUpdates.layout = {
    breadcrumbs: [
        { title: 'Public updates & comments', href: '/public-updates/manage' },
    ],
};
