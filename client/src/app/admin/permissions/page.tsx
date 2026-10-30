import { PermissionsTable } from '@/components/admin/permissions/permissions-table';
import { parseListParams } from '@/lib/admin/list-params';
const SORT_FIELDS = ['permission', 'description'] as const;
export default async function AdminPermissionsPage({ searchParams, }: PageProps<'/admin/permissions'>) {
    const { page, sort, dir } = parseListParams(await searchParams, SORT_FIELDS);
    return (<div className="flex flex-col gap-6 pt-1">
            <section className="flex flex-col gap-1.5">
                <h1 className="font-heading text-xl font-semibold tracking-tight">Permissions</h1>
                <p className="text-muted-foreground max-w-2xl text-sm">
                    Actions recognised by the platform and available when composing roles. This
                    vocabulary is defined in code and is read-only here.
                </p>
            </section>

            <PermissionsTable permissions={[]} page={page} pageCount={1} total={0} sort={sort} dir={dir}/>
        </div>);
}
