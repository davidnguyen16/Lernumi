import { SubjectOverview } from '@/components/admin/academic-detail-pages';
export default async function Page({ params, searchParams }: PageProps<'/navigation/institution/subjects/[subjectId]'>) {
    const { subjectId } = await params;
    return <SubjectOverview subjectId={subjectId} searchParams={await searchParams}/>;
}
