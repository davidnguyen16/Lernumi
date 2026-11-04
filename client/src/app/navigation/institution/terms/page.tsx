import { TermsSection } from '@/components/admin/terms/terms-section';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { academicCapabilities, academicContext } from '@/lib/admin/academic-data';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/terms'>) {
    const params = await searchParams;
    const context = await academicContext(params);
    return <NavigationPage title="Terms" description="Study terms and calendar periods.">{context.institution
        ? <TermsSection searchParams={params} lockedInstitutionId={context.institution.id} contextQuery={context.query} detailBasePath="/navigation/institution/terms"
            capabilities={academicCapabilities(context.session, 'term', context.institution.id, undefined, context.assignment?.roleAssignmentId)}/>
        : <NavigationEmptyState title="Choose an institution" description="An active institution role is needed to manage study terms."/>}</NavigationPage>;
}
