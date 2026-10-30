import { RolesSection } from '@/components/admin/roles/roles-section';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Roles" description="Role scope, inheritance and permissions."><RolesSection searchParams={{}} lockedInstitutionId="" endpoint="" contextQuery="" capabilities={{ create: false, view: true, update: false, delete: false }}/></NavigationPage>;
}
