import { TermPeriods } from '@/components/admin/academic-detail-pages';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { academicContext } from '@/lib/admin/academic-data';
export default async function Page({ params, searchParams }: PageProps<'/navigation/institution/terms/[termId]'>) {
    const { termId } = await params;
    const query = await searchParams;
    const context = await academicContext(query);
    if (!context.institution) return <NavigationPage title="Term periods"><NavigationEmptyState title="Choose an institution"/></NavigationPage>;
    return <TermPeriods institutionId={context.institution.id} termId={termId} searchParams={query} navigation/>;
}
