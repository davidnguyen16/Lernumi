import { OfferingAssessments } from '@/components/admin/academic-detail-pages';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { academicContext } from '@/lib/admin/academic-data';
export default async function Page({ params, searchParams }: PageProps<'/navigation/institution/offerings/[offeringId]'>) {
    const { offeringId } = await params;
    const query = await searchParams;
    const context = await academicContext(query);
    if (!context.institution) return <NavigationPage title="Assessments"><NavigationEmptyState title="Choose an institution"/></NavigationPage>;
    return <OfferingAssessments institutionId={context.institution.id} offeringId={offeringId} searchParams={query} navigation/>;
}
