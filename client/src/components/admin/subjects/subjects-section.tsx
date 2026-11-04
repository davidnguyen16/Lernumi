import { SubjectsTable, type SubjectCapabilities } from '@/components/admin/subjects/subjects-table';
import { parseListParams, parseOptionalUuid } from '@/lib/admin/list-params';
import { academicAll, academicInstitutions, type AcademicOffering, type AcademicSubject, type ApiPage } from '@/lib/admin/academic-data';
import { serverApi } from '@/lib/api/server';

export async function SubjectsSection({ searchParams, lockedInstitutionId = null, endpoint, contextQuery, subjectBasePath, capabilities }: {
    searchParams: Record<string, string | string[] | undefined>; lockedInstitutionId?: string | null;
    endpoint?: string; contextQuery?: string; subjectBasePath?: string; capabilities?: SubjectCapabilities;
}) {
    const { q, page, sort, dir } = parseListParams(searchParams, ['subjectCode', 'subjectName', 'institution', 'offerings']);
    const institutionId = lockedInstitutionId ?? parseOptionalUuid(searchParams.institution);
    const institutions = await academicInstitutions();
    const path = institutionId ? `/api/v1/institutions/${institutionId}/subjects` : '/api/v1/admin/subjects';
    const query = new URLSearchParams({ page: String(page), per_page: '10', search: q,
        sort: `${dir === 'desc' ? '-' : ''}${sort === 'subjectName' ? 'name' : 'subjectCode'}` });
    const response = capabilities?.view === false ? null : await serverApi<ApiPage<AcademicSubject>>(`${path}?${query}`);
    const offerings = response?.data.some(subject => subject.offeringCount === undefined)
        ? await academicAll<AcademicOffering>(institutionId ? `/api/v1/institutions/${institutionId}/offerings` : '/api/v1/admin/offerings') : [];
    const subjects = (response?.data ?? []).map(subject => ({ ...subject,
        institution: institutions.find(item => item.id === subject.institutionId) ?? { id: subject.institutionId, name: 'Institution' },
        counts: { offerings: subject.offeringCount ?? offerings.filter(item => item.subjectId === subject.id).length } }));
    return <SubjectsTable subjects={subjects} institutions={institutions} institutionId={institutionId} locked={lockedInstitutionId !== null}
        page={response?.meta.current_page ?? page} pageCount={response?.meta.last_page ?? 1} total={response?.meta.total ?? 0} sort={sort} dir={dir}
        endpoint={endpoint ?? path} contextQuery={contextQuery} subjectBasePath={subjectBasePath}
        capabilities={capabilities ?? { create: institutionId !== null, view: true, update: institutionId !== null, delete: institutionId !== null }}/>;
}
