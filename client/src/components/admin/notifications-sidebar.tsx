'use client';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Alert02Icon,BookOpen01Icon,Cancel01Icon,Notification03Icon,UserSharingIcon,} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { createContext,useContext,useState,type ReactNode } from 'react';
export type AdminNotification = {
    id: string;
    icon: typeof Alert02Icon;
    title: string;
    body: string;
    at: string;
    tone: 'info' | 'attention';
    href?: string;
};
export const JOIN_REQUEST_ICON = UserSharingIcon;
export const SUBJECT_ACCESS_REQUEST_ICON = BookOpen01Icon;
type NotificationsContext = {
    open: boolean;
    toggle: () => void;
    close: () => void;
    count: number;
};
const Context = createContext<NotificationsContext | null>(null);
function useNotifications() {
    const context = useContext(Context);
    if (!context) {
        throw new Error('useNotifications must be used inside <NotificationsProvider>');
    }
    return context;
}
export function NotificationsProvider({ children, notifications, }: {
    children: ReactNode;
    notifications: AdminNotification[];
}) {
    const [open, setOpen] = useState(false);
    return (<Context.Provider value={{
            open,
            toggle: () => setOpen((value) => !value),
            close: () => setOpen(false),
            count: notifications.length,
        }}>
            <div className="flex min-h-0 flex-1">

                <div className="flex min-w-0 flex-1 flex-col">{children}</div>

                <NotificationsAside notifications={notifications}/>
            </div>
        </Context.Provider>);
}
export function NotificationsTrigger() {
    const { toggle, count, open } = useNotifications();
    return (<Button variant="ghost" size="icon-sm" onClick={toggle} aria-label={open ? 'Close notifications' : 'Open notifications'} aria-expanded={open} className="relative">
            <HugeiconsIcon icon={Notification03Icon} strokeWidth={2}/>

            {count > 0 && (<span aria-hidden className="bg-brand-amber absolute top-1 right-1 size-2 rounded-full ring-2 ring-background"/>)}
        </Button>);
}
function NotificationsAside({ notifications }: {
    notifications: AdminNotification[];
}) {
    const { open, close, count } = useNotifications();
    return (<>

            {open && (<button type="button" aria-label="Close notifications" onClick={close} className="bg-foreground/20 fixed inset-0 z-30 lg:hidden"/>)}

            <aside data-state={open ? 'open' : 'closed'} className={cn('bg-sidebar flex shrink-0 flex-col border-l transition-[width] duration-200 ease-linear', 'fixed inset-y-0 right-0 z-40 w-80 lg:static lg:z-auto', open ? 'lg:w-80' : 'w-0 border-l-0 lg:w-0', !open && 'pointer-events-none translate-x-full lg:translate-x-0')}>

                <div className="flex w-80 flex-1 flex-col overflow-hidden">
                    <div className="flex h-16 shrink-0 items-center gap-2 px-4">
                        <h2 className="flex-1 text-sm font-semibold">Notifications</h2>

                        {count > 0 && (<span className="bg-muted rounded-sm px-1.5 py-0.5 text-xs font-medium tabular-nums">
                                {count}
                            </span>)}

                        <Button variant="ghost" size="icon-sm" onClick={close} aria-label="Close notifications">
                            <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2}/>
                        </Button>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {count === 0 ? (<div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                                <div className="bg-muted flex size-10 items-center justify-center rounded-full">
                                    <HugeiconsIcon icon={Notification03Icon} strokeWidth={2} className="text-muted-foreground size-4"/>
                                </div>
                                <p className="text-sm font-medium">Nothing to review</p>
                                <p className="text-muted-foreground text-sm">
                                    Role and subject access requests needing a decision will appear
                                    here.
                                </p>
                            </div>) : (<ul className="divide-border divide-y">
                                {notifications.map((item) => (<li key={item.id}>
                                        <Link href={item.href ?? '/admin/institutions'} onClick={close} className="hover:bg-muted/50 flex gap-3 px-4 py-3.5 transition-colors">
                                            <div className={cn('mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md', item.tone === 'attention'
                    ? 'bg-brand-amber/12 text-brand-amber'
                    : 'bg-muted text-muted-foreground')}>
                                                <HugeiconsIcon icon={item.icon} strokeWidth={2} className="size-3.5"/>
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium">{item.title}</p>
                                                <p className="text-muted-foreground text-sm">
                                                    {item.body}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {item.at}
                                                </p>
                                            </div>
                                        </Link>
                                    </li>))}
                            </ul>)}
                    </div>
                </div>
            </aside>
        </>);
}
