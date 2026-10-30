import { TermsTable } from '@/components/admin/terms/terms-table';
import { parseListParams,parseOptionalUuid } from '@/lib/admin/list-params';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
export function TermsSection({ searchParams, lockedInstitutionId = null, endpoint, detailBasePath, contextQuery, capabilities, }: {
    searchParams: Record<string, string | string[] | undefined>;
    lockedInstitutionId?: string | null;
    endpoint?: string;
    detailBasePath?: string;
    contextQuery?: string;
    capabilities?: CrudCapabilities;
}) {
    const SORT_FIELDS = ['year', 'institution', 'periods', 'offerings'] as const;
    const { page, sort, dir } = parseListParams(searchParams, SORT_FIELDS);
    const institutionId = lockedInstitutionId ?? parseOptionalUuid(searchParams.institution);
    return (<TermsTable terms={[]} institutions={[]} institutionId={institutionId} locked={lockedInstitutionId !== null} page={page} pageCount={1} total={0} sort={sort} dir={dir} endpoint={endpoint} detailBasePath={detailBasePath} contextQuery={contextQuery} capabilities={capabilities}/>);
}
