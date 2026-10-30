import { ToolsTable } from '@/components/admin/tools/tools-table';
import { parseListParams } from '@/lib/admin/list-params';
const SORT_FIELDS = ['name', 'code', 'versions', 'assessments'] as const;
export default async function AdminToolsPage({ searchParams }: PageProps<'/admin/tools'>) {
    const { page, sort, dir } = parseListParams(await searchParams, SORT_FIELDS);
    return (<div className="flex flex-col gap-6 pt-1">
            <section className="flex flex-col gap-1.5">
                <h1 className="font-heading text-xl font-semibold tracking-tight">Tools</h1>
                <p className="text-muted-foreground max-w-2xl text-sm">
                    Independent web applications available to assessments. Uploads create immutable
                    versions so old sessions keep the code they were recorded with.
                </p>
            </section>

            <ToolsTable tools={[]} page={page} pageCount={1} total={0} sort={sort} dir={dir}/>
        </div>);
}
