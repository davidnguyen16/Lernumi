import { TermsSection } from '@/components/admin/terms/terms-section';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Terms" description="Study terms and calendar periods."><TermsSection searchParams={{}} lockedInstitutionId="" endpoint="" detailBasePath="/navigation/institution/terms" contextQuery="" capabilities={{ create: false, view: true, update: false, delete: false }}/></NavigationPage>;
}
