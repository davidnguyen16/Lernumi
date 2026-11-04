import { InstitutionsTable } from '@/components/admin/institutions/institutions-table';
import { parseListParams } from '@/lib/admin/list-params';
import { serverApi } from '@/lib/api/server';
import type { AcademicInstitution, ApiPage } from '@/lib/admin/academic-data';
export default async function AdminInstitutionsPage({ searchParams }: PageProps<'/admin/institutions'>) {
    const { q, page, sort, dir } = parseListParams(await searchParams, ['name', 'subjects', 'terms']);
    const query = new URLSearchParams({ page: String(page), per_page: '10', search: q, sort: `${dir === 'desc' ? '-' : ''}name` });
    const response = await serverApi<ApiPage<AcademicInstitution>>(`/api/v1/admin/institutions?${query}`);
    const institutions = response.data.map(institution => ({ ...institution, counts: institution.counts ?? { subjects: 0, terms: 0, roles: 0, requests: 0 } }));
    return <div className="flex flex-col gap-6 pt-1"><section className="flex flex-col gap-1.5"><h1 className="font-heading text-xl font-semibold tracking-tight">Institutions</h1>
        <p className="text-muted-foreground max-w-2xl text-sm">Schools and universities using the platform. Every subject, term and role belongs to one of these.</p></section>
        <InstitutionsTable institutions={institutions} page={response.meta.current_page} pageCount={response.meta.last_page} total={response.meta.total} sort={sort} dir={dir} query={q}/></div>;
}
