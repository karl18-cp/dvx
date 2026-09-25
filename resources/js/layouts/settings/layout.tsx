import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import Link from '@/components/page-link';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Password & security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { url } = usePage();

    return (
        <div className="settings-layout mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col overflow-hidden px-4 py-4 text-[#17202d] [--background:white] [--foreground:#17202d] [--input:#e3dadd] [--muted-foreground:#64748b] [--primary-foreground:white] [--primary:#ae1b20] lg:px-8 lg:py-6">
            <div className="shrink-0 [&>header]:mb-4 lg:[&>header]:mb-8">
                <Heading
                    title="Settings"
                    description="Update your profile picture, personal details, and password"
                />
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row lg:gap-12">
                <aside className="w-full shrink-0 lg:w-48">
                    <nav
                        className="flex flex-wrap gap-1 lg:flex-col"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('justify-start lg:w-full', {
                                    'bg-red-50 text-red-800':
                                        isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <div className="min-h-0 min-w-0 flex-1 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:max-w-2xl">
                    <section
                        key={url.split('?')[0]}
                        aria-label="Settings content"
                        tabIndex={0}
                        className="h-full [scrollbar-gutter:stable] overflow-y-auto overscroll-contain p-5 sm:p-7"
                    >
                        <div className="max-w-xl space-y-8">{children}</div>
                    </section>
                </div>
            </div>
        </div>
    );
}
