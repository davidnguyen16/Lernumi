import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import Link from 'next/link';
const KINDS = ['all', 'role', 'scope', 'request', 'code', 'permission'] as const;
export default async function InstitutionAuditPage({ searchParams }: PageProps<'/navigation/institution/audit'>) {
    const query = await searchParams;
    const kind = typeof query.kind === 'string' ? query.kind : 'all';
    return <NavigationPage title="Audit history" description="Role, access and permission history."><nav className="mb-4 flex flex-wrap gap-1" aria-label="Audit event type">{KINDS.map((value) => <Link key={value} href={'/navigation/institution/audit?kind=' + value} className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), kind === value && 'bg-muted')}>{value === 'all' ? 'All events' : value[0].toUpperCase() + value.slice(1)}</Link>)}</nav><NavigationEmptyState title="No audit events" description="History will appear here when available."/></NavigationPage>;
}
