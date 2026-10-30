import { ScopeRequestsTable } from '@/components/admin/requests/scope-requests-table';
import { parseListParams,parseOptionalUuid } from '@/lib/admin/list-params';
const STATUS_VALUES = ['pending', 'approved', 'rejected', 'all'] as const;
export function ScopeRequestsSection({ searchParams, actionBasePath, contextQuery, canReview, roleFilterParam = 'role' }: {
    institutionId: string;
    searchParams: Record<string, string | string[] | undefined>;
    actionBasePath?: string;
    contextQuery?: string;
    canReview?: boolean;
    roleFilterParam?: string;
}) {
    const SORT_FIELDS = ['requested', 'user', 'role', 'subject', 'status'] as const;
    const { page, sort, dir } = parseListParams(searchParams, SORT_FIELDS);
    const roleId = parseOptionalUuid(searchParams[roleFilterParam]);
    const rawStatus = Array.isArray(searchParams.status)
        ? searchParams.status[0]
        : searchParams.status;
    const status = STATUS_VALUES.includes(rawStatus as (typeof STATUS_VALUES)[number])
        ? (rawStatus as (typeof STATUS_VALUES)[number])
        : 'pending';
    return (<ScopeRequestsTable requests={[]} roles={[]} roleId={roleId} status={status} page={page} pageCount={1} total={0} sort={sort} dir={dir} actionBasePath={actionBasePath} contextQuery={contextQuery} canReview={canReview} roleFilterParam={roleFilterParam}/>);
}
