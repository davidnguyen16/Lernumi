import { AttemptReviewWorkspace } from '@/components/navigation/marking/attempt-review-workspace';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Attempt review" description="Review feedback, marks and recordings."><AttemptReviewWorkspace marks={[]} flags={[]} canViewMarks canReplay={false} canMark={false} replay={null}/></NavigationPage>;
}
