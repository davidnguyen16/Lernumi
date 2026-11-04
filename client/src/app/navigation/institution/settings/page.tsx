import { InstitutionAccessPage } from '@/components/navigation/institution-access-page';
export default async function Page({ searchParams }: PageProps<'/navigation/institution/settings'>) {
    return <InstitutionAccessPage kind="settings" searchParams={await searchParams} />;
}
