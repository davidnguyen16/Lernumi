import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/people'>) {
    return <InstitutionAccessPage kind="people" searchParams={await searchParams} />;
}
