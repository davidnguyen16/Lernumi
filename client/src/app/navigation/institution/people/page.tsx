import { PeopleSection } from '@/components/admin/people/people-section';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="People" description="Role assignments and subject access."><PeopleSection institutionId="" searchParams={{}} endpoint="" contextQuery="" roleCapabilities={{ create: false, revoke: false }} scopeCapabilities={{ view: true, create: false, revoke: false }}/></NavigationPage>;
}
