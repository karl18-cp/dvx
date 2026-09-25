import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';

export type Navigation = { currentPath: string; links: Record<string, string> };

/** Opaque page addresses are issued by the server; no encryption key reaches the browser. */
export default function PageLink({ href = '#', ...props }: InertiaLinkProps) {
    const navigation = usePage().props.navigation as Navigation | undefined;
    const original = typeof href === 'string' ? href : href.url;
    const boundary = original.search(/[?#]/);
    const path = boundary < 0 ? original : original.slice(0, boundary);
    const suffix = boundary < 0 ? '' : original.slice(boundary);
    const url = (navigation?.links[path] ?? path) + suffix;
    const isGet =
        (props.method ?? (typeof href === 'string' ? 'get' : href.method)) ===
        'get';

    return <Link {...props} href={isGet ? url : href} />;
}
