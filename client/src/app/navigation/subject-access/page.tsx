import { SubjectAccess, type SubjectAccessOffering, type SubjectAccessRole } from '@/components/navigation/subject-access';
import { JoinCodeForm } from '@/components/navigation/join-code-form';
import { all, requireSession, one, type Assignment, type Offering, type Query } from '@/lib/access-data';
type ScopeRequest = { id: string; roleAssignmentId: string; status: string; offering: { id: string; subjectCode: string; subjectName: string; year: number; session: number; institutionId: string } };
export default async function Page({ searchParams }: PageProps<'/navigation/subject-access'>) {
    const session = await requireSession(); const query: Query = await searchParams;
    const [assignments, requests] = await Promise.all([all<Assignment>('/api/v1/me/role-assignments'), all<ScopeRequest>('/api/v1/me/scope-requests')]);
    const institutionIds = Array.from(new Set(assignments.map(assignment => assignment.institutionId)));
    const offerings = (await Promise.all(institutionIds.map(id => all<Offering>('/api/v1/institutions/' + id + '/requestable-offerings')))).flat();
    const available: SubjectAccessOffering[] = offerings.map(offering => ({ id: offering.id, institutionId: offering.institutionId, subjectCode: offering.subject.subjectCode, subjectName: offering.subject.subjectName, year: offering.term?.year ?? offering.studyTerm?.year ?? 0, session: offering.term?.session ?? offering.studyTerm?.session ?? 0 }));
    const roles: SubjectAccessRole[] = assignments.map(assignment => ({ assignmentId: assignment.id, roleName: assignment.role.roleName, scopeType: assignment.role.scopeType, institution: { id: assignment.institutionId, name: session.contexts.find(context => context.institution.id === assignment.institutionId)?.institution.name ?? 'Institution' }, scopes: assignment.scopeAssignments.filter(scope => !scope.revokedAt).flatMap(scope => { const offering = available.find(item => item.id === scope.offeringId); return offering ? [{ id: scope.id, source: scope.scopeAssignmentCodeId ? 'CODE' as const : 'DIRECT' as const, offering }] : []; }), pendingRequests: requests.filter(request => request.status === 'PENDING' && request.roleAssignmentId === assignment.id).map(request => ({ id: request.id, offering: request.offering })) }));
    const initial = one(query.roleAssignmentId) ?? roles.find(role => role.institution.id === one(query.institution))?.assignmentId ?? roles[0]?.assignmentId ?? null;
    return <><div className="mx-auto max-w-6xl pt-7"><JoinCodeForm roles={roles.filter(role => role.scopeType === 'OFFERING').map(role => ({ id: role.assignmentId, name: role.institution.name + ' · ' + role.roleName }))} /></div><SubjectAccess roles={roles} offerings={available} initialAssignmentId={initial} /></>;
}
