import { UsersTable } from '@/components/admin/users/users-table';
import { parseListParams } from '@/lib/admin/list-params';
import { all, requireSession, names, displayDate, type User } from '@/lib/access-data';
export default async function AdminUsersPage({ searchParams }: PageProps<'/admin/users'>) {
    const session = await requireSession(); const { q, page, sort, dir } = parseListParams(await searchParams, ['lastName', 'createdAt', 'attempts', 'isSystemAdmin'] as const);
    const raw = (await all<User>('/api/v1/admin/users')).filter(user => !q || (user.name + user.email).toLowerCase().includes(q.toLowerCase()));
    const users = raw.map(user => ({ id: user.id, ...names(user), email: user.email, isSystemAdmin: user.isAdmin, isActive: user.isActive, joined: displayDate(user.createdAt), createdAt: user.createdAt, counts: user.counts ?? { roles: 0, attempts: 0 } }));
    users.sort((a, b) => { const left = sort === 'attempts' ? a.counts.attempts : sort === 'isSystemAdmin' ? Number(a.isSystemAdmin) : a[sort]; const right = sort === 'attempts' ? b.counts.attempts : sort === 'isSystemAdmin' ? Number(b.isSystemAdmin) : b[sort]; return (typeof left === 'number' && typeof right === 'number' ? left - right : String(left).localeCompare(String(right))) * (dir === 'asc' ? 1 : -1); });
    return <div className="space-y-6 pt-1"><section><h1 className="font-heading text-xl font-semibold">Users</h1><p className="text-muted-foreground mt-2 text-sm">Platform accounts and system administrator access. Institution roles are granted separately.</p></section><UsersTable users={users.slice((page - 1) * 10, page * 10)} currentUserId={session.user.id} page={page} pageCount={Math.max(1, Math.ceil(users.length / 10))} total={users.length} sort={sort} dir={dir} /></div>;
}
