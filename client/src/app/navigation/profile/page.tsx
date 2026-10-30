import { NavigationPage } from '@/components/navigation/page-shell';
import { ProfileSettings } from '@/components/navigation/profile-settings';
export default function Page() {
    return <NavigationPage title="Profile" description="Manage your profile and account identity."><ProfileSettings firstName="" lastName="" email="" avatarKey={null}/></NavigationPage>;
}
