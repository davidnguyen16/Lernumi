import { InstitutionsTable } from '@/components/admin/institutions/institutions-table';
import { parseListParams } from '@/lib/admin/list-params';
const SORT_FIELDS = ['name', 'subjects', 'terms'] as const;
export default async function AdminInstitutionsPage({ searchParams, }: PageProps<'/admin/institutions'>) {
    const { q, page, sort, dir } = parseListParams(await searchParams, SORT_FIELDS);
    return (<div className="flex flex-col gap-6 pt-1">
            <section className="flex flex-col gap-1.5">
                <h1 className="font-heading text-xl font-semibold tracking-tight">Institutions</h1>
                <p className="text-muted-foreground max-w-2xl text-sm">
                    Schools and universities using the platform. Every subject, term and role
                    belongs to one of these.
                </p>
            </section>

            <InstitutionsTable institutions={[]} page={page} pageCount={1} total={0} sort={sort} dir={dir} query={q}/>
        </div>);
}
