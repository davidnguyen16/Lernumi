import { PeriodsTable } from '@/components/admin/terms/periods-table';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Study periods" description="Teaching, recess and exam periods."><PeriodsTable periods={[]} termId="" endpoint="" contextQuery="" capabilities={{ create: false, view: true, update: false, delete: false }}/></NavigationPage>;
}
