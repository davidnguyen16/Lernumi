import { InstitutionOverview } from '@/components/admin/institutions/institution-overview';
import Link from 'next/link';
const SECTIONS = [
    { path: 'subjects', label: 'Subjects', description: 'Units taught by the institution.' },
    { path: 'terms', label: 'Terms', description: 'Study sessions and calendar periods.' },
    { path: 'roles', label: 'Roles', description: 'Institution roles and access.' },
    { path: 'offerings', label: 'Offerings', description: 'Subjects offered in each term.' },
    { path: 'people', label: 'People', description: 'People and their institution roles.' },
    { path: 'requests', label: 'Requests', description: 'Membership and subject access requests.' },
];
export default async function InstitutionSetupPage({ params }: PageProps<'/admin/institutions/[id]'>) {
    const { id } = await params;
    return <InstitutionOverview heading={<div><h2 className="font-heading text-lg font-semibold">Institution setup</h2><p className="text-muted-foreground mt-1 text-sm">No institution details available.</p></div>}>
        <section className="divide-y overflow-hidden rounded-lg border">
            {SECTIONS.map(section => <Link key={section.path} href={`/admin/institutions/${id}/${section.path}`} className="hover:bg-muted/50 block px-4 py-4"><p className="text-sm font-medium">{section.label}</p><p className="text-muted-foreground text-sm">{section.description}</p></Link>)}
        </section>
    </InstitutionOverview>;
}
