import { OfferingsSection } from '@/components/admin/offerings/offerings-section';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { academicContext, academicListCapabilities } from '@/lib/admin/academic-data';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/offerings'>) {
    const params = await searchParams;
    const context = await academicContext(params);
    return <NavigationPage title="Subject offerings" description="Scheduled subject deliveries and assessments.">{context.institution
        ? <OfferingsSection institutionId={context.institution.id} searchParams={params} contextQuery={context.query} assessmentBasePath="/navigation/institution/offerings"
            capabilities={academicListCapabilities(context.session, 'offering', context.institution.id, context.assignment?.roleAssignmentId)}
            allowedOfferingIds={!context.session.isAdmin && context.assignment?.role.scopeType === 'OFFERING' ? context.assignment.offeringIds : null}/>
        : <NavigationEmptyState title="Choose an institution" description="Join an institution or select a role to view its offerings."/>}</NavigationPage>;
}
