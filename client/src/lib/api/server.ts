import 'server-only';

import { cache } from 'react';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { ApiError, apiPath } from './errors';
import type { ApiContext, Paginated, Resource, SessionData } from './types';
export { ApiError } from './errors';
export type { ApiContext, ApiUser, Paginated, Resource, SessionData } from './types';

const backend = (process.env.LERNUMI_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');

export async function serverApi<T = unknown>(path: string, options: RequestInit = {}): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('Cookie', (await cookies()).toString());
    headers.set('Origin', process.env.FRONTEND_URL ?? 'http://localhost:3001');
    const response = await fetch(backend + apiPath(path), { ...options, headers, cache: 'no-store', redirect: 'manual' });
    const payload: unknown = response.status === 204 ? undefined : await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, payload);
    return payload as T;
}

export const getSession = cache(async (): Promise<SessionData | null> => {
    try {
        const { data } = await serverApi<Resource<SessionData>>('/api/v1/auth/session');
        const contexts = data.contexts.map(context => ({ ...context, assignmentId: context.roleAssignmentId ?? context.id }));
        if (data.isAdmin) {
            for (let page = 1; ; page++) {
                const institutions = await serverApi<Paginated<{ id: string; name: string; logoUrl?: string | null }>>(`/api/v1/institutions?perPage=100&page=${page}`);
                for (const institution of institutions.data) {
                    contexts.push({ id: 'admin:' + institution.id, assignmentId: 'admin:' + institution.id, roleAssignmentId: null,
                        institution, role: { id: 'system-admin', roleCode: 'system:admin', roleName: 'System administrator', scopeType: 'INSTITUTION' }, permissions: [], offeringIds: [] });
                }
                if (page >= institutions.meta.last_page) break;
            }
        }
        return { ...data, contexts };
    } catch (error) {
        if (error instanceof ApiError && error.status === 401) return null;
        throw error;
    }
});

export async function requireSession(): Promise<SessionData> {
    const session = await getSession();
    if (!session) redirect('/auth');
    return session;
}
