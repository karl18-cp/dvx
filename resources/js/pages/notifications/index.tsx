import { Head, router } from '@inertiajs/react';

type Item = {
    id: number;
    title: string;
    message: string;
    action_url?: string;
    read_at?: string;
    created_at: string;
};
type PageData = {
    data: Item[];
    prev_page_url?: string;
    next_page_url?: string;
};

export default function Notifications({
    notifications,
}: {
    notifications: PageData;
}) {
    return (
        <main className="min-h-full bg-[#f7f7fa] p-6">
            <Head title="Notifications" />
            <div className="mx-auto max-w-4xl">
                <h1 className="mb-5 text-2xl font-bold">Notifications</h1>
                <div className="space-y-3">
                    {notifications.data.map((item) => (
                        <button
                            key={item.id}
                            onClick={() =>
                                router.patch(`/notifications/${item.id}/read`)
                            }
                            className={`block w-full rounded-2xl border bg-white p-4 text-left ${item.read_at ? '' : 'border-[#b72822]'}`}
                        >
                            <strong>{item.title}</strong>
                            <p className="mt-1 text-sm text-[#676a79]">
                                {item.message}
                            </p>
                            <small>
                                {new Date(item.created_at).toLocaleString(
                                    'en-US',
                                    { timeZone: 'Asia/Manila' },
                                )}
                            </small>
                        </button>
                    ))}
                    {!notifications.data.length && (
                        <div className="rounded-2xl border bg-white p-10 text-center text-[#77798a]">
                            No new notifications.
                        </div>
                    )}
                </div>
                <div className="mt-4 flex justify-between">
                    <button
                        disabled={!notifications.prev_page_url}
                        onClick={() =>
                            notifications.prev_page_url &&
                            router.get(notifications.prev_page_url)
                        }
                    >
                        Previous
                    </button>
                    <button
                        disabled={!notifications.next_page_url}
                        onClick={() =>
                            notifications.next_page_url &&
                            router.get(notifications.next_page_url)
                        }
                    >
                        Next
                    </button>
                </div>
            </div>
        </main>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: '/notifications' }],
};
