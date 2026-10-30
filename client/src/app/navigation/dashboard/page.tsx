import { NavigationPage } from '@/components/navigation/page-shell';
import { ArrowRight01Icon,BookOpen01Icon,Calendar03Icon,Folder01Icon,Shield01Icon,UniversityIcon,UserMultipleIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
const SECTIONS = [
    { title: 'Learning', description: 'Your subjects, study calendar and work.', items: [
            { label: 'My subjects', href: '/navigation/subjects', icon: BookOpen01Icon },
            { label: 'Calendar', href: '/navigation/calendar', icon: Calendar03Icon },
            { label: 'Workspaces', href: '/navigation/workspaces', icon: Folder01Icon },
        ] },
    { title: 'Institution', description: 'Subjects, study terms and access.', items: [
            { label: 'Institution profile', href: '/navigation/institution/settings', icon: UniversityIcon },
            { label: 'Subject offerings', href: '/navigation/institution/offerings', icon: BookOpen01Icon },
            { label: 'People', href: '/navigation/institution/people', icon: UserMultipleIcon },
        ] },
    { title: 'Access and personal work', description: 'Roles and subject access.', items: [
            { label: 'My roles', href: '/navigation/roles', icon: Shield01Icon },
            { label: 'Subject access', href: '/navigation/subject-access', icon: BookOpen01Icon },
        ] },
];
export default function DashboardPage() {
    return <NavigationPage title="Dashboard" description="Your learning workspace."><div className="space-y-9">{SECTIONS.map((section) => <section key={section.title}><div className="mb-3"><h2 className="font-heading text-lg font-semibold">{section.title}</h2><p className="text-muted-foreground mt-1 text-sm">{section.description}</p></div><div className="overflow-hidden rounded-xl border">{section.items.map((item) => <Link key={item.href} href={item.href} className="bg-card hover:bg-muted/35 group flex items-center gap-4 border-b px-4 py-4 transition-colors last:border-b-0"><div className="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-lg"><HugeiconsIcon icon={item.icon} strokeWidth={2} className="size-5"/></div><span className="min-w-0 flex-1 font-medium">{item.label}</span><HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2} className="text-muted-foreground size-4 transition-transform group-hover:translate-x-0.5"/></Link>)}</div></section>)}</div></NavigationPage>;
}
