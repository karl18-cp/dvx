import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const notifications = usePage().props.assessment_notifications as {
        unread_count: number;
    };

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Link
                href="/notifications"
                className="relative rounded-lg p-2 hover:bg-black/5"
                aria-label={`${notifications.unread_count} unread notifications`}
            >
                <Bell className="size-5" />
                {notifications.unread_count > 0 && (
                    <span className="absolute -top-1 -right-1 min-w-5 rounded-full bg-[#b72822] px-1 text-center text-xs font-bold text-white">
                        {notifications.unread_count > 99
                            ? '99+'
                            : notifications.unread_count}
                    </span>
                )}
            </Link>
        </header>
    );
}
