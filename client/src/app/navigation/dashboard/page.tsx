import { NavigationPage } from '@/components/navigation/page-shell';
import { ArrowRight01Icon,BookOpen01Icon,Calendar03Icon,Folder01Icon,Shield01Icon,UniversityIcon,UserMultipleIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { serverApi } from '@/lib/api/server';
import { learningContext, withQuery } from '@/lib/learning/server';
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
export default async function DashboardPage({ searchParams }: PageProps<'/navigation/dashboard'>) {
    const { session, query, contextQuery } = await learningContext(await searchParams);
    const [learning, workspaces] = await Promise.all([serverApi<{ meta: { total: number } }>(withQuery('/api/v1/me/offerings?perPage=1', query)), serverApi<{ meta: { total: number } }>('/api/v1/workspaces?per_page=1')]);
    return <NavigationPage title={`Welcome, ${session.user.firstName || session.user.name}`} description="Your learning workspace."><div className="mb-7 grid gap-3 sm:grid-cols-2"><div className="bg-card rounded-xl border p-5"><p className="text-muted-foreground text-sm">Subjects available</p><p className="mt-2 text-3xl font-semibold">{learning.meta.total}</p></div><div className="bg-card rounded-xl border p-5"><p className="text-muted-foreground text-sm">Your workspaces</p><p className="mt-2 text-3xl font-semibold">{workspaces.meta.total}</p></div></div><div className="space-y-9">{SECTIONS.map((section) => <section key={section.title}><div className="mb-3"><h2 className="font-heading text-lg font-semibold">{section.title}</h2><p className="text-muted-foreground mt-1 text-sm">{section.description}</p></div><div className="overflow-hidden rounded-xl border">{section.items.map((item) => <Link key={item.href} href={withQuery(item.href, contextQuery)} className="bg-card hover:bg-muted/35 group flex items-center gap-4 border-b px-4 py-4 transition-colors last:border-b-0"><div className="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded-lg"><HugeiconsIcon icon={item.icon} strokeWidth={2} className="size-5"/></div><span className="min-w-0 flex-1 font-medium">{item.label}</span><HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2} className="text-muted-foreground size-4 transition-transform group-hover:translate-x-0.5"/></Link>)}</div></section>)}</div></NavigationPage>;
}
