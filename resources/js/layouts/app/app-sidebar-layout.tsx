import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { DiverTextUnreadProvider } from '@/components/divertext-unread-provider';
import { MessageWidget } from '@/components/message-widget';
import type { Auth } from '@/types';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <DiverTextUnreadProvider
            enabled={!['trainee', 'qa_admin'].includes(auth.user.role)}
        >
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="app-workspace overflow-x-hidden"
                >
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
                {!['trainee', 'qa_admin'].includes(auth.user.role) && (
                    <MessageWidget />
                )}
            </AppShell>
        </DiverTextUnreadProvider>
    );
}
