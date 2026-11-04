import { SubjectsSection } from '@/components/admin/subjects/subjects-section';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { academicCapabilities, academicContext } from '@/lib/admin/academic-data';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/subjects'>) {
    const params = await searchParams;
    const context = await academicContext(params);
    return <NavigationPage title="Subjects" description="Manage this institution's subject catalog.">{context.institution
        ? <SubjectsSection searchParams={params} lockedInstitutionId={context.institution.id} contextQuery={context.query} subjectBasePath="/navigation/institution/subjects"
            capabilities={academicCapabilities(context.session, 'subject', context.institution.id, undefined, context.assignment?.roleAssignmentId)}/>
        : <NavigationEmptyState title="Choose an institution" description="An active institution role is needed to manage subjects."/>}</NavigationPage>;
}
