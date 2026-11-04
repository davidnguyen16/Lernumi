import Link from 'next/link';
import { NavigationPage, NavigationEmptyState } from '@/components/navigation/page-shell';
import { RolesSection } from '@/components/admin/roles/roles-section';
import { PeopleSection } from '@/components/admin/people/people-section';
import { RequestsSection } from '@/components/admin/requests/requests-section';
import { ScopeRequestsSection } from '@/components/admin/requests/scope-requests-section';
import { ScopeCodesSection } from '@/components/admin/scope-codes/scope-codes-section';
import { InstitutionOverview } from '@/components/admin/institutions/institution-overview';
import { PictureField } from '@/components/media/picture-field';
import { accessContext, all, can, one, displayDate, type Query } from '@/lib/access-data';

type Kind = 'settings' | 'roles' | 'people' | 'requests' | 'audit';
const titles = { settings: 'Institution profile', roles: 'Roles', people: 'People', requests: 'Access requests', audit: 'Audit history' };
type Audit = { id: string; actorId: string | null; action: string; subjectId: string | null; metadata: unknown; createdAt: string };
export async function InstitutionAccessPage({ kind, searchParams, institutionId: explicitId, admin = false }: { kind: Kind; searchParams: Query; institutionId?: string; admin?: boolean }) {
    const state = await accessContext(explicitId ? { ...searchParams, institution: explicitId } : searchParams);
    const id = explicitId ?? state.institutionId;
    const institution = state.institutions.find(item => item.id === id);
    if (!id || !institution) return <NavigationPage title={titles[kind]} description="Institution administration"><NavigationEmptyState title="No institution selected" description="Join an institution or select a role to continue." /></NavigationPage>;
    const base = '/api/v1/institutions/' + id;
    const offeringId = one(searchParams.offering) ?? (kind === 'people' && state.context?.role.scopeType === 'OFFERING'
        ? state.context.offeringIds.find(offering => can(state.session, 'role_assignment.view', id, offering)) : undefined);
    const query = offeringId ? { ...searchParams, offering: offeringId } : searchParams;
    const scopedCan = (permission: string) => can(state.session, permission, id)
        || (offeringId ? can(state.session, permission, id, offeringId)
            : state.session.contexts.filter(context => context.institution.id === id)
                .some(context => context.offeringIds.some(offering => can(state.session, permission, id, offering))));
    let content;
    if (kind === 'roles') content = scopedCan('role.view') ? <RolesSection searchParams={query} lockedInstitutionId={id} /> : <NavigationEmptyState title="Role access required" description="Your active role cannot manage institution roles." />;
    else if (kind === 'people') content = scopedCan('role_assignment.view') ? <PeopleSection institutionId={id} searchParams={query} roleFilterParam="filterRole" endpoint={base + '/role-assignments'} roleCapabilities={{ create: scopedCan('role_assignment.create'), revoke: can(state.session, 'role_assignment.revoke', id) }} scopeCapabilities={{ view: scopedCan('scope_assignment.view'), create: scopedCan('scope_assignment.create'), revoke: scopedCan('scope_assignment.revoke') }} /> : <NavigationEmptyState title="Member access required" description="Your active role cannot inspect role assignments." />;
    else if (kind === 'requests') content = <div className="space-y-9">{can(state.session, 'role_request.view', id) && <section><h2 className="font-heading mb-4 text-lg font-semibold">Role requests</h2><RequestsSection institutionId={id} searchParams={query} roleFilterParam="filterRole" /></section>}{scopedCan('scope_request.view') && <section><h2 className="font-heading mb-4 text-lg font-semibold">Subject access requests</h2><ScopeRequestsSection institutionId={id} searchParams={query} roleFilterParam="filterRole" /></section>}{(scopedCan('scope_assignment_code.view') || scopedCan('scope_assignment_code.create')) && <section><h2 className="font-heading mb-4 text-lg font-semibold">One-time access codes</h2><ScopeCodesSection institutionId={id} endpoint={base + '/scope-codes'} contextQuery="" capabilities={{ view: scopedCan('scope_assignment_code.view'), create: scopedCan('scope_assignment_code.create'), revoke: scopedCan('scope_assignment_code.revoke') }} /></section>}{!can(state.session, 'role_request.view', id) && !scopedCan('scope_request.view') && !scopedCan('scope_assignment_code.view') && !scopedCan('scope_assignment_code.create') && <NavigationEmptyState title="Request access required" description="Your role cannot review membership requests or generate access codes." />}</div>;
    else if (kind === 'settings') content = <InstitutionOverview institution={institution} canEdit={can(state.session, 'institution.settings.update', id)} editEndpoint={base} heading={<div><h2 className="font-heading text-2xl font-semibold">{institution.name}</h2>{institution.website && <a href={institution.website} target="_blank" rel="noreferrer" className="text-primary text-sm underline">{institution.website}</a>}</div>} logo={<PictureField pictureKey={institution.logoUrl ?? null} fallback={institution.name.at(0) ?? '?'} alt={institution.name + ' logo'} rounded="lg" endpoint={can(state.session, 'institution.settings.update', id) ? base + '/logo' : undefined} fileField="logo" />}><p className="text-muted-foreground text-sm">Institution details are shared across its subjects, terms and offerings.</p></InstitutionOverview>;
    else if (can(state.session, 'audit.view', id)) {
        const rows = await all<Audit>(base + '/audit');
        const kindFilter = one(searchParams.kind) ?? 'all';
        const filtered = rows.filter(row => kindFilter === 'all' || row.action.startsWith(kindFilter));
        const page = Math.max(1, Number(one(searchParams.page)) || 1);
        content = <div className="space-y-4"><nav className="flex flex-wrap gap-3 text-sm" aria-label="Audit event type">{['all', 'role', 'scope'].map(value => { const params = new URLSearchParams({ institution: id, kind: value }); return <Link key={value} href={'?' + params} className={kindFilter === value ? 'text-primary font-medium' : 'text-muted-foreground'}>{value === 'all' ? 'All events' : value}</Link>; })}</nav>{filtered.length ? <ul className="divide-y rounded-xl border px-5">{filtered.slice((page - 1) * 20, page * 20).map(row => <li key={row.id} className="space-y-2 py-4"><p className="font-mono text-sm">{row.action}</p><p className="text-muted-foreground text-xs">{displayDate(row.createdAt)} · Actor: {row.actorId ?? 'System'}</p>{row.subjectId && <p className="text-muted-foreground break-all text-xs">Record: {row.subjectId}</p>}<pre className="bg-muted overflow-auto rounded p-3 text-xs">{JSON.stringify(row.metadata, null, 2)}</pre></li>)}</ul> : <NavigationEmptyState title="No audit events" description="Access changes appear here after they are made." />}<div className="flex gap-4 text-sm">{page > 1 && <Link href={'?institution=' + id + '&kind=' + kindFilter + '&page=' + (page - 1)}>Previous</Link>}{page * 20 < filtered.length && <Link href={'?institution=' + id + '&kind=' + kindFilter + '&page=' + (page + 1)}>Next</Link>}</div></div>;
    } else content = <NavigationEmptyState title="Audit access required" description="Your role cannot inspect institution audit history." />;
    return admin ? <div className="space-y-5 pt-2">{content}</div> : <NavigationPage title={titles[kind]} description={institution.name}>{content}</NavigationPage>;
}
