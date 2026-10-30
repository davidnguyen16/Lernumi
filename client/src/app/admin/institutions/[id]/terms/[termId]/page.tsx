import { PeriodsTable } from '@/components/admin/terms/periods-table';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
export default async function InstitutionTermPeriodsPage({ params }: PageProps<'/admin/institutions/[id]/terms/[termId]'>) {
    const { id, termId } = await params;
    return <div className="flex flex-col gap-5 pt-2">
        <Button nativeButton={false} variant="ghost" className="w-fit -ml-3" render={<Link href={`/admin/institutions/${id}/terms`}/>}>Terms</Button>
        <div><h2 className="font-heading text-lg font-semibold tracking-tight">Term periods</h2><p className="text-muted-foreground mt-1 text-sm">Calendar windows for teaching, breaks and exams.</p></div>
        <PeriodsTable periods={[]} termId={termId}/>
    </div>;
}
