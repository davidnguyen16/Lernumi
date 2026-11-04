import { RequestsTable, type RequestListItem } from '@/components/admin/requests/requests-table';
import { parseListParams, parseOptionalUuid } from '@/lib/admin/list-params';
import { permitted, requireSession, can, one, names, displayDate, type Query, type Role } from '@/lib/access-data';

type RequestRow = { id: string; status: RequestListItem['status']; requestedAt?: string; createdAt: string; processedAt?: string | null; reviewedAt?: string | null; processedById?: string | null; reviewedById?: string | null; approvedAssignmentId: string | null; user: { id: string; name: string; email: string }; role: RequestListItem['role'] };
export async function RequestsSection({ institutionId, searchParams, actionBasePath, contextQuery = '', canReview, roleFilterParam = 'role' }: { institutionId: string; searchParams: Query; actionBasePath?: string; contextQuery?: string; canReview?: boolean; roleFilterParam?: string }) {
    const session = await requireSession();
    const { q, page, sort, dir } = parseListParams(searchParams, ['requested', 'user', 'role', 'status'] as const);
    const roleId = parseOptionalUuid(searchParams[roleFilterParam]);
    const rawStatus = one(searchParams.status);
    const status = rawStatus === 'approved' || rawStatus === 'rejected' || rawStatus === 'all' ? rawStatus : 'pending';
    const base = '/api/v1/institutions/' + institutionId;
    const [rows, roles] = await Promise.all([permitted<RequestRow>(base + '/role-requests' + (status === 'all' ? '' : '?status=' + status.toUpperCase())), permitted<Role>(base + '/roles')]);
    const order = (row: RequestRow) => sort === 'user' ? row.user.name : sort === 'role' ? row.role.roleName : sort === 'status' ? row.status : row.requestedAt ?? row.createdAt;
    rows.sort((a, b) => order(a).localeCompare(order(b)) * (dir === 'asc' ? 1 : -1));
    const requests: RequestListItem[] = rows.filter(row => (!roleId || row.role.id === roleId) && (!q || (row.user.name + row.user.email + row.role.roleName).toLowerCase().includes(q.toLowerCase()))).map(row => {
        const mayReview = canReview !== false && (session.isAdmin || row.user.id !== session.user.id);
        return { id: row.id, status: row.status, requested: displayDate(row.requestedAt ?? row.createdAt), processed: row.processedAt || row.reviewedAt ? displayDate(row.processedAt ?? row.reviewedAt) : null, user: { ...row.user, ...names(row.user) }, role: row.role, processedBy: row.processedById ?? row.reviewedById ?? null, approvedAssignmentId: row.approvedAssignmentId,
            canApprove: mayReview && can(session, 'role_request.approve', institutionId),
            canReject: mayReview && can(session, 'role_request.reject', institutionId) };
    });
    return <RequestsTable requests={requests.slice((page - 1) * 10, page * 10)} roles={roles} roleId={roleId} status={status} page={page} pageCount={Math.max(1, Math.ceil(requests.length / 10))} total={requests.length} sort={sort} dir={dir} actionBasePath={actionBasePath ?? base + '/role-requests'} contextQuery={contextQuery} canReview={requests.some(row => row.canApprove || row.canReject)} roleFilterParam={roleFilterParam} />;
}
