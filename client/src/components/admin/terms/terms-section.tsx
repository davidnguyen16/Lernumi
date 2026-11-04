import { TermsTable } from '@/components/admin/terms/terms-table';
import { parseListParams, parseOptionalUuid } from '@/lib/admin/list-params';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
import { academicAll, academicDate, academicInstitutions, type AcademicOffering, type AcademicTerm, type ApiPage } from '@/lib/admin/academic-data';
import { serverApi } from '@/lib/api/server';

export async function TermsSection({ searchParams, lockedInstitutionId = null, endpoint, detailBasePath, contextQuery, capabilities }: {
    searchParams: Record<string, string | string[] | undefined>; lockedInstitutionId?: string | null;
    endpoint?: string; detailBasePath?: string; contextQuery?: string; capabilities?: CrudCapabilities;
}) {
    const { q, page, sort, dir } = parseListParams(searchParams, ['year', 'institution', 'periods', 'offerings']);
    const institutionId = lockedInstitutionId ?? parseOptionalUuid(searchParams.institution);
    const institutions = await academicInstitutions();
    const path = institutionId ? `/api/v1/institutions/${institutionId}/terms` : '/api/v1/admin/terms';
    const query = new URLSearchParams({ page: String(page), per_page: '10', sort: `${dir === 'desc' ? '-' : ''}year` });
    if (/^\d{4}$/.test(q)) query.set('year', q);
    const response = capabilities?.view === false || (q !== '' && !/^\d{4}$/.test(q)) ? null : await serverApi<ApiPage<AcademicTerm>>(`${path}?${query}`);
    const offerings = response?.data.some(term => term.offeringCount === undefined)
        ? await academicAll<AcademicOffering>(institutionId ? `/api/v1/institutions/${institutionId}/offerings` : '/api/v1/admin/offerings') : [];
    const terms = (response?.data ?? []).map(term => {
        const periods = term.periods ?? [];
        const dates = periods.flatMap(period => [period.startAt, period.endAt]).sort();
        return { ...term, institution: institutions.find(item => item.id === term.institutionId) ?? { id: term.institutionId, name: 'Institution' },
            counts: { periods: periods.length, offerings: term.offeringCount ?? offerings.filter(item => item.termId === term.id).length },
            span: dates.length ? `${academicDate(dates[0])} – ${academicDate(dates[dates.length - 1])}` : null };
    });
    return <TermsTable terms={terms} institutions={institutions} institutionId={institutionId} locked={lockedInstitutionId !== null}
        page={response?.meta.current_page ?? page} pageCount={response?.meta.last_page ?? 1} total={response?.meta.total ?? 0} sort={sort} dir={dir}
        endpoint={endpoint ?? path} detailBasePath={detailBasePath} contextQuery={contextQuery}
        capabilities={capabilities ?? { create: institutionId !== null, view: true, update: institutionId !== null, delete: institutionId !== null }}/>;
}
