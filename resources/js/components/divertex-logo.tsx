import { useState } from 'react';

export default function DivertexLogo({
    className = '',
}: {
    className?: string;
}) {
    const [unavailable, setUnavailable] = useState(false);

    return unavailable ? (
        <span
            className={`inline-flex items-center justify-center px-2 py-3 font-black tracking-tight text-[#e63535] italic ${className}`}
        >
            DIVERTEX
        </span>
    ) : (
        <img
            src="/images/divertex-logo.png"
            alt="Divertex"
            width={1714}
            height={918}
            className={`h-auto object-contain ${className}`}
            onError={() => setUnavailable(true)}
        />
    );
}
