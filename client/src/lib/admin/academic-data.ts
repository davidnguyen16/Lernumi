import 'server-only';
import { cache } from 'react';
import { requireSession, serverApi } from '@/lib/api/server';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
import type { SessionData } from '@/lib/api/types';
import { can } from '@/lib/api/context';

export type SearchParams = Record<string, string | string[] | undefined>;
export type AcademicInstitution = { id: string; name: string; website: string | null; logoUrl?: string | null; counts?: { subjects: number; terms: number; roles: number; requests: number } };
export type AcademicSubject = { id: string; institutionId: string; subjectCode: string; subjectName: string; subjectDescription: string | null; offeringCount?: number };
export type AcademicPeriod = { id: string; name: string; type: 'TEACHING' | 'BREAK' | 'EXAM'; startAt: string; endAt: string };
export type AcademicTerm = { id: string; institutionId: string; year: number; session: number; periods?: AcademicPeriod[]; offeringCount?: number };
export type AcademicOffering = { id: string; institutionId: string; subjectId: string; termId: string; subject: AcademicSubject; term: AcademicTerm; institution?: AcademicInstitution; assessmentCount?: number; scopeAssignmentCount?: number };
export type AcademicTool = { id: string; code: string; name: string; description: string | null };
export type AcademicAssessment = { id: string; offeringId: string; name: string; description: string | null; weight: number; hurdle: boolean; isGroup: boolean; publishAt: string | null; dueAt: string | null; expectedDue: string | null; allowedTools?: AcademicTool[]; attemptCount?: number };
export type ApiPage<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number; per_page: number } };

export const academicAll = cache(async <T,>(path: string): Promise<T[]> => {
    const items: T[] = [];
    for (let page = 1; ; page++) {
        const separator = path.includes('?') ? '&' : '?';
        const response = await serverApi<ApiPage<T>>(`${path}${separator}per_page=100&perPage=100&page=${page}`);
        items.push(...response.data);
        if (page >= response.meta.last_page) break;
    }
    return items;
});

export async function academicInstitutions(): Promise<AcademicInstitution[]> {
    const session = await requireSession();
    if (session.isAdmin) return academicAll<AcademicInstitution>('/api/v1/admin/institutions');
    const institutions = new Map<string, AcademicInstitution>();
    for (const context of session.contexts) institutions.set(context.institution.id, { ...context.institution, website: null });
    return [...institutions.values()];
}

export async function academicContext(searchParams: SearchParams) {
    const session = await requireSession();
    const wantedInstitution = first(searchParams.institution);
    const wantedRole = first(searchParams.role);
    const wantedAssignment = first(searchParams.roleAssignmentId);
    const institutions = await academicInstitutions();
    const assignment = session.contexts.find(context => (!wantedInstitution || context.institution.id === wantedInstitution)
        && (!wantedRole || context.role.id === wantedRole) && (!wantedAssignment || context.roleAssignmentId === wantedAssignment));
    const institution = session.isAdmin ? (wantedInstitution ? institutions.find(item => item.id === wantedInstitution) : institutions[0]) ?? null
        : institutions.find(item => item.id === assignment?.institution.id) ?? null;
    const query = new URLSearchParams();
    if (institution) query.set('institution', institution.id);
    if (assignment && assignment.institution.id === institution?.id && assignment.roleAssignmentId) {
        query.set('role', assignment.role.id);
        query.set('roleAssignmentId', assignment.roleAssignmentId);
    }
    return { session, institution, assignment, query: query.toString() };
}

export function academicCan(session: SessionData, permission: string, institutionId: string, offeringId?: string, assignmentId?: string | null): boolean {
    return can(session, permission, institutionId, offeringId, assignmentId);
}

export function academicCapabilities(session: SessionData, resource: string, institutionId: string, offeringId?: string, assignmentId?: string | null): CrudCapabilities {
    return Object.fromEntries(['create', 'view', 'update', 'delete'].map(action => [action,
        academicCan(session, `${resource}.${action}`, institutionId, offeringId, assignmentId)])) as CrudCapabilities;
}

export function academicListCapabilities(session: SessionData, resource: string, institutionId: string, assignmentId?: string | null): CrudCapabilities {
    const offeringIds = session.contexts.filter(context => context.institution.id === institutionId
        && (!assignmentId || context.roleAssignmentId === assignmentId)).flatMap(context => context.offeringIds);
    return Object.fromEntries(['create', 'view', 'update', 'delete'].map(action => [action,
        academicCan(session, `${resource}.${action}`, institutionId, undefined, assignmentId)
        || offeringIds.some(id => academicCan(session, `${resource}.${action}`, institutionId, id, assignmentId))])) as CrudCapabilities;
}

export function first(value: string | string[] | undefined): string | undefined { return Array.isArray(value) ? value[0] : value; }
export function academicDate(value: string | null | undefined): string | null {
    return value ? new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(value)) : null;
}
export function periodRows(periods: AcademicPeriod[]) { return periods.map(period => ({ ...period, starts: academicDate(period.startAt) ?? '', ends: academicDate(period.endAt) ?? '' })); }
export function assessmentRows(assessments: AcademicAssessment[]) {
    return assessments.map(assessment => ({ ...assessment, published: academicDate(assessment.publishAt), due: academicDate(assessment.dueAt), tools: assessment.allowedTools ?? [], attempts: assessment.attemptCount ?? 0 }));
}
