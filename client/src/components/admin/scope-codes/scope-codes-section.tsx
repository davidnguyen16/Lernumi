import { ScopeCodesTable, type ScopeCodeListItem } from '@/components/admin/scope-codes/scope-codes-table';
import { can, requireSession, permitted, offeringItem, displayDate, type Offering } from '@/lib/access-data';
export type ScopeCodeCapabilities = { view: boolean; create: boolean; revoke: boolean };
type Code = { id: string; offeringId: string; createdById: string; createdAt: string; usedAt: string | null; revokedAt: string | null; expiresAt: string };
export async function ScopeCodesSection({ institutionId, endpoint, contextQuery, capabilities }: { institutionId: string; endpoint: string; contextQuery: string; capabilities: ScopeCodeCapabilities }) {
    const session = await requireSession();
    const [rows, offerings] = await Promise.all([capabilities.view ? permitted<Code>('/api/v1/institutions/' + institutionId + '/scope-codes') : Promise.resolve([] as Code[]), permitted<Offering>('/api/v1/institutions/' + institutionId + '/requestable-offerings')]);
    const codes: ScopeCodeListItem[] = rows.flatMap(row => { const offering = offerings.find(item => item.id === row.offeringId); return offering ? [{ id: row.id, created: displayDate(row.createdAt), used: row.usedAt, revoked: row.revokedAt, offering: offeringItem(offering), createdBy: { firstName: row.createdById, lastName: '', email: '' }, usedBy: null, usedByRole: null,
        canRevoke: capabilities.revoke && can(session, 'scope_assignment_code.revoke', institutionId, row.offeringId) }] : []; });
    const targets = offerings.filter(offering => capabilities.create && can(session, 'scope_assignment_code.create', institutionId, offering.id));
    return <ScopeCodesTable codes={codes} offerings={targets.map(offeringItem)} endpoint={endpoint} contextQuery={contextQuery} capabilities={capabilities} />;
}
