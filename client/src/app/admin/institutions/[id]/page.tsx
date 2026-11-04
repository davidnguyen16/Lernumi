import { InstitutionSetup } from '@/components/admin/academic-detail-pages';
export default async function InstitutionSetupPage({ params }: PageProps<'/admin/institutions/[id]'>) {
    const { id } = await params;
    return <InstitutionSetup institutionId={id}/>;
}
