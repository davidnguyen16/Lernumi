import { TermPeriods } from '@/components/admin/academic-detail-pages';
export default async function InstitutionTermPeriodsPage({ params }: PageProps<'/admin/institutions/[id]/terms/[termId]'>) {
    const { id, termId } = await params;
    return <TermPeriods institutionId={id} termId={termId}/>;
}
