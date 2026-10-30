import { AssessmentAttemptBrowser } from '@/components/navigation/learning/assessment-attempt-browser';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Assessment attempts" description="Browse submitted and in-progress assessment work."><AssessmentAttemptBrowser students={[]} canReplay={false}/></NavigationPage>;
}
