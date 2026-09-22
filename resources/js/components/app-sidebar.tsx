import { Form, Link, usePage } from '@inertiajs/react';
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
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
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
    { label: 'Ranking', icon: Trophy },
    { label: 'Attendance', icon: CalendarCheck, href: '/attendance' },
    { label: 'Requests', icon: Inbox, href: '/requests' },
    { label: 'Task Tracker', icon: ListChecks },
    { label: 'IT EOD Reports', icon: ClipboardCheck },
    { label: 'DiverText', icon: MessagesSquare },
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
    { label: 'Campaign Schedules', icon: CalendarDays },
    { label: 'Employees', icon: Users, href: '/employees' },
    { label: 'Team Assigning', icon: UserRoundPlus, href: '/team-assigning' },
    { label: 'Team', icon: UsersRound, href: '/teams' },
    { label: 'Campaign', icon: Megaphone, href: '/campaigns' },
    { label: 'Sanctions', icon: Gavel },
    { label: 'Forms', icon: FileText },
    { label: 'Satisfaction Results', icon: ClipboardList },
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
    const className = `flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition hover:bg-white/8 hover:text-white ${
        active ? 'bg-white/12 text-white' : 'text-[#bcb7c7]'
    } ${nested ? 'pl-5' : ''}`;

    if (item.href) {
        return (
            <Link href={item.href} className={className}>
                <Icon className="size-[18px] shrink-0" />
                <span>{item.label}</span>
            </Link>
        );
    }

    return (
        <button type="button" className={className}>
            <Icon className="size-[18px] shrink-0" />
            <span>{item.label}</span>
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
                className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-[#d1ccd8] transition hover:bg-white/8 hover:text-white focus-visible:ring-2 focus-visible:ring-white/40 focus-visible:outline-none"
            >
                <Icon className="size-[18px]" />
                <span>{label}</span>
                <ChevronDown
                    className={`ml-auto size-4 transition-transform duration-200 ${open ? 'rotate-0' : '-rotate-90'}`}
                />
            </button>
            <div
                id={id}
                className={`grid transition-[grid-template-rows] duration-300 ease-in-out ${open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}
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
    const page = usePage<{ auth: Auth }>();
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
            className="border-r border-white/10 [--sidebar-foreground:#f7f4fa] [--sidebar:#171326]"
        >
            <SidebarHeader className="h-32 items-center justify-center border-b border-white/10 bg-[#171326]">
                <Link
                    href={dashboard()}
                    className="flex h-[52px] w-[100px] items-center justify-center rounded-[50%] border-[3px] border-[#e63535] bg-[#a50d20] text-sm font-black tracking-[-0.08em] text-white italic"
                >
                    DIVERTEX
                </Link>
            </SidebarHeader>

            <SidebarContent className="bg-[linear-gradient(180deg,#191426_0%,#28131d_62%,#401414_100%)] px-3 py-4">
                <nav className="space-y-1">
                    {primaryItems.map((item) => (
                        <SidebarItem
                            key={item.label}
                            item={item}
                            currentUrl={page.url}
                        />
                    ))}

                    {isManager && (
                        <>
                            <SidebarGroup
                                label="Management"
                                icon={Layers3}
                                items={managementItems}
                                open={managementOpen}
                                onToggle={() =>
                                    setManagementOpen((value) => !value)
                                }
                                currentUrl={page.url}
                            />
                            <SidebarGroup
                                label="Training & Development"
                                icon={BookOpenCheck}
                                items={trainingItems}
                                open={trainingOpen}
                                onToggle={() =>
                                    setTrainingOpen((value) => !value)
                                }
                                currentUrl={page.url}
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
                            onToggle={() =>
                                setQualityOpen((value) => !value)
                            }
                            currentUrl={page.url}
                        />
                    )}
                    {!isManager && isQaViewer && (
                        <SidebarItem
                            item={coachingItem}
                            currentUrl={page.url}
                        />
                    )}

                    <div className="pt-2">
                        <SidebarItem
                            item={{ label: 'Payroll', icon: ReceiptText }}
                            currentUrl={page.url}
                        />
                        <SidebarItem
                            item={{ label: 'Settings', icon: Settings }}
                            currentUrl={page.url}
                        />
                    </div>
                </nav>
            </SidebarContent>

            <SidebarFooter className="border-t border-white/10 bg-[#401414] p-4 text-white">
                <div className="mb-3 flex items-center gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#ed4328]">
                        <UserCircle className="size-5" />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {auth.user.name}
                        </p>
                        <p className="text-xs text-white/65 capitalize">
                            {auth.user.role}
                        </p>
                    </div>
                </div>
                <Form {...logout.form()}>
                    <button
                        type="submit"
                        className="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-white/10 text-sm font-semibold transition hover:bg-white/15"
                    >
                        <LogOut className="size-4" />
                        Logout
                    </button>
                </Form>
            </SidebarFooter>
        </Sidebar>
    );
}
