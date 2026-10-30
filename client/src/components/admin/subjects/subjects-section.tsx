import { SubjectsTable,type SubjectCapabilities } from '@/components/admin/subjects/subjects-table';
import { parseListParams,parseOptionalUuid } from '@/lib/admin/list-params';
export function SubjectsSection({ searchParams, lockedInstitutionId = null, endpoint, contextQuery, subjectBasePath, capabilities, }: {
    searchParams: Record<string, string | string[] | undefined>;
    lockedInstitutionId?: string | null;
    endpoint?: string;
    contextQuery?: string;
    subjectBasePath?: string;
    capabilities?: SubjectCapabilities;
}) {
    const SORT_FIELDS = ['subjectCode', 'subjectName', 'institution', 'offerings'] as const;
    const { page, sort, dir } = parseListParams(searchParams, SORT_FIELDS);
    const institutionId = lockedInstitutionId ?? parseOptionalUuid(searchParams.institution);
    return (<SubjectsTable subjects={[]} institutions={[]} institutionId={institutionId} locked={lockedInstitutionId !== null} page={page} pageCount={1} total={0} sort={sort} dir={dir} endpoint={endpoint} contextQuery={contextQuery} subjectBasePath={subjectBasePath} capabilities={capabilities}/>);
}
