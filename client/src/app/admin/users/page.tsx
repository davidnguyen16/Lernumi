import { UsersTable } from '@/components/admin/users/users-table';
import { parseListParams } from '@/lib/admin/list-params';
const SORT_FIELDS = ['lastName', 'createdAt', 'attempts', 'isSystemAdmin'] as const;
export default async function AdminUsersPage({ searchParams }: PageProps<'/admin/users'>) {
    const { page, sort, dir } = parseListParams(await searchParams, SORT_FIELDS);
    return (<div className="flex flex-col gap-6 pt-1">
            <section className="flex flex-col gap-1.5">
                <h1 className="font-heading text-xl font-semibold tracking-tight">Users</h1>
                <p className="text-muted-foreground max-w-2xl text-sm">
                    Every account on the platform. System admins reach this area; roles at an
                    institution are granted separately.
                </p>
            </section>

            <UsersTable users={[]} currentUserId="" page={page} pageCount={1} total={0} sort={sort} dir={dir}/>
        </div>);
}
