import { RolesTable } from '@/components/admin/roles/roles-table';
import { parseListParams,parseOptionalUuid } from '@/lib/admin/list-params';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
export function RolesSection({ searchParams, lockedInstitutionId = null, endpoint, contextQuery, capabilities, }: {
    searchParams: Record<string, string | string[] | undefined>;
    lockedInstitutionId?: string | null;
    endpoint?: string;
    contextQuery?: string;
    capabilities?: CrudCapabilities;
}) {
    const SORT_FIELDS = ['roleName', 'roleCode', 'scopeType', 'institution'] as const;
    const { page, sort, dir } = parseListParams(searchParams, SORT_FIELDS);
    const institutionId = lockedInstitutionId ?? parseOptionalUuid(searchParams.institution);
    return (<RolesTable roles={[]} institutions={[]} institutionId={institutionId} locked={lockedInstitutionId !== null} rolesByInstitution={{}} permissions={[]} page={page} pageCount={1} total={0} sort={sort} dir={dir} endpoint={endpoint} contextQuery={contextQuery} capabilities={capabilities}/>);
}
