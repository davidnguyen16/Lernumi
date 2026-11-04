export function pictureUrl(value: string | null | undefined): string | undefined {
    if (!value) return undefined;
    if (value.startsWith('/api/v1/') && !value.startsWith('//')) return value;

    try {
        const url = new URL(value);
        const { protocol } = url;
        if (url.pathname.startsWith('/api/v1/') && ['localhost', '127.0.0.1'].includes(url.hostname)) return url.pathname + url.search;
        return ['http:', 'https:', 'blob:', 'data:'].includes(protocol) ? value : undefined;
    } catch {
        return undefined;
    }
}
