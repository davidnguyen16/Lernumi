import { AppSidebar } from '@/components/navigation/app-sidebar';
import { HeaderAccount } from '@/components/navigation/header-account';
import { HeaderContext } from '@/components/navigation/header-context';
import { ThemeMenu } from '@/components/theme-menu';
import { Separator } from '@/components/ui/separator';
import { SidebarInset,SidebarProvider,SidebarTrigger } from '@/components/ui/sidebar';
import { Suspense } from 'react';
export default function NavigationLayout({ children, pageHeader }: LayoutProps<'/navigation'>) {
    return (<SidebarProvider>
            <Suspense><AppSidebar /></Suspense>
            <SidebarInset>
                <header className="bg-background sticky top-0 z-10 flex h-16 shrink-0 items-center gap-3 border-b px-5 md:px-6">
                    <SidebarTrigger className="-ml-1.5"/>
                    <div className="min-w-0 flex-1">{pageHeader}</div>
                    <HeaderContext options={[]}/>
                    <Separator orientation="vertical" className="h-5 data-vertical:self-center"/>
                    <ThemeMenu />
                    <HeaderAccount />
                </header>
                <div className="relative flex-1 overflow-y-auto">
                    <div aria-hidden className="text-foreground pointer-events-none absolute inset-0 opacity-[0.035]" style={{ backgroundImage: 'radial-gradient(currentColor 1px, transparent 1px)', backgroundSize: '22px 22px' }}/>
                    <div className="relative px-5 pb-10 md:px-6">{children}</div>
                </div>
            </SidebarInset>
        </SidebarProvider>);
}
