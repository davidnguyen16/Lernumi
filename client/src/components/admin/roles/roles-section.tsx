import { RolesTable } from '@/components/admin/roles/roles-table';
import { NavigationEmptyState } from '@/components/navigation/page-shell';
import { parseListParams } from '@/lib/admin/list-params';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
import { accessContext, all, permitted, roleItem, roleOptions, can, type Query, type Role, type Permission } from '@/lib/access-data';

export async function RolesSection({ searchParams, lockedInstitutionId = null, endpoint, contextQuery = '', capabilities }: {
    searchParams: Query; lockedInstitutionId?: string | null; endpoint?: string; contextQuery?: string; capabilities?: CrudCapabilities;
}) {
    const state = await accessContext(lockedInstitutionId ? { ...searchParams, institution: lockedInstitutionId } : searchParams);
    const institutionId = lockedInstitutionId ?? state.institutionId;
    if (!institutionId) return <NavigationEmptyState title="No institution selected" description="Create or join an institution to manage roles." />;
    const { q, page, sort, dir } = parseListParams(searchParams, ['roleName', 'roleCode', 'scopeType', 'institution'] as const);
    const institutions = lockedInstitutionId ? state.institutions.filter(item => item.id === lockedInstitutionId) : state.institutions;
    const [roleGroups, permissions] = await Promise.all([
        Promise.all(institutions.map(async institution => ({ institution, roles: await permitted<Role>('/api/v1/institutions/' + institution.id + '/roles') }))),
        all<Permission>('/api/v1/permissions'),
    ]);
    const selectedGroups = lockedInstitutionId || searchParams.institution ? roleGroups.filter(group => group.institution.id === institutionId) : roleGroups;
    const roles = selectedGroups.flatMap(group => group.roles.map(role => roleItem(role, group.institution, group.roles, permissions)))
        .filter(role => !q || (role.roleCode + ' ' + role.roleName + ' ' + role.institution.name).toLowerCase().includes(q.toLowerCase()));
    roles.sort((a, b) => { const left = sort === 'institution' ? a.institution.name : a[sort]; const right = sort === 'institution' ? b.institution.name : b[sort]; return left.localeCompare(right) * (dir === 'asc' ? 1 : -1); });
    const actualCapabilities = capabilities ?? { create: can(state.session, 'role.create', institutionId), view: can(state.session, 'role.view', institutionId), update: can(state.session, 'role.update', institutionId), delete: can(state.session, 'role.delete', institutionId) };
    return <RolesTable roles={roles.slice((page - 1) * 10, page * 10)} institutions={institutions} institutionId={lockedInstitutionId ?? (typeof searchParams.institution === 'string' ? searchParams.institution : null)} locked={lockedInstitutionId !== null} rolesByInstitution={Object.fromEntries(roleGroups.map(group => [group.institution.id, roleOptions(group.roles)]))} permissions={permissions.map(permission => ({ id: permission.code, permission: permission.code, description: permission.description }))} page={page} pageCount={Math.max(1, Math.ceil(roles.length / 10))} total={roles.length} sort={sort} dir={dir} endpoint={endpoint ?? '/api/v1/institutions/' + institutionId + '/roles'} contextQuery={contextQuery} capabilities={actualCapabilities} />;
}
