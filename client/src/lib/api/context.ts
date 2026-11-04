import type { ContextOption } from '@/lib/context/context-params';
import type { ApiContext, SearchParams, SessionData } from './types';

const first = (value: string | string[] | undefined) => Array.isArray(value) ? value[0] : value;

export function getActiveContext(params: SearchParams, session: SessionData): ApiContext | null {
    const institution = first(params.institution);
    const role = first(params.role);
    const assignment = first(params.roleAssignmentId);
    const offering = first(params.offering);
    const context = session.contexts.find(item => (!institution || item.institution.id === institution)
        && (!role || item.role.id === role) && (!assignment || item.roleAssignmentId === assignment));
    if (!context || (offering && !session.isAdmin && context.role.scopeType === 'OFFERING' && !context.offeringIds.includes(offering))) return null;
    return { ...context, offeringId: offering ?? null };
}

export function can(session: SessionData, permission: string, institutionId?: string | null, offeringId?: string | null, roleAssignmentId?: string | null): boolean {
    if (!session.user.isActive) return false;
    if (session.isAdmin) return true;
    const institutionOnly = /^(institution\.|subject\.|term\.|role\.|role_request\.)/.test(permission) || ['offering.create', 'audit.view'].includes(permission);
    return session.contexts.some(context => {
        if (institutionId && context.institution.id !== institutionId) return false;
        if (roleAssignmentId && context.roleAssignmentId !== roleAssignmentId) return false;
        if (permission === 'institution.view') return true;
        if (context.role.scopeType === 'OFFERING') {
            if (institutionOnly) return false;
            if (offeringId && !context.offeringIds.includes(offeringId)) return false;
            if (institutionId && !offeringId) return false;
        }
        return context.permissions.includes(permission);
    });
}

export function contextOptions(session: SessionData): ContextOption[] {
    return session.contexts.map(context => ({ assignmentId: context.assignmentId, institution: { ...context.institution, logoKey: context.institution.logoUrl ?? null }, role: context.role, offering: null }));
}
