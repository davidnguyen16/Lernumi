'use client';

import type { FieldValues, Path, UseFormReturn } from 'react-hook-form';
import { ApiError, apiPath } from './errors';
export { ApiError } from './errors';

export type ApiOptions = Omit<RequestInit, 'body'> & { body?: unknown };

function csrfToken(): string {
    const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

export async function bootstrapCsrf(): Promise<void> {
    const response = await fetch('/sanctum/csrf-cookie', { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) throw new ApiError(response.status, { message: 'Could not establish a secure session. Please try again.' });
}

export async function api<T = unknown>(path: string, options: ApiOptions = {}): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();
    const mutates = !['GET', 'HEAD', 'OPTIONS'].includes(method);
    if (mutates && !csrfToken()) await bootstrapCsrf();

    async function send(): Promise<Response> {
        const headers = new Headers(options.headers);
        headers.set('Accept', 'application/json');
        if (mutates) headers.set('X-XSRF-TOKEN', csrfToken());
        let body: BodyInit | undefined;
        if (options.body instanceof FormData || options.body instanceof Blob || typeof options.body === 'string' || options.body instanceof URLSearchParams) {
            body = options.body;
        } else if (options.body !== undefined) {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(options.body);
        }
        return fetch(apiPath(path), { ...options, method, body, headers, credentials: 'include', cache: 'no-store' });
    }

    let response = await send();
    // A rejected CSRF check occurs before the mutation; refreshing once is safe.
    if (response.status === 419 && mutates) {
        await bootstrapCsrf();
        response = await send();
    }
    const payload: unknown = response.status === 204 ? undefined : await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, payload);
    return payload as T;
}

export function applyFormErrors<T extends FieldValues>(error: unknown, form: Pick<UseFormReturn<T>, 'setError'>): void {
    if (error instanceof ApiError && Object.keys(error.errors).length) {
        for (const [field, messages] of Object.entries(error.errors)) {
            const name = field.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase()) as Path<T>;
            form.setError(name, { type: 'server', message: messages.join(' ') });
        }
    }
    form.setError('root', { type: 'server', message: error instanceof Error ? error.message : 'Something went wrong. Please try again.' });
}
