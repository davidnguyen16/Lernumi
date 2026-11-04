'use client';
import { BrandLogo } from '@/components/brand-logo';
import { Sidebar,SidebarContent,SidebarFooter,SidebarGroup,SidebarGroupContent,SidebarGroupLabel,SidebarHeader,SidebarMenu,SidebarMenuButton,SidebarMenuItem,SidebarRail } from '@/components/ui/sidebar';
import { BookOpen01Icon,Calendar03Icon,CalendarMortarboardIcon,CheckmarkSquare02Icon,CourseIcon,Folder01Icon,Home01Icon,Settings01Icon,Shield01Icon,UniversityIcon,UserMultipleIcon,UserSharingIcon,ViewIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { usePathname, useSearchParams } from 'next/navigation';
import { can, getActiveContext } from '@/lib/api/context';
import type { SessionData } from '@/lib/api/types';
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
export function AppSidebar({ session }: { session: SessionData }) {
    const pathname = usePathname();
    const searchParams = useSearchParams();
    const context = getActiveContext(Object.fromEntries(searchParams), session);
    const roleQuery = new URLSearchParams();
    for (const field of ['institution', 'role', 'offering']) {
        const value = searchParams.get(field);
        if (value) roleQuery.set(field, value);
    }
    const href = (path: string) => roleQuery.size ? `${path}?${roleQuery}` : path;
    const allowed = (permission: string) => {
        if (session.isAdmin) return true;
        if (!context) return false;
        const institutionId = context.institution.id;
        const assignmentId = context.roleAssignmentId;
        if (can(session, permission, institutionId, null, assignmentId)) return true;
        const offerings = context.offeringId ? [context.offeringId] : context.offeringIds;
        return offerings.some(id => can(session, permission, institutionId, id, assignmentId));
    };
    const required: Record<string, string> = {
        '/navigation/institution/subjects': 'subject.view', '/navigation/institution/terms': 'term.view',
        '/navigation/institution/offerings': 'offering.view', '/navigation/institution/roles': 'role.view',
        '/navigation/institution/people': 'role_assignment.view', '/navigation/institution/requests': 'role_request.view',
        '/navigation/institution/audit': 'audit.view',
    };
    const groups = GROUPS.map(group => ({ ...group, items: group.items.filter(item => {
        if (item.href === '/navigation/marking') return allowed('attempt.mark') || allowed('attempt.view_all') || allowed('tool_session.replay_all');
        if (item.href === '/navigation/institution/settings') return session.isAdmin || Boolean(context);
        if (item.href === '/navigation/institution/requests') return allowed('role_request.view') || allowed('scope_request.view') || allowed('scope_assignment_code.view') || allowed('scope_assignment_code.create');
        return !required[item.href] || allowed(required[item.href]);
    }) })).filter(group => group.items.length);
    return (<Sidebar collapsible="icon">
            <SidebarHeader><SidebarMenu><SidebarMenuItem><SidebarMenuButton size="lg" render={<Link href="/"/>}><BrandLogo className="size-8 shrink-0"/><div className="grid flex-1 text-left leading-tight"><span className="font-heading truncate font-semibold">Lern<span className="text-brand-teal">umi</span></span><span className="text-muted-foreground truncate text-xs">Learning platform</span></div></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader>
            <SidebarContent>{groups.map((group) => <SidebarGroup key={group.label}><SidebarGroupLabel>{group.label}</SidebarGroupLabel><SidebarGroupContent><SidebarMenu>{group.items.map((item) => <SidebarMenuItem key={item.href}><SidebarMenuButton isActive={pathname.startsWith(item.href)} tooltip={item.label} render={<Link href={href(item.href)}/>}><HugeiconsIcon icon={item.icon} strokeWidth={2}/><span>{item.label}</span></SidebarMenuButton></SidebarMenuItem>)}</SidebarMenu></SidebarGroupContent></SidebarGroup>)}</SidebarContent>
            <SidebarFooter><SidebarMenu>{session.isAdmin && <SidebarMenuItem><SidebarMenuButton tooltip="Administration" render={<Link href="/admin" />}><HugeiconsIcon icon={Shield01Icon} strokeWidth={2}/><span>Administration</span></SidebarMenuButton></SidebarMenuItem>}<SidebarMenuItem><SidebarMenuButton isActive={pathname.startsWith('/navigation/settings')} tooltip="Settings" render={<Link href="/navigation/settings"/>}><HugeiconsIcon icon={Settings01Icon} strokeWidth={2}/><span>Settings</span></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarFooter>
            <SidebarRail />
        </Sidebar>);
}
