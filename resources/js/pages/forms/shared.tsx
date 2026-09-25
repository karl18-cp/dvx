import { Link } from '@inertiajs/react';

export type Field = {
    id: string;
    label: string;
    type: string;
    placeholder: string;
    required: boolean;
    options: string[];
};
export type Team = {
    id: number;
    name: string;
    campaign?: { name: string } | null;
};
export type FormRecord = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    ranking_enabled: boolean;
    revision: number;
    fields: Field[];
    teams: Team[];
    responses_count: number;
    created_at: string;
};
export type ResponseRecord = {
    id: number;
    form_title: string;
    form_description: string | null;
    employee_name: string;
    employee_username: string | null;
    teams: Team[];
    revision: number;
    points: number;
    answers: (Field & { value: string | number | string[] | null })[];
    created_at: string;
};
export type Page<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export const types = [
    'text',
    'textarea',
    'number',
    'email',
    'date',
    'dropdown',
    'radio',
    'checkbox',
];
export const choiceTypes = ['dropdown', 'radio', 'checkbox'];
export const panel = 'rounded-2xl border border-slate-200 bg-white p-5';
export const formatDate = (value: string) =>
    new Date(value).toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
export const teamLabel = (team: Team) =>
    `${team.name}${team.campaign ? ` – ${team.campaign.name}` : ''}`;
export function Pagination({ page }: { page: Page<unknown> }) {
    return (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
            <span>
                {page.total} records · Page {page.current_page} of{' '}
                {page.last_page}
            </span>
            <div className="flex gap-4">
                {page.prev_page_url && (
                    <Link
                        preserveScroll
                        href={page.prev_page_url}
                        className="font-bold text-red-700"
                    >
                        Previous
                    </Link>
                )}
                {page.next_page_url && (
                    <Link
                        preserveScroll
                        href={page.next_page_url}
                        className="font-bold text-red-700"
                    >
                        Next
                    </Link>
                )}
            </div>
        </div>
    );
}
