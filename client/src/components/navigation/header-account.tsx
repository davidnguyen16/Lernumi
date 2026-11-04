import { AccountBox } from '@/components/navigation/account-box';
import { getSession } from '@/lib/api/server';

export async function HeaderAccount() {
    const session = await getSession();
    const user = session?.user;
    const names = user?.name.split(/\s+/) ?? [];
    return <AccountBox user={user ? { firstName: user.firstName ?? names[0] ?? '', lastName: user.lastName ?? names.slice(1).join(' '), email: user.email, avatarKey: user.avatarUrl } : null} />;
}
