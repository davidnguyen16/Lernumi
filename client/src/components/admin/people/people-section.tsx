import { PeopleTable } from '@/components/admin/people/people-table';
import { NavigationEmptyState } from '@/components/navigation/page-shell';
import { parseListParams, parseOptionalUuid } from '@/lib/admin/list-params';
import { all, permitted, requireSession, can, one, names, offeringItem, peopleItems, type Query, type Assignment, type Role, type Offering, type User } from '@/lib/access-data';

export async function PeopleSection({ institutionId, searchParams, endpoint, contextQuery = '', canManage, roleCapabilities, scopeCapabilities, roleFilterParam = 'role', allowedOfferingIds, allowProtectedRoles = true }: {
    institutionId: string; searchParams: Query; endpoint?: string; contextQuery?: string; canManage?: boolean; roleCapabilities?: { create: boolean; revoke: boolean }; scopeCapabilities?: { view: boolean; create: boolean; revoke: boolean }; roleFilterParam?: string; allowedOfferingIds?: readonly string[] | null; allowProtectedRoles?: boolean;
}) {
    const session = await requireSession();
    if (!institutionId) return <NavigationEmptyState title="No institution selected" description="Select an institution to view its members." />;
    const { q, page, sort, dir } = parseListParams(searchParams, ['user'] as const);
    const roleId = parseOptionalUuid(searchParams[roleFilterParam]);
    const offeringId = parseOptionalUuid(searchParams.offering);
    const rawStatus = one(searchParams.status);
    const status = rawStatus === 'revoked' || rawStatus === 'all' ? rawStatus : 'active';
    const assignmentPath = '/api/v1/institutions/' + institutionId + '/role-assignments?includeRevoked=' + (status !== 'active' ? '1' : '0') + (offeringId ? '&offeringId=' + offeringId : '') + (roleId ? '&roleId=' + roleId : '');
    const [assignments, roles, rawOfferings, rawUsers] = await Promise.all([
        permitted<Assignment>(assignmentPath), permitted<Role>('/api/v1/institutions/' + institutionId + '/roles'),
        permitted<Offering>('/api/v1/institutions/' + institutionId + '/offerings'),
        session.isAdmin ? all<User>('/api/v1/admin/users') : Promise.resolve([] as User[]),
    ]);
    const offerings = allowedOfferingIds ? rawOfferings.filter(offering => allowedOfferingIds.includes(offering.id)) : rawOfferings;
    const filtered = assignments.filter(assignment => status !== 'revoked' || assignment.revokedAt !== null);
    const people = peopleItems(filtered, offerings).filter(person => !q || (person.user.firstName + ' ' + person.user.lastName + ' ' + person.user.email).toLowerCase().includes(q.toLowerCase()));
    people.sort((a, b) => (a.user.lastName + a.user.firstName).localeCompare(b.user.lastName + b.user.firstName) * (dir === 'asc' ? 1 : -1));
    const users = rawUsers.length ? rawUsers.map(user => ({ id: user.id, ...names(user), email: user.email })) : people.map(person => person.user);
    const availableRoles = roles.length ? roles : Array.from(new Map(assignments.map(assignment => [assignment.role.id, assignment.role])).values()).filter(role => role.scopeType === 'OFFERING');
    return <PeopleTable people={people.slice((page - 1) * 10, page * 10)} users={users} roles={availableRoles} offerings={offerings.map(offeringItem)} roleId={roleId} offeringId={offeringId} status={status} page={page} pageCount={Math.max(1, Math.ceil(people.length / 10))} total={people.length} sort={sort} dir={dir} endpoint={endpoint ?? '/api/v1/institutions/' + institutionId + '/role-assignments'} contextQuery={contextQuery} canManage={canManage ?? can(session, 'role_assignment.manage', institutionId)} roleCapabilities={roleCapabilities ?? { create: can(session, 'role_assignment.create', institutionId), revoke: can(session, 'role_assignment.revoke', institutionId) }} scopeCapabilities={scopeCapabilities ?? { view: can(session, 'scope_assignment.view', institutionId, offeringId), create: can(session, 'scope_assignment.create', institutionId, offeringId), revoke: can(session, 'scope_assignment.revoke', institutionId, offeringId) }} roleFilterParam={roleFilterParam} canManageProtectedRoles={allowProtectedRoles} />;
}
