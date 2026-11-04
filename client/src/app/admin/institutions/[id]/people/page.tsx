import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams, params }: PageProps<'/admin/institutions/[id]/people'>) {
    const { id } = await params;
    return <InstitutionAccessPage kind="people" searchParams={await searchParams} institutionId={id} admin />;
}
