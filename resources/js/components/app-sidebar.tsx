import { Form, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ChevronDown,
    ClipboardCheck,
    ClipboardList,
    BookOpenCheck,
    FileText,
    Gavel,
    Inbox,
    Layers3,
    LayoutDashboard,
    ListChecks,
    LogOut,
    Megaphone,
    MessagesSquare,
    Presentation,
    ReceiptText,
    Settings,
    Trophy,
    UserCircle,
    UserRoundPlus,
    Users,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import DivertexLogo from '@/components/divertex-logo';
import DivertexMark from '@/components/divertex-mark';
import { DiverTextBadge } from '@/components/divertext-badge';
import type { Navigation } from '@/components/page-link';
import Link from '@/components/page-link';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, logout } from '@/routes';
import type { Auth } from '@/types';

type MenuItem = {
    label: string;
    icon: LucideIcon;
    href?: string;
    activePrefixes?: string[];
    excludePrefixes?: string[];
};

const primaryItems: MenuItem[] = [
    { label: 'Dashboard', icon: LayoutDashboard, href: dashboard().url },
    { label: 'Announcements', icon: Megaphone, href: '/announcements' },
    { label: 'Ranking', icon: Trophy, href: '/ranking' },
    { label: 'Attendance', icon: CalendarCheck, href: '/attendance' },
    { label: 'Requests', icon: Inbox, href: '/requests' },
    { label: 'My Forms', icon: FileText, href: '/my-forms' },
    { label: 'Task Tracker', icon: ListChecks, href: '/task-tracker' },
    { label: 'EOD Report', icon: ClipboardCheck, href: '/eod-reports' },
    { label: 'DiverText', icon: MessagesSquare, href: '/divertext' },
    {
        label: 'Training & Assessments',
        icon: BookOpenCheck,
        href: '/assessments',
    },
    { label: 'My Coaching', icon: Presentation, href: '/my-coaching' },
];

const managementItems: MenuItem[] = [
    {
        label: 'Applicants',
        icon: UserRoundPlus,
        href: '/management/applicants',
    },
    {
        label: 'Campaign Schedules',
        icon: CalendarDays,
        href: '/campaign-schedules',
    },
    { label: 'Employees', icon: Users, href: '/employees' },
    { label: 'Team Assigning', icon: UserRoundPlus, href: '/team-assigning' },
    { label: 'Team', icon: UsersRound, href: '/teams' },
    { label: 'Campaign', icon: Megaphone, href: '/campaigns' },
    { label: 'Sanctions', icon: Gavel, href: '/sanctions' },
    { label: 'Forms', icon: FileText, href: '/forms' },
    {
        label: 'Satisfaction Results',
        icon: ClipboardList,
        href: '/satisfaction-results',
    },
];

const coachingItem: MenuItem = {
    label: 'Coaching Log',
    icon: Presentation,
    href: '/management/coaching',
    activePrefixes: ['/management/coaching', '/management/employees/'],
};

const trainingItems: MenuItem[] = [
    {
        label: 'Assessments',
        icon: BookOpenCheck,
        href: '/management/assessments',
        excludePrefixes: ['/management/assessments/schedule'],
    },
    {
        label: 'Assignments',
        icon: ClipboardList,
        href: '/management/assessment-assignments',
        activePrefixes: [
            '/management/assessment-assignments',
            '/management/assessments/schedule',
        ],
    },
    {
        label: 'Training Library',
        icon: Presentation,
        href: '/management/training-library',
        activePrefixes: [
            '/management/training-library',
            '/management/question-bank',
        ],
    },
    coachingItem,
    {
        label: 'Pending Reviews',
        icon: ClipboardCheck,
        href: '/management/assessment-reviews',
    },
    {
        label: 'Results & Analytics',
        icon: Trophy,
        href: '/management/assessment-results',
        activePrefixes: [
            '/management/assessment-results',
            '/management/assessment-teams',
        ],
    },
    {
        label: 'Campaign Analytics',
        icon: Trophy,
        href: '/management/campaign-analytics',
    },
];

