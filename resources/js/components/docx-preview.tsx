import * as mammoth from 'mammoth';
import { useEffect, useState } from 'react';

export default function DocxPreview({ url }: { url: string }) {
    const [text, setText] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();

        const load = async () => {
            try {
                setLoading(true);
                setError('');
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error('The document could not be loaded.');
                }

                const result = await mammoth.extractRawText({
                    arrayBuffer: await response.arrayBuffer(),
                });
                setText(result.value.trim());
            } catch (reason) {
                if (!controller.signal.aborted) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'The document could not be previewed.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        };

        void load();

        return () => controller.abort();
    }, [url]);

    if (loading) {
        return <p className="mt-3 text-sm text-slate-500">Loading preview…</p>;
    }

    if (error) {
        return <p className="mt-3 text-sm text-red-700">{error}</p>;
    }

    return (
        <div className="mt-4 rounded-xl border bg-slate-50 p-4">
            <p className="mb-2 text-xs font-bold text-slate-500 uppercase">
                Document Preview
            </p>
            <pre className="max-h-[32rem] overflow-auto whitespace-pre-wrap font-sans text-sm leading-6 text-slate-800">
                {text || 'This document contains no readable text.'}
            </pre>
        </div>
    );
}
