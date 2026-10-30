export function pictureUrl(value: string | null | undefined): string | undefined {
    if (!value) return undefined;

    try {
        const { protocol } = new URL(value);
        return ['http:', 'https:', 'blob:', 'data:'].includes(protocol) ? value : undefined;
    } catch {
        return undefined;
    }
}
