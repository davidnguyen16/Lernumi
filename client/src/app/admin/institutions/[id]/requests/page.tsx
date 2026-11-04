import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams, params }: PageProps<'/admin/institutions/[id]/requests'>) {
    const { id } = await params;
    return <InstitutionAccessPage kind="requests" searchParams={await searchParams} institutionId={id} admin />;
}
