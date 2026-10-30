import { CalendarPlanner } from '@/components/navigation/calendar/calendar-planner';
import { dayKeyOf } from '@/components/navigation/calendar/weeks';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Calendar" description="Your week and study term in one place."><CalendarPlanner data={{ timeZone: 'Australia/Sydney', terms: [], offerings: [], canSeeAssessments: false }} today={dayKeyOf(new Date(), 'Australia/Sydney')} initialEvents={[]} initialPlans={[]}/></NavigationPage>;
}
