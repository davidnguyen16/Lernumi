'use client';
import { pictureUrl } from '@/components/media/picture-url';
import { Avatar,AvatarFallback,AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu,DropdownMenuContent,DropdownMenuGroup,DropdownMenuItem,DropdownMenuLabel,DropdownMenuSeparator,DropdownMenuTrigger,} from '@/components/ui/dropdown-menu';
import { ArrowDown01Icon,Logout01Icon,Settings01Icon,UserIcon,} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { api } from '@/lib/api/client';
import { useState } from 'react';
export type AccountUser = {
    firstName: string;
    lastName: string;
    email: string;
    avatarKey: string | null;
};
type Props = {
    user: AccountUser | null;
    onSignOut?: () => void;
};
export function AccountBox({ user, onSignOut }: Props) {
    const [signingOut, setSigningOut] = useState(false);
    const [error, setError] = useState<string | null>(null);
    async function signOut() {
        if (onSignOut) return onSignOut();
        setSigningOut(true);
        try {
            await api('/api/v1/auth/logout', { method: 'POST' });
            window.location.replace('/auth');
        } catch (error) { setError(error instanceof Error ? error.message : 'Could not sign out.'); }
        finally { setSigningOut(false); }
    }
    const initials = user ? `${user.firstName.at(0) ?? ''}${user.lastName.at(0) ?? ''}`.toUpperCase() : '';
    const fullName = user ? `${user.firstName} ${user.lastName}`.trim() : 'Account';
    return (<DropdownMenu>
            <DropdownMenuTrigger className="hover:bg-accent hover:text-accent-foreground flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring hover:cursor-pointer">
                <Avatar className="size-7">
                    <AvatarImage src={pictureUrl(user?.avatarKey ?? null)} alt=""/>
                    <AvatarFallback className="text-xs">{initials || <HugeiconsIcon icon={UserIcon} className="size-4" />}</AvatarFallback>
                </Avatar>

                <div className="hidden text-left leading-tight sm:grid">
                    <span className="truncate text-sm font-medium">{fullName}</span>
                    <span className="text-muted-foreground max-w-40 truncate text-xs">
                        {user?.email ?? ''}
                    </span>
                </div>

                <HugeiconsIcon icon={ArrowDown01Icon} strokeWidth={2} className="text-muted-foreground hidden size-4 sm:block"/>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-56">

                <DropdownMenuGroup>
                    <DropdownMenuLabel className="flex flex-col gap-0.5">
                        <span className="font-medium">{fullName}</span>
                        <span className="text-muted-foreground truncate text-xs font-normal">
                            {user?.email ?? ''}
                        </span>
                    </DropdownMenuLabel>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem render={<Link href="/navigation/profile"/>}>
                        <HugeiconsIcon icon={UserIcon} strokeWidth={2}/>
                        Profile
                    </DropdownMenuItem>
                    <DropdownMenuItem render={<Link href="/navigation/settings"/>}>
                        <HugeiconsIcon icon={Settings01Icon} strokeWidth={2}/>
                        Settings
                    </DropdownMenuItem>
                </DropdownMenuGroup>

                <DropdownMenuSeparator />

                {error && <p role="alert" className="px-2 py-1 text-xs text-destructive">{error}</p>}
                <DropdownMenuItem variant="destructive" disabled={signingOut} onClick={() => void signOut()}>
                    <HugeiconsIcon icon={Logout01Icon} strokeWidth={2}/>
                    {signingOut ? 'Signing out…' : 'Sign out'}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>);
}
