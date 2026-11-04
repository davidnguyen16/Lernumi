import { PermissionsTable } from '@/components/admin/permissions/permissions-table';
import { parseListParams } from '@/lib/admin/list-params';
import { all, type Permission } from '@/lib/access-data';
export default async function AdminPermissionsPage({ searchParams }: PageProps<'/admin/permissions'>) {
    const { q, page, sort, dir } = parseListParams(await searchParams, ['permission', 'description'] as const);
    const permissions = (await all<Permission & { activeRoles: number }>('/api/v1/permissions')).filter(permission => !q || (permission.code + ' ' + permission.description).toLowerCase().includes(q.toLowerCase())).map(permission => ({ id: permission.code, permission: permission.code, description: permission.description, activeRoles: permission.activeRoles }));
    permissions.sort((a, b) => String(a[sort] ?? '').localeCompare(String(b[sort] ?? '')) * (dir === 'asc' ? 1 : -1));
    return <div className="space-y-6 pt-1"><section><h1 className="font-heading text-xl font-semibold">Permissions</h1><p className="text-muted-foreground mt-2 text-sm">The permission catalog is defined by the backend and can be assigned when composing roles.</p></section><PermissionsTable permissions={permissions.slice((page - 1) * 10, page * 10)} page={page} pageCount={Math.max(1, Math.ceil(permissions.length / 10))} total={permissions.length} sort={sort} dir={dir} /></div>;
}
