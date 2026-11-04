import { CalendarPlanner } from '@/components/navigation/calendar/calendar-planner';
import { dayKeyOf } from '@/components/navigation/calendar/weeks';
import { NavigationPage } from '@/components/navigation/page-shell';
import type { CalendarData } from '@/components/navigation/calendar/types';
import type { CalendarEventDto, AssessmentPlanDto } from '@/lib/calendar/types';
import { serverApi } from '@/lib/api/server';
import { learningContext, withQuery } from '@/lib/learning/server';
export default async function Page({ searchParams }: PageProps<'/navigation/calendar'>) {
    const { query, contextQuery } = await learningContext(await searchParams);
    const result = await serverApi<CalendarData & { events: CalendarEventDto[]; plans: AssessmentPlanDto[] }>(withQuery('/api/v1/me/calendar', query));
    const data = { ...result, offerings: result.offerings.map(offering => ({ ...offering, assessments: offering.assessments.map(assessment => ({ ...assessment, href: withQuery(`/navigation/assessments/${assessment.id}`, contextQuery) })) })) };
    return <NavigationPage title="Calendar" description="Your week and study term in one place."><CalendarPlanner data={data} today={dayKeyOf(new Date(), result.timeZone)} initialEvents={result.events} initialPlans={result.plans}/></NavigationPage>;
}
