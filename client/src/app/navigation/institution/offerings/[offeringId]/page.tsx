import { AssessmentsTable } from '@/components/admin/offerings/assessments-table';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Assessments" description="Assessments for this subject offering."><AssessmentsTable assessments={[]} offeringId="" tools={[]} totalWeight={0} endpoint="" contextQuery="" capabilities={{ create: false, view: true, update: false, delete: false }}/></NavigationPage>;
}
