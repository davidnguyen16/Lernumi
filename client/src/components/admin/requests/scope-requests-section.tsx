import { ScopeRequestsTable, type ScopeRequestListItem } from '@/components/admin/requests/scope-requests-table';
import { parseListParams, parseOptionalUuid } from '@/lib/admin/list-params';
import { permitted, requireSession, can, one, names, displayDate, type Query, type Role } from '@/lib/access-data';

type RequestRow = { id: string; status: ScopeRequestListItem['status']; requestedAt?: string; createdAt: string; reviewedAt?: string | null; processedAt?: string | null; reviewedById?: string | null; processedById?: string | null; approvedScopeAssignmentId: string | null; user: { id: string; name: string; email: string }; role: ScopeRequestListItem['role']; offering: ScopeRequestListItem['offering'] };
export async function ScopeRequestsSection({ institutionId, searchParams, actionBasePath, contextQuery = '', canReview, roleFilterParam = 'role' }: { institutionId: string; searchParams: Query; actionBasePath?: string; contextQuery?: string; canReview?: boolean; roleFilterParam?: string }) {
    const session = await requireSession();
    const { q, page, sort, dir } = parseListParams(searchParams, ['requested', 'user', 'role', 'subject', 'status'] as const);
    const roleId = parseOptionalUuid(searchParams[roleFilterParam]);
    const offeringId = parseOptionalUuid(searchParams.offering);
    const rawStatus = one(searchParams.status);
    const status = rawStatus === 'approved' || rawStatus === 'rejected' || rawStatus === 'all' ? rawStatus : 'pending';
    const base = '/api/v1/institutions/' + institutionId;
    const query = new URLSearchParams();
    if (status !== 'all') query.set('status', status.toUpperCase());
    if (offeringId) query.set('offeringId', offeringId);
    const [rows, roles] = await Promise.all([permitted<RequestRow>(base + '/scope-requests?' + query), permitted<Role>(base + '/roles')]);
    const order = (row: RequestRow) => sort === 'user' ? row.user.name : sort === 'role' ? row.role.roleName : sort === 'subject' ? row.offering.subjectCode : sort === 'status' ? row.status : row.requestedAt ?? row.createdAt;
    rows.sort((a, b) => order(a).localeCompare(order(b)) * (dir === 'asc' ? 1 : -1));
    const requests: ScopeRequestListItem[] = rows.filter(row => (!roleId || row.role.id === roleId) && (!q || (row.user.name + row.user.email + row.role.roleName + row.offering.subjectCode).toLowerCase().includes(q.toLowerCase()))).map(row => {
        const mayReview = canReview !== false && (session.isAdmin || row.user.id !== session.user.id);
        return { id: row.id, status: row.status, requested: displayDate(row.requestedAt ?? row.createdAt), processed: row.reviewedAt || row.processedAt ? displayDate(row.reviewedAt ?? row.processedAt) : null, user: { ...row.user, ...names(row.user) }, role: row.role, offering: row.offering, processedBy: row.processedById ?? row.reviewedById ?? null, approvedScopeAssignmentId: row.approvedScopeAssignmentId,
            canApprove: mayReview && can(session, 'scope_request.approve', institutionId, row.offering.id),
            canReject: mayReview && can(session, 'scope_request.reject', institutionId, row.offering.id) };
    });
    const roleOptions = roles.length ? roles : Array.from(new Map(rows.map(row => [row.role.id, row.role])).values());
    return <ScopeRequestsTable requests={requests.slice((page - 1) * 10, page * 10)} roles={roleOptions} roleId={roleId} status={status} page={page} pageCount={Math.max(1, Math.ceil(requests.length / 10))} total={requests.length} sort={sort} dir={dir} actionBasePath={actionBasePath ?? base + '/scope-requests'} contextQuery={contextQuery} canReview={requests.some(row => row.canApprove || row.canReject)} roleFilterParam={roleFilterParam} />;
}