const qualityItems: MenuItem[] = [
    {
        label: 'QA Dashboard',
        icon: LayoutDashboard,
        href: '/management/qa-dashboard',
    },
    {
        label: 'Call Evaluations',
        icon: ClipboardList,
        href: '/management/call-evaluations',
    },
    {
        label: 'QA Scorecards',
        icon: ClipboardCheck,
        href: '/management/qa-scorecards',
    },
];

function SidebarItem({
    item,
    currentUrl,
    nested = false,
}: {
    item: MenuItem;
    currentUrl: string;
    nested?: boolean;
}) {
    const Icon = item.icon;
    const active = item.href
        ? [item.href, ...(item.activePrefixes || [])].some(
              (prefix) =>
                  currentUrl === prefix || currentUrl.startsWith(`${prefix}/`),
          ) &&
          !(item.excludePrefixes || []).some(
              (prefix) =>
                  currentUrl === prefix || currentUrl.startsWith(`${prefix}/`),
          )
        : false;
    const className = `flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition hover:bg-white/8 hover:text-white group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2 ${
        active ? 'bg-white/12 text-white' : 'text-[#bcb7c7]'
    } ${nested ? 'pl-5' : ''}`;

    if (item.href) {
        return (
            <Link
                href={item.href}
                className={`relative ${className}`}
                aria-label={item.label}
                title={item.label}
            >
                <Icon className="size-[18px] shrink-0" />
                <span className="group-data-[collapsible=icon]:hidden">
                    {item.label}
                </span>
                {item.label === 'DiverText' && <DiverTextBadge />}
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={className}
            aria-label={item.label}
            title={item.label}
        >
            <Icon className="size-[18px] shrink-0" />
            <span className="group-data-[collapsible=icon]:hidden">
                {item.label}
            </span>
        </button>
    );
}

function SidebarGroup({
    label,
    icon: Icon,
    items,
    open,
    onToggle,
    currentUrl,
}: {
    label: string;
    icon: LucideIcon;
    items: MenuItem[];
    open: boolean;
    onToggle: () => void;
    currentUrl: string;
}) {
    const id = `${label.toLowerCase().replaceAll(' ', '-')}-menu`;

    return (
        <div className="pt-1">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                aria-controls={id}
                aria-label={label}
                title={label}
                className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-[#d1ccd8] transition group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2 hover:bg-white/8 hover:text-white focus-visible:ring-2 focus-visible:ring-white/40 focus-visible:outline-none"
            >
                <Icon className="size-[18px] shrink-0" />
                <span className="group-data-[collapsible=icon]:hidden">
                    {label}
                </span>
                <ChevronDown
                    className={`ml-auto size-4 transition-transform duration-200 group-data-[collapsible=icon]:hidden ${open ? 'rotate-0' : '-rotate-90'}`}
                />
            </button>
            <div
                id={id}
                className={`grid transition-[grid-template-rows] duration-300 ease-in-out group-data-[collapsible=icon]:hidden ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}
            >
                <div className="overflow-hidden">
                    <div className="ml-5 space-y-0.5 border-l border-white/15 pl-2">
                        {items.map((item) => (
                            <SidebarItem
                                key={item.label}
                                item={item}
                                currentUrl={currentUrl}
                                nested
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

export function AppSidebar() {
    const { isMobile, setOpen } = useSidebar();
    const page = usePage<{ auth: Auth; navigation?: Navigation }>();
    const { auth } = page.props;
    const [managementOpen, setManagementOpen] = useState(true);
    const [trainingOpen, setTrainingOpen] = useState(true);
    const [qualityOpen, setQualityOpen] = useState(true);
    const isManager =
        auth.user.role === 'admin' || auth.user.role === 'manager';
    const isQaViewer = isManager || auth.user.role === 'team_leader';

    return (
        <Sidebar
            collapsible="icon"
            {...(!isMobile && {
                onPointerEnter: (event) => {
                    if (event.pointerType === 'mouse') {
                        setOpen(true);
                    }
                },
                onPointerLeave: (event) => {
                    if (
                        event.pointerType === 'mouse' &&
                        !event.currentTarget.querySelector(':focus-visible')
                    ) {
                        setOpen(false);
                    }
                },
                onFocusCapture: (event) => {
                    if (event.target.matches(':focus-visible')) {
                        setOpen(true);
                    }
                },
                onBlurCapture: (event) => {
                    if (
                        !event.currentTarget.contains(event.relatedTarget) &&
                        !event.currentTarget.matches(':hover')
                    ) {
                        setOpen(false);
                    }
                },
            })}
            className="border-r border-white/10 [--sidebar-foreground:#f7f4fa] [--sidebar:#171326]"
        >
            <SidebarHeader className="h-32 items-center justify-center border-b border-white/10 bg-[#171326]">
                <Link
                    href={dashboard()}
                    aria-label="Divertex dashboard"
                    className="flex w-44 max-w-full items-center justify-center rounded-xl p-2 transition group-data-[collapsible=icon]:w-12 group-data-[collapsible=icon]:p-0 hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-400"
                >
                    <DivertexMark className="hidden h-10 w-12 shrink-0 group-data-[collapsible=icon]:block" />
                    <span className="w-full group-data-[collapsible=icon]:hidden">
                        <DivertexLogo className="w-full" />
                    </span>
                </Link>
            </SidebarHeader>

            <SidebarContent className="bg-[linear-gradient(180deg,#191426_0%,#28131d_62%,#401414_100%)] px-3 py-4 group-data-[collapsible=icon]:px-1">
                <nav className="space-y-1">
                    {auth.user.role === 'team_leader' && (
                        <SidebarItem
                            item={{
                                label: 'My Attendance',
                                icon: CalendarCheck,
                                href: '/my-attendance',
                            }}
                            currentUrl={
                                (
                                    page.props.navigation?.currentPath ??
                                    page.url
                                ).split('?')[0]
                            }
                        />
                    )}
                    {auth.user.role === 'team_leader' && (
                        <SidebarItem
                            item={{
                                label: 'My Team',
                                icon: UsersRound,
                                href: '/my-team',
                            }}
                            currentUrl={
                                (
                                    page.props.navigation?.currentPath ??
                                    page.url
                                ).split('?')[0]
                            }
                        />
                    )}
                    {primaryItems
                        .filter(
                            (item) =>
                                !['Task Tracker', 'EOD Report'].includes(
                                    item.label,
                                ) ||
                                ['admin', 'team_leader'].includes(
                                    auth.user.role,
                                ),
                        )
                        .map((item) => (
                            <SidebarItem
                                key={item.label}
                                item={
                                    ['agent', 'team_leader'].includes(
                                        auth.user.role,
                                    ) && item.href === '/requests'
                                        ? {
                                              ...item,
                                              label: 'Leave Requests',
                                              href: '/leave-requests',
                                          }
                                        : auth.user.role === 'team_leader' &&
                                            item.href === '/attendance'
                                          ? {
                                                ...item,
                                                label: 'Team Attendance',
                                            }
                                          : auth.user.role === 'admin' &&
                                              item.href === '/my-coaching'
                                            ? {
                                                  ...item,
                                                  label: 'Coaching Overview',
                                              }
                                            : item
                                }
                                currentUrl={
                                    (
                                        page.props.navigation?.currentPath ??
                                        page.url
                                    ).split('?')[0]
                                }
                            />
                        ))}

                    {isManager && (
                        <>
                            <SidebarGroup
                                label="Management"
                                icon={Layers3}
                                items={managementItems.filter(
                                    (item) =>
                                        ![
                                            '/sanctions',
                                            '/forms',
                                            '/satisfaction-results',
                                        ].includes(item.href ?? '') ||
                                        auth.user.role === 'admin',
                                )}
                                open={managementOpen}
                                onToggle={() =>
                                    setManagementOpen((value) => !value)
                                }
                                currentUrl={
                                    (
                                        page.props.navigation?.currentPath ??
                                        page.url
                                    ).split('?')[0]
                                }
                            />
                            <SidebarGroup
                                label="Training & Development"
                                icon={BookOpenCheck}
                                items={trainingItems}
                                open={trainingOpen}
                                onToggle={() =>
                                    setTrainingOpen((value) => !value)
                                }
                                currentUrl={
                                    (
                                        page.props.navigation?.currentPath ??
                                        page.url
                                    ).split('?')[0]
                                }
                            />
                        </>
                    )}
                    {isQaViewer && (
                        <SidebarGroup
                            label="Quality Assurance"
                            icon={ClipboardCheck}
                            items={
                                isManager
                                    ? qualityItems
                                    : qualityItems.filter(
                                          (item) =>
                                              item.label !== 'QA Scorecards',
                                      )
                            }
                            open={qualityOpen}
                            onToggle={() => setQualityOpen((value) => !value)}
                            currentUrl={
                                (
                                    page.props.navigation?.currentPath ??
                                    page.url
                                ).split('?')[0]
                            }
                        />
                    )}
                    {!isManager && isQaViewer && (
                        <SidebarItem
                            item={coachingItem}
                            currentUrl={
                                (
                                    page.props.navigation?.currentPath ??
                                    page.url
                                ).split('?')[0]
                            }
                        />
                    )}

                    <div className="pt-2">
                        <SidebarItem
                            item={{ label: 'Payroll', icon: ReceiptText }}
                            currentUrl={
                                (
                                    page.props.navigation?.currentPath ??
                                    page.url
                                ).split('?')[0]
                            }
                        />
                        <SidebarItem
                            item={{
                                label: 'Settings',
                                icon: Settings,
                                href: '/settings',
                                activePrefixes: ['/settings/'],
                            }}
                            currentUrl={
                                (
                                    page.props.navigation?.currentPath ??
                                    page.url
                                ).split('?')[0]
                            }
                        />
                    </div>
                </nav>
            </SidebarContent>

            <SidebarFooter className="border-t border-white/10 bg-[#401414] p-4 text-white group-data-[collapsible=icon]:p-1">
                <div className="mb-3 flex items-center gap-3 group-data-[collapsible=icon]:justify-center">
                    <Link
                        href="/settings/profile"
                        aria-label="Edit your profile"
                        className="shrink-0 rounded-full focus-visible:outline-2 focus-visible:outline-white"
                    >
                        <Avatar className="size-10">
                            <AvatarImage
                                src={auth.user.avatar}
                                alt={auth.user.name}
                                className="object-cover"
                            />
                            <AvatarFallback className="bg-[#ed4328] text-white">
                                <UserCircle className="size-5" />
                            </AvatarFallback>
                        </Avatar>
                    </Link>
                    <div className="min-w-0 group-data-[collapsible=icon]:hidden">
                        <p className="truncate text-sm font-semibold">
                            {auth.user.name}
                        </p>
                        <p className="text-xs text-white/65 capitalize">
                            {auth.user.role.replaceAll('_', ' ')}
                        </p>
                    </div>
                </div>
                <Form {...logout.form()}>
                    <button
                        type="submit"
                        aria-label="Logout"
                        title="Logout"
                        className="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-white/10 text-sm font-semibold transition hover:bg-white/15"
                    >
                        <LogOut className="size-4" />
                        <span className="group-data-[collapsible=icon]:hidden">
                            Logout
                        </span>
                    </button>
                </Form>
            </SidebarFooter>
        </Sidebar>
    );
}
