import { useId } from 'react';

export default function DivertexMark({ className }: { className?: string }) {
    const gradientId = useId();

    return (
        <svg
            viewBox="0 0 48 40"
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0.7" y2="1">
                    <stop offset="0" stopColor="#ff8787" />
                    <stop offset="0.25" stopColor="#f03737" />
                    <stop offset="0.65" stopColor="#cf1724" />
                    <stop offset="1" stopColor="#ff5050" />
                </linearGradient>
            </defs>
            <ellipse
                cx="24"
                cy="20"
                rx="22"
                ry="17"
                fill="#27131f"
                stroke={`url(#${gradientId})`}
                strokeWidth="2.5"
            />
            <path
                d="M17 10H26C35 10 35 16 32 24C30 29 27 30 21 30H12L17 10ZM22 15L19 25H22C25 25 26 23 27 19C28 16 27 15 25 15H22Z"
                fill={`url(#${gradientId})`}
                fillRule="evenodd"
            />
        </svg>
    );
}
