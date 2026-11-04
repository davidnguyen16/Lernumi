import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/roles'>) {
    return <InstitutionAccessPage kind="roles" searchParams={await searchParams} />;
}
