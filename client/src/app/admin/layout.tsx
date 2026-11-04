import { AdminSidebar } from '@/components/admin/admin-sidebar';
import { NotificationsProvider,NotificationsTrigger } from '@/components/admin/notifications-sidebar';
import { HeaderAccount } from '@/components/navigation/header-account';
import { Separator } from '@/components/ui/separator';
import { SidebarInset,SidebarProvider,SidebarTrigger } from '@/components/ui/sidebar';
import { requireSession } from '@/lib/api/server';
import { redirect } from 'next/navigation';
export default async function AdminLayout({ children }: LayoutProps<'/admin'>) {
    const session = await requireSession();
    if (!session.isAdmin) redirect('/navigation/dashboard');
    return (<SidebarProvider>
            <AdminSidebar />
            <SidebarInset className="overflow-hidden">
                <NotificationsProvider>
                    <header className="bg-background sticky top-0 z-10 flex h-16 shrink-0 items-center gap-3 border-b px-5 md:px-6">
                        <SidebarTrigger className="-ml-1.5"/>
                        <Separator orientation="vertical" className="h-5 data-vertical:self-center"/>

                        <span className="text-destructive bg-destructive/10 rounded-sm px-2 py-0.5 text-xs font-semibold tracking-wide uppercase">
                            Administration
                        </span>
                        <div className="flex-1"/>
                        <HeaderAccount />
                        <NotificationsTrigger />
                    </header>

                    <div className="relative flex-1 overflow-y-auto">
                        <div aria-hidden className="text-foreground pointer-events-none absolute inset-0 opacity-[0.035]" style={{
            backgroundImage: 'radial-gradient(currentColor 1px, transparent 1px)',
            backgroundSize: '22px 22px',
        }}/>
                        <div className="relative px-5 pb-10 md:px-6">{children}</div>
                    </div>
                </NotificationsProvider>
            </SidebarInset>
        </SidebarProvider>);
}
