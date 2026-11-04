import { TermsSection } from '@/components/admin/terms/terms-section';
export default async function AdminTermsPage({ searchParams }: PageProps<'/admin/terms'>) {
    return <div className="flex flex-col gap-6 pt-1"><section><h1 className="font-heading text-xl font-semibold">Study terms</h1><p className="text-muted-foreground mt-1 text-sm">Browse study terms across institutions. Open institution setup to make changes.</p></section>
        <TermsSection searchParams={await searchParams} capabilities={{ create: false, view: true, update: false, delete: false }}/></div>;
}
