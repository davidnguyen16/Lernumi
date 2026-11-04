type ErrorPayload = { message?: string; errors?: Record<string, string[]>; error?: { message?: string; code?: string; fields?: Record<string, string[]>; details?: unknown } };

export class ApiError extends Error {
    readonly errors: Record<string, string[]>;
    readonly code?: string;
    readonly details?: unknown;

    constructor(readonly status: number, readonly payload: unknown) {
        const body = payload && typeof payload === 'object' ? payload as ErrorPayload : {};
        super(body.message ?? body.error?.message ?? (status === 503 || status === 502 ? 'The API is unavailable. Please try again.' : `The request failed (${status}).`));
        this.name = 'ApiError';
        this.errors = body.errors ?? body.error?.fields ?? {};
        this.code = body.error?.code;
        this.details = body.error?.details;
    }
}

export function apiPath(path: string): string {
    if (!path.startsWith('/') || path.startsWith('//') || /[\r\n]/.test(path)) throw new Error('Use a relative API path.');
    if (path.startsWith('/api/') || path.startsWith('/sanctum/')) return path;
    if (path === '/v1' || path.startsWith('/v1/')) return '/api' + path;
    return '/api/v1' + path;
}
