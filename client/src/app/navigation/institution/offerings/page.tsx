import { OfferingsSection } from '@/components/admin/offerings/offerings-section';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Subject offerings" description="Scheduled subject deliveries and assessments."><OfferingsSection institutionId="" searchParams={{}} endpoint="" contextQuery="" assessmentBasePath="/navigation/institution/offerings" capabilities={{ create: false, view: true, update: false, delete: false }}/></NavigationPage>;
}
