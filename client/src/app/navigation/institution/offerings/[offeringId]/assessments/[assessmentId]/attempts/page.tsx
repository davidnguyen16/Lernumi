import { redirect } from 'next/navigation';
import { first } from '@/lib/admin/academic-data';
export default async function Page({ params, searchParams }: PageProps<'/navigation/institution/offerings/[offeringId]/assessments/[assessmentId]/attempts'>) {
    const { offeringId, assessmentId } = await params;
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(await searchParams)) { const item = first(value); if (item) query.set(key, item); }
    query.set('offeringId', offeringId);
    query.set('assessmentId', assessmentId);
    redirect(`/navigation/marking?${query}`);
}
