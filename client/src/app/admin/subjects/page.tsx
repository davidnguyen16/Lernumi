import { SubjectsSection } from '@/components/admin/subjects/subjects-section';
export default async function AdminSubjectsPage({ searchParams }: PageProps<'/admin/subjects'>) {
    return <div className="flex flex-col gap-6 pt-1"><section><h1 className="font-heading text-xl font-semibold">Subjects</h1><p className="text-muted-foreground mt-1 text-sm">Browse subjects across institutions. Open institution setup to create or edit subjects.</p></section>
        <SubjectsSection searchParams={await searchParams} capabilities={{ create: false, view: true, update: false, delete: false }}/></div>;
}
