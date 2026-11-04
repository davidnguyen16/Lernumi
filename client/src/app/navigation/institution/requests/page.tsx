import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/requests'>) {
    return <InstitutionAccessPage kind="requests" searchParams={await searchParams} />;
}
