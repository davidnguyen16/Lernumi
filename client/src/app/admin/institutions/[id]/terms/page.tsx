import { TermsSection } from '@/components/admin/terms/terms-section';
export default async function InstitutionTermsPage({ params, searchParams, }: PageProps<'/admin/institutions/[id]/terms'>) {
    const { id } = await params;
    return (<div className="flex flex-col gap-4 pt-2">
            <p className="text-muted-foreground max-w-2xl text-sm">
                The years and sessions this institution runs. Teaching, break and exam periods live inside a term and drive the calendar.
            </p>

            <TermsSection searchParams={await searchParams} lockedInstitutionId={id}/>
        </div>);
}
