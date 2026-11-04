import Link from 'next/link';
import { notFound } from 'next/navigation';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { learningApi as serverApi } from '@/lib/learning/server';
import { ApiError } from '@/lib/api/errors';
import { learningContext, withQuery } from '@/lib/learning/server';
import { displayDate, type LearningOffering } from '@/lib/learning/types';

export default async function Page({ params, searchParams }: PageProps<'/navigation/subjects/[offeringId]'>) {
    const { offeringId } = await params;
    const { query, contextQuery } = await learningContext(await searchParams);
    let offering: LearningOffering;
    try { ({ offering } = await serverApi<{ offering: LearningOffering }>(withQuery(`/api/v1/me/offerings/${offeringId}`, query))); }
    catch (error) { if (error instanceof ApiError && error.status === 404) notFound(); throw error; }
    return <NavigationPage title={`${offering.subject.code} · ${offering.subject.name}`} description={`${offering.institution.name} · Session ${offering.term.session}, ${offering.term.year}`} backHref={withQuery('/navigation/subjects', contextQuery)}>
        {offering.subject.description && <p className="text-muted-foreground mb-6 whitespace-pre-wrap">{offering.subject.description}</p>}
        {!offering.assessments?.length ? <NavigationEmptyState title="No published assessments" description="Assessments appear here when your teacher publishes them." /> : <div className="grid gap-3">{offering.assessments.map(assessment => <Link key={assessment.id} href={withQuery(`/navigation/assessments/${assessment.id}`, contextQuery)} className="bg-card hover:border-primary/50 rounded-xl border p-5 transition-colors"><div className="flex justify-between gap-4"><h2 className="font-semibold">{assessment.name}</h2><span>{assessment.weight}%</span></div><p className="text-muted-foreground mt-2 text-sm">{assessment.isGroup ? 'Group assessment' : 'Individual assessment'} · Due {displayDate(assessment.dueAt)}</p></Link>)}</div>}
    </NavigationPage>;
}
