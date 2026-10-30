import { AssessmentsTable } from '@/components/admin/offerings/assessments-table';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
export default async function InstitutionOfferingAssessmentsPage({ params }: PageProps<'/admin/institutions/[id]/offerings/[offeringId]'>) {
    const { id, offeringId } = await params;
    return <div className="flex flex-col gap-5 pt-2">
        <Button nativeButton={false} variant="ghost" className="w-fit -ml-3" render={<Link href={`/admin/institutions/${id}/offerings`}/>}>Offerings</Button>
        <div><h2 className="font-heading text-lg font-semibold tracking-tight">Assessments</h2><p className="text-muted-foreground mt-1 text-sm">Grading, dates, hurdle rules and allowed tools.</p></div>
        <AssessmentsTable assessments={[]} offeringId={offeringId} tools={[]} totalWeight={0}/>
    </div>;
}
