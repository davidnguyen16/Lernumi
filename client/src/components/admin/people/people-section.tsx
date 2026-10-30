import { PeopleTable } from '@/components/admin/people/people-table';
import { parseListParams,parseOptionalUuid } from '@/lib/admin/list-params';
const STATUS_VALUES = ['active', 'revoked', 'all'] as const;
export function PeopleSection({ searchParams, endpoint, contextQuery, canManage, roleCapabilities, scopeCapabilities, roleFilterParam = 'role', allowProtectedRoles = true }: {
    institutionId: string;
    searchParams: Record<string, string | string[] | undefined>;
    endpoint?: string;
    contextQuery?: string;
    canManage?: boolean;
    roleCapabilities?: {
        create: boolean;
        revoke: boolean;
    };
    scopeCapabilities?: {
        view: boolean;
        create: boolean;
        revoke: boolean;
    };
    roleFilterParam?: string;
    allowedOfferingIds?: readonly string[] | null;
    allowProtectedRoles?: boolean;
}) {
    const SORT_FIELDS = ['user'] as const;
    const { page, sort, dir } = parseListParams(searchParams, SORT_FIELDS);
    const roleId = parseOptionalUuid(searchParams[roleFilterParam]);
    const offeringId = parseOptionalUuid(searchParams.offering);
    const rawStatus = Array.isArray(searchParams.status)
        ? searchParams.status[0]
        : searchParams.status;
    const status = STATUS_VALUES.includes(rawStatus as (typeof STATUS_VALUES)[number])
        ? (rawStatus as (typeof STATUS_VALUES)[number])
        : 'active';
    return (<PeopleTable people={[]} users={[]} roles={[]} offerings={[]} roleId={roleId} offeringId={offeringId} status={status} page={page} pageCount={1} total={0} sort={sort} dir={dir} endpoint={endpoint} contextQuery={contextQuery} canManage={canManage} roleCapabilities={roleCapabilities} scopeCapabilities={scopeCapabilities} roleFilterParam={roleFilterParam} canManageProtectedRoles={allowProtectedRoles}/>);
}
