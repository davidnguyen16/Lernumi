import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/audit'>) {
    return <InstitutionAccessPage kind="audit" searchParams={await searchParams} />;
}
