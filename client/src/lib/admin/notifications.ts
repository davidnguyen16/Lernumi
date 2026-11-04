'use client';

import { api } from '@/lib/api/client';
import type { Paginated } from '@/lib/api/types';

type Institution = { id: string; name: string };
type AccessRequest = {
    id: string;
    requestedAt: string;
    user: { name: string; email: string };
    role: { roleName: string };
};
type ScopeRequest = AccessRequest & {
    offering: { subjectCode: string; subjectName: string; year: number; session: number };
};

export type PendingAdminRequest = {
    id: string;
    kind: 'role' | 'scope';
    title: string;
    body: string;
    requestedAt: string;
    href: string;
};

async function allPages<T>(path: string, signal: AbortSignal): Promise<T[]> {
    const items: T[] = [];
    for (let page = 1; ; page++) {
        const separator = path.includes('?') ? '&' : '?';
        const result = await api<Paginated<T>>(`${path}${separator}perPage=100&page=${page}`, { signal });
        items.push(...result.data);
        if (page >= result.meta.last_page) return items;
    }
}

export async function pendingAdminRequests(signal: AbortSignal): Promise<PendingAdminRequest[]> {
    const institutions = await allPages<Institution>('/admin/institutions', signal);
    const jobs = institutions.flatMap(institution => [
        { institution, kind: 'role' as const },
        { institution, kind: 'scope' as const },
    ]);
    const requests: PendingAdminRequest[] = [];
    let nextJob = 0;
    let failed = false;

    // Each worker paginates one list at a time, keeping the total at four requests.
    async function worker(): Promise<void> {
        while (!signal.aborted && !failed && nextJob < jobs.length) {
            const { institution, kind } = jobs[nextJob++];
            const path = `/institutions/${institution.id}/${kind}-requests?status=PENDING`;
            try {
                const items = await allPages<AccessRequest | ScopeRequest>(path, signal);
                for (const item of items) {
                    const person = item.user.name || item.user.email;
                    const offering = 'offering' in item ? item.offering : null;
                    requests.push({
                        id: `${kind}:${item.id}`,
                        kind,
                        title: kind === 'role' ? `${person} requested a role` : `${person} requested subject access`,
                        body: offering
                            ? `${institution.name} · ${offering.subjectCode} · ${offering.year}, session ${offering.session} · ${item.role.roleName}`
                            : `${institution.name} · ${item.role.roleName}`,
                        requestedAt: item.requestedAt,
                        href: `/admin/institutions/${institution.id}/requests`,
                    });
                }
            } catch (error) {
                failed = true;
                throw error;
            }
        }
    }

    await Promise.all(Array.from({ length: Math.min(4, jobs.length) }, () => worker()));
    return Array.from(new Map(requests.map(request => [request.id, request])).values())
        .sort((left, right) => Date.parse(right.requestedAt) - Date.parse(left.requestedAt) || left.id.localeCompare(right.id));
}
