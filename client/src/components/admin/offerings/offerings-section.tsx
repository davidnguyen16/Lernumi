import { OfferingsTable } from '@/components/admin/offerings/offerings-table';
import { parseListParams, parseOptionalUuid } from '@/lib/admin/list-params';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
import { academicAll, type AcademicOffering, type AcademicSubject, type AcademicTerm, type ApiPage } from '@/lib/admin/academic-data';
import { serverApi } from '@/lib/api/server';

export async function OfferingsSection({ institutionId, searchParams, endpoint, assessmentBasePath, detailActionLabel, contextQuery, capabilities, allowedOfferingIds, emptyMessage }: {
    institutionId: string; searchParams: Record<string, string | string[] | undefined>; endpoint?: string;
    assessmentBasePath?: string | null; detailActionLabel?: string; contextQuery?: string; capabilities?: CrudCapabilities;
    allowedOfferingIds?: readonly string[] | null; emptyMessage?: string;
}) {
    const { q, page, sort, dir } = parseListParams(searchParams, ['createdAt']);
    const subjectId = parseOptionalUuid(searchParams.subject);
    const termId = parseOptionalUuid(searchParams.term);
    const path = `/api/v1/institutions/${institutionId}/offerings`;
    const query = new URLSearchParams({ page: String(page), per_page: '10', search: q, sort: `${dir === 'desc' ? '-' : ''}${sort}` });
    if (subjectId) query.set('subjectId', subjectId);
    if (termId) query.set('termId', termId);
    let response: ApiPage<AcademicOffering>;
    let lookupOfferings: AcademicOffering[];
    if (allowedOfferingIds) {
        const scopeQuery = new URLSearchParams(query);
        scopeQuery.delete('page'); scopeQuery.delete('per_page');
        const scoped = (await academicAll<AcademicOffering>(`${path}?${scopeQuery}`)).filter(offering => allowedOfferingIds.includes(offering.id));
        lookupOfferings = scoped;
        response = { data: scoped.slice((page - 1) * 10, page * 10), meta: { current_page: page, per_page: 10, total: scoped.length, last_page: Math.max(1, Math.ceil(scoped.length / 10)) } };
    } else {
        response = await serverApi<ApiPage<AcademicOffering>>(`${path}?${query}`);
        lookupOfferings = response.data;
    }
    const canCreate = capabilities?.create ?? true;
    const [subjects, terms] = canCreate ? await Promise.all([
        academicAll<AcademicSubject>(`/api/v1/institutions/${institutionId}/subjects`),
        academicAll<AcademicTerm>(`/api/v1/institutions/${institutionId}/terms`),
    ]) : [[...new Map(lookupOfferings.map(offering => [offering.subject.id, offering.subject])).values()],
        [...new Map(lookupOfferings.map(offering => [offering.term.id, offering.term])).values()]];
    const offerings = response.data.filter(offering => !allowedOfferingIds || allowedOfferingIds.includes(offering.id))
        .map(offering => ({ ...offering, counts: { assessments: offering.assessmentCount ?? 0, assignments: offering.scopeAssignmentCount ?? 0 } }));
    return <OfferingsTable offerings={offerings} institutionId={institutionId} subjects={subjects} terms={terms} subjectId={subjectId} termId={termId}
        page={response.meta.current_page} pageCount={response.meta.last_page} total={response.meta.total} sort={sort} dir={dir}
        endpoint={endpoint ?? path} assessmentBasePath={assessmentBasePath} detailActionLabel={detailActionLabel} contextQuery={contextQuery}
        capabilities={capabilities} emptyMessage={emptyMessage}/>;
}
