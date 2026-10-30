import { NavigationPage } from '@/components/navigation/page-shell';
import { SecuritySettings } from '@/components/navigation/profile-settings';
export default function Page() {
    return <NavigationPage title="Security settings" description="Update your password and control signed-in devices."><SecuritySettings /></NavigationPage>;
}
