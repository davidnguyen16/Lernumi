'use client';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { pendingAdminRequests, type PendingAdminRequest } from '@/lib/admin/notifications';
import { cn } from '@/lib/utils';
import { Alert02Icon, BookOpen01Icon, Cancel01Icon, Notification03Icon, UserSharingIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

type NotificationsContext = {
    open: boolean;
    toggle: () => void;
    close: () => void;
    count: number;
};
const Context = createContext<NotificationsContext | null>(null);

function useNotifications() {
    const context = useContext(Context);
    if (!context) throw new Error('useNotifications must be used inside <NotificationsProvider>');
    return context;
}

export function NotificationsProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<PendingAdminRequest[]>([]);
    const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);
    const [refresh, setRefresh] = useState(0);

    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        setStatus('loading');
        setError(null);
        pendingAdminRequests(controller.signal).then(items => {
            if (controller.signal.aborted) return;
            setNotifications(items);
            setStatus('ready');
        }).catch((failure: unknown) => {
            if (controller.signal.aborted) return;
            setError(failure instanceof Error ? failure.message : 'Could not load requests. Please try again.');
            setStatus('error');
        });
        return () => controller.abort();
    }, [open, refresh]);

    return (
        <Context.Provider value={{
            open,
            toggle: () => setOpen(value => !value),
            close: () => setOpen(false),
            count: notifications.length,
        }}>
            <div className="flex min-h-0 flex-1">
                <div className="flex min-w-0 flex-1 flex-col">{children}</div>
                <NotificationsAside notifications={notifications} status={status} error={error} retry={() => setRefresh(value => value + 1)} />
            </div>
        </Context.Provider>
    );
}

export function NotificationsTrigger() {
    const { toggle, count, open } = useNotifications();
    return (
        <Button variant="ghost" size="icon-sm" onClick={toggle} aria-label={open ? 'Close notifications' : 'Open notifications'} aria-expanded={open} className="relative">
            <HugeiconsIcon icon={Notification03Icon} strokeWidth={2} />
            {count > 0 && <span aria-hidden className="bg-brand-amber absolute top-1 right-1 size-2 rounded-full ring-2 ring-background" />}
        </Button>
    );
}

function NotificationsAside({ notifications, status, error, retry }: {
    notifications: PendingAdminRequest[];
    status: 'idle' | 'loading' | 'ready' | 'error';
    error: string | null;
    retry: () => void;
}) {
    const { open, close, count } = useNotifications();
    return (
        <>
            {open && <button type="button" aria-label="Close notifications" onClick={close} className="bg-foreground/20 fixed inset-0 z-30 lg:hidden" />}
            <aside aria-labelledby="admin-notifications-title" aria-hidden={!open} inert={!open} data-state={open ? 'open' : 'closed'} className={cn('bg-sidebar flex shrink-0 flex-col border-l transition-[width] duration-200 ease-linear', 'fixed inset-y-0 right-0 z-40 w-80 lg:static lg:z-auto', open ? 'lg:w-80' : 'w-0 border-l-0 lg:w-0', !open && 'pointer-events-none translate-x-full lg:translate-x-0')}>
                <div className="flex w-80 flex-1 flex-col overflow-hidden">
                    <div className="flex h-16 shrink-0 items-center gap-2 px-4">
                        <h2 id="admin-notifications-title" className="flex-1 text-sm font-semibold">Notifications</h2>
                        {status === 'ready' && count > 0 && <span className="bg-muted rounded-sm px-1.5 py-0.5 text-xs font-medium tabular-nums">{count}</span>}
                        <Button variant="ghost" size="icon-sm" onClick={close} aria-label="Close notifications">
                            <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2} />
                        </Button>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto" aria-busy={status === 'loading'}>
                        {(status === 'idle' || status === 'loading') ? (
                            <div role="status" className="text-muted-foreground flex items-center justify-center gap-2 px-6 py-12 text-sm">
                                <Spinner aria-hidden /> Loading requests…
                            </div>
                        ) : status === 'error' ? (
                            <div role="alert" className="flex flex-col items-center gap-3 px-6 py-12 text-center">
                                <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} className="text-destructive size-6" />
                                <p className="text-sm font-medium">Could not load notifications</p>
                                <p className="text-muted-foreground text-sm">{error}</p>
                                <Button variant="outline" size="sm" onClick={retry}>Try again</Button>
                            </div>
                        ) : count === 0 ? (
                            <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                                <div className="bg-muted flex size-10 items-center justify-center rounded-full">
                                    <HugeiconsIcon icon={Notification03Icon} strokeWidth={2} className="text-muted-foreground size-4" />
                                </div>
                                <p className="text-sm font-medium">Nothing to review</p>
                                <p className="text-muted-foreground text-sm">Role and subject access requests needing a decision will appear here.</p>
                            </div>
                        ) : (
                            <ul className="divide-border divide-y">
                                {notifications.map(item => (
                                    <li key={item.id}>
                                        <Link href={item.href} onClick={close} className="hover:bg-muted/50 flex gap-3 px-4 py-3.5 transition-colors">
                                            <div className="bg-brand-amber/12 text-brand-amber mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md">
                                                <HugeiconsIcon icon={item.kind === 'role' ? UserSharingIcon : BookOpen01Icon} strokeWidth={2} className="size-3.5" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium">{item.title}</p>
                                                <p className="text-muted-foreground text-sm">{item.body}</p>
                                                <time dateTime={item.requestedAt} className="text-muted-foreground mt-1 block text-xs">
                                                    {new Date(item.requestedAt).toLocaleString('en-AU', { timeZone: 'Australia/Sydney' })}
                                                </time>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </aside>
        </>
    );
}
