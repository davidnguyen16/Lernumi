import { NavigationPage } from '@/components/navigation/page-shell';
import { ProfileSettings } from '@/components/navigation/profile-settings';
import { requireSession } from '@/lib/api/server';
export default async function Page() {
    const { user } = await requireSession();
    const parts = user.name.split(/\s+/);
    return <NavigationPage title="Profile" description="Manage your profile and account identity."><ProfileSettings firstName={user.firstName ?? parts[0] ?? ''} lastName={user.lastName ?? parts.slice(1).join(' ')} email={user.email} avatarKey={user.avatarUrl} emailVerified={user.emailVerified}/></NavigationPage>;
}
