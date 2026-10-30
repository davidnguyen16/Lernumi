import { RequestsSection } from '@/components/admin/requests/requests-section';
import { ScopeRequestsSection } from '@/components/admin/requests/scope-requests-section';
import { ScopeCodesSection } from '@/components/admin/scope-codes/scope-codes-section';
import { NavigationPage } from '@/components/navigation/page-shell';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import Link from 'next/link';
export default async function InstitutionRequestsPage({ searchParams }: PageProps<'/navigation/institution/requests'>) {
    const query = await searchParams;
    const kind = query.kind === 'scope' || query.kind === 'code' ? query.kind : 'role';
    return <NavigationPage title="Access requests" description="Role membership and subject access requests."><nav className="mb-4 flex w-fit gap-1 rounded-lg border p-1" aria-label="Request type">{[{ value: 'role', label: 'Role membership' }, { value: 'scope', label: 'Subject access' }, { value: 'code', label: 'Access codes' }].map((item) => <Link key={item.value} href={'/navigation/institution/requests?kind=' + item.value} className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), kind === item.value && 'bg-muted')}>{item.label}</Link>)}</nav>{kind === 'code' ? <ScopeCodesSection institutionId="" endpoint="" contextQuery="" capabilities={{ view: true, create: false, revoke: false }}/> : kind === 'scope' ? <ScopeRequestsSection institutionId="" searchParams={query} actionBasePath="" contextQuery="" canReview={false}/> : <RequestsSection institutionId="" searchParams={query} actionBasePath="" contextQuery="" canReview={false}/>}</NavigationPage>;
}
