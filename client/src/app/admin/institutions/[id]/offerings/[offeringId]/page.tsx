import { OfferingAssessments } from '@/components/admin/academic-detail-pages';
export default async function InstitutionOfferingAssessmentsPage({ params }: PageProps<'/admin/institutions/[id]/offerings/[offeringId]'>) {
    const { id, offeringId } = await params;
    return <OfferingAssessments institutionId={id} offeringId={offeringId}/>;
}
