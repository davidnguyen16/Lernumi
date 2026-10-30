'use client';
import { BrandLogo } from '@/components/brand-logo';
import { Sidebar,SidebarContent,SidebarFooter,SidebarGroup,SidebarGroupContent,SidebarGroupLabel,SidebarHeader,SidebarMenu,SidebarMenuButton,SidebarMenuItem,SidebarRail } from '@/components/ui/sidebar';
import { BookOpen01Icon,Calendar03Icon,CalendarMortarboardIcon,CheckmarkSquare02Icon,CourseIcon,Folder01Icon,Home01Icon,Settings01Icon,Shield01Icon,UniversityIcon,UserMultipleIcon,UserSharingIcon,ViewIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
const GROUPS = [
    { label: 'Personal', items: [
            { href: '/navigation/dashboard', label: 'Dashboard', icon: Home01Icon },
            { href: '/navigation/subjects', label: 'My Subjects', icon: BookOpen01Icon },
            { href: '/navigation/calendar', label: 'Calendar', icon: Calendar03Icon },
            { href: '/navigation/workspaces', label: 'Workspaces', icon: Folder01Icon },
        ] },
    { label: 'Marking & Replay', items: [
            { href: '/navigation/marking', label: 'Marking & Replay', icon: CheckmarkSquare02Icon },
        ] },
    { label: 'Access Control', items: [
            { href: '/navigation/roles', label: 'My Roles', icon: Shield01Icon },
            { href: '/navigation/subject-access', label: 'Subject Access', icon: BookOpen01Icon },
        ] },
    { label: 'Institution', items: [
            { href: '/navigation/institution/settings', label: 'Institution Profile', icon: Settings01Icon },
            { href: '/navigation/institution/subjects', label: 'Subjects', icon: UniversityIcon },
            { href: '/navigation/institution/terms', label: 'Study Term', icon: CalendarMortarboardIcon },
            { href: '/navigation/institution/offerings', label: 'Subject Offering', icon: CourseIcon },
            { href: '/navigation/institution/roles', label: 'Roles', icon: Shield01Icon },
            { href: '/navigation/institution/people', label: 'People', icon: UserMultipleIcon },
            { href: '/navigation/institution/requests', label: 'Requests', icon: UserSharingIcon },
            { href: '/navigation/institution/audit', label: 'Audit', icon: ViewIcon },
        ] },
];
export function AppSidebar() {
    const pathname = usePathname();
    return (<Sidebar collapsible="icon">
            <SidebarHeader><SidebarMenu><SidebarMenuItem><SidebarMenuButton size="lg" render={<Link href="/"/>}><BrandLogo className="size-8 shrink-0"/><div className="grid flex-1 text-left leading-tight"><span className="font-heading truncate font-semibold">Lern<span className="text-brand-teal">umi</span></span><span className="text-muted-foreground truncate text-xs">Learning platform</span></div></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader>
            <SidebarContent>{GROUPS.map((group) => <SidebarGroup key={group.label}><SidebarGroupLabel>{group.label}</SidebarGroupLabel><SidebarGroupContent><SidebarMenu>{group.items.map((item) => <SidebarMenuItem key={item.href}><SidebarMenuButton isActive={pathname.startsWith(item.href)} tooltip={item.label} render={<Link href={item.href}/>}><HugeiconsIcon icon={item.icon} strokeWidth={2}/><span>{item.label}</span></SidebarMenuButton></SidebarMenuItem>)}</SidebarMenu></SidebarGroupContent></SidebarGroup>)}</SidebarContent>
            <SidebarFooter><SidebarMenu><SidebarMenuItem><SidebarMenuButton isActive={pathname.startsWith('/navigation/settings')} tooltip="Settings" render={<Link href="/navigation/settings"/>}><HugeiconsIcon icon={Settings01Icon} strokeWidth={2}/><span>Settings</span></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarFooter>
            <SidebarRail />
        </Sidebar>);
}
