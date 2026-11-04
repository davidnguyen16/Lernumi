import Link from 'next/link';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { serverApi } from '@/lib/api/server';
import { learningContext, withQuery } from '@/lib/learning/server';
import { first, type LearningOffering } from '@/lib/learning/types';

export default async function Page({ searchParams }: PageProps<'/navigation/subjects'>) {
    const search = await searchParams;
    const { query, contextQuery } = await learningContext(search);
    const page = Math.max(1, Number(first(search.page)) || 1);
    const result = await serverApi<{ offerings: LearningOffering[]; meta: { current_page: number; last_page: number; total: number } }>(withQuery(`/api/v1/me/offerings?page=${page}&perPage=15`, query));
    return <NavigationPage title="My subjects" description="Your subjects and study periods.">
        {result.offerings.length === 0 ? <NavigationEmptyState title="No subjects yet" description="Request a role and subject access to see your classes here." /> : <div className="grid gap-4 md:grid-cols-2">{result.offerings.map(offering => <Link key={offering.id} href={withQuery(`/navigation/subjects/${offering.id}`, contextQuery)} className="bg-card hover:border-primary/50 rounded-xl border p-5 transition-colors">
            <p className="text-primary text-xs font-semibold uppercase tracking-wide">{offering.subject.code}</p><h2 className="mt-2 text-lg font-semibold">{offering.subject.name}</h2><p className="text-muted-foreground mt-2 text-sm">{offering.institution.name} · Session {offering.term.session}, {offering.term.year}</p><p className="text-muted-foreground mt-3 text-sm">{offering.publishedAssessmentCount ?? 0} published assessments</p>
        </Link>)}</div>}
        <div className="mt-5 flex justify-between text-sm">{page > 1 ? <Link href={withQuery(`/navigation/subjects?page=${page - 1}`, contextQuery)}>Previous</Link> : <span />}{page < result.meta.last_page && <Link href={withQuery(`/navigation/subjects?page=${page + 1}`, contextQuery)}>Next</Link>}</div>
    </NavigationPage>;
}
