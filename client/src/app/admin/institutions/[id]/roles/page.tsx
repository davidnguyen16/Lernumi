import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams, params }: PageProps<'/admin/institutions/[id]/roles'>) {
    const { id } = await params;
    return <InstitutionAccessPage kind="roles" searchParams={await searchParams} institutionId={id} admin />;
}
