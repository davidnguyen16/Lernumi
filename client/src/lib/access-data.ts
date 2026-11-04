import 'server-only';

import { serverApi, requireSession } from '@/lib/api/server';
import { can, getActiveContext } from '@/lib/api/context';
import type { RoleListItem, RoleInheritanceOption } from '@/components/admin/roles/roles-table';
import type { PersonListItem } from '@/components/admin/people/people-table';
import type { ContextOption } from '@/lib/context/context-params';

export type Query = Record<string, string | string[] | undefined>;
export type PageResult<T> = { data: T[]; meta?: { total?: number; last_page?: number; current_page?: number; lastPage?: number; currentPage?: number } };
export type Institution = { id: string; name: string; website: string | null; logoUrl?: string | null };
export type Role = { id: string; institutionId: string; roleCode: string; roleName: string; scopeType: 'INSTITUTION' | 'OFFERING'; requestMode: 'DISABLED' | 'PUBLIC' | 'CONDITIONAL'; autoGrantEmailRegex: string | null; permissions: string[]; effectivePermissions: string[]; includedRoleIds: string[]; requiredRoleIds: string[]; counts?: { assignments: number; requests: number } };
export type Offering = { id: string; institutionId: string; subjectId: string; termId: string; subject: { subjectCode: string; subjectName: string }; term?: { year: number; session: number }; studyTerm?: { year: number; session: number } };
export type User = { id: string; name: string; firstName?: string | null; lastName?: string | null; email: string; isAdmin: boolean; isActive: boolean; avatarUrl: string | null; createdAt: string; counts?: { roles: number; attempts: number } };
export type Scope = { id: string; offeringId: string; grantedById: string | null; scopeAssignmentCodeId: string | null; revokedAt: string | null };
export type Assignment = { id: string; institutionId: string; userId: string; user: { id: string; name: string; email: string }; roleId: string; role: { id: string; roleCode: string; roleName: string; scopeType: 'INSTITUTION' | 'OFFERING' }; grantedById: string | null; grantedAt: string; revokedAt: string | null; scopeAssignments: Scope[] };
export type Permission = { code: string; description: string | null; institutionOnly: boolean };

export function names(user: { name?: string; firstName?: string | null; lastName?: string | null; email: string }) {
    const parts = (user.name || user.email).split(/\s+/);
    return { firstName: user.firstName ?? parts[0] ?? '', lastName: user.lastName ?? parts.slice(1).join(' ') };
}
export function displayDate(value: string | null | undefined) { return value ? new Date(value).toLocaleString('en-AU', { timeZone: 'Australia/Sydney' }) : ''; }
export function one(value: string | string[] | undefined) { return Array.isArray(value) ? value[0] : value; }
export function queryString(query: Record<string, string | number | boolean | null | undefined>) {
    return new URLSearchParams(Object.entries(query).filter(([, value]) => value !== undefined && value !== null && value !== '').map(([key, value]) => [key, String(value)])).toString();
}
export async function all<T>(path: string): Promise<T[]> {
    const values: T[] = [];
    for (let page = 1; ; page++) {
        const result = await serverApi<PageResult<T>>(`${path}${path.includes('?') ? '&' : '?'}perPage=100&per_page=100&page=${page}`);
        values.push(...result.data);
        if (page >= (result.meta?.last_page ?? result.meta?.lastPage ?? 1)) return values;
    }
}
export async function permitted<T>(path: string): Promise<T[]> {
    try { return await all<T>(path); }
    catch (error) { if (error instanceof Error && 'status' in error && error.status === 403) return []; throw error; }
}
export async function accessContext(query: Query) {
    const session = await requireSession();
    const context = getActiveContext(query, session);
    const visible = await all<Institution>('/api/v1/institutions');
    const institutions = Array.from(new Map([...session.contexts.map(context => ({ id: context.institution.id, name: context.institution.name, website: null, logoUrl: context.institution.logoUrl ?? null } as Institution)), ...visible].map(item => [item.id, item])).values());
    const requestedId = one(query.institution);
    const institutionId = institutions.some(item => item.id === requestedId) ? requestedId! : context?.institution.id ?? institutions[0]?.id ?? null;
    return { session, context, institutions, institutionId };
}
export function roleItem(role: Role, institution: Institution, roles: Role[], permissions: Permission[]): RoleListItem {
    return { ...role, institution, includedRoles: roles.filter(item => role.includedRoleIds.includes(item.id)).map(({ id, roleName }) => ({ id, roleName })), prerequisiteRoles: roles.filter(item => role.requiredRoleIds.includes(item.id)).map(({ id, roleName }) => ({ id, roleName })), permissions: role.permissions.map(code => ({ id: code, permission: code, description: permissions.find(permission => permission.code === code)?.description ?? null })), counts: role.counts ?? { assignments: 0, requests: 0 } };
}
export function roleOptions(roles: Role[]): RoleInheritanceOption[] { return roles.map(role => ({ id: role.id, roleName: role.roleName, roleCode: role.roleCode, scopeType: role.scopeType, effectivePermissionIds: role.effectivePermissions })); }
export function offeringItem(offering: Offering) { return { ...offering, term: offering.term ?? offering.studyTerm ?? { year: 0, session: 0 } }; }
export function peopleItems(assignments: Assignment[], offerings: Offering[]): PersonListItem[] {
    const people = new Map<string, PersonListItem>();
    for (const assignment of assignments) {
        const person = people.get(assignment.userId) ?? { user: { id: assignment.userId, ...names(assignment.user), email: assignment.user.email }, assignments: [] };
        person.assignments.push({ id: assignment.id, role: assignment.role, granted: displayDate(assignment.grantedAt), revoked: assignment.revokedAt, source: assignment.grantedById ? 'MANUAL' : 'REQUEST', scopes: assignment.scopeAssignments.filter(scope => !scope.revokedAt).flatMap(scope => { const offering = offerings.find(item => item.id === scope.offeringId); return offering ? [{ id: scope.id, source: scope.scopeAssignmentCodeId ? 'CODE' as const : 'DIRECT' as const, assignedBy: scope.grantedById, offering: offeringItem(offering) }] : []; }) });
        people.set(assignment.userId, person);
    }
    return Array.from(people.values());
}
export async function roleContexts(): Promise<ContextOption[]> {
    const session = await requireSession();
    const institutions = new Map(session.contexts.map(context => [context.institution.id, context.institution]));
    const assignments = await all<Assignment>('/api/v1/me/role-assignments');
    return assignments.map(assignment => ({ assignmentId: assignment.id, institution: { id: assignment.institutionId, name: institutions.get(assignment.institutionId)?.name ?? 'Institution', logoKey: null }, role: assignment.role, offering: null }));
}
export { can, requireSession, serverApi };
