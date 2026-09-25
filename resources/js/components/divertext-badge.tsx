import { useDiverTextUnread } from '@/components/divertext-unread-provider';

export function DiverTextBadge() {
    const count = useDiverTextUnread();

    return count ? (
        <span
            className="ml-auto rounded-full bg-red-600 px-1.5 text-xs font-bold text-white group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:top-0 group-data-[collapsible=icon]:right-0"
            aria-label={`${count} unread messages`}
        >
            {count > 99 ? '99+' : count}
        </span>
    ) : null;
}
