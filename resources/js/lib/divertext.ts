export async function chatRequest<T>(
    url: string,
    method = 'GET',
    data?: unknown,
    signal?: AbortSignal,
): Promise<T> {
    const token = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method,
        signal,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
        },
        ...(data !== undefined ? { body: JSON.stringify(data) } : {}),
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({}));

        throw new Error(
            response.status === 403
                ? 'You no longer have access to this conversation.'
                : response.status === 401 || response.status === 419
                  ? 'Your session expired. Refresh the page and sign in again.'
                  : error.message || 'Unable to connect. Please try again.',
        );
    }

    return response.json() as Promise<T>;
}
