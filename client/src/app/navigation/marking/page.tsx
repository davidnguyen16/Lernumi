import { MarkingWorkspace } from '@/components/navigation/marking/marking-workspace';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Marking & replay" description="Review assessment work, feedback and recordings."><MarkingWorkspace terms={[]} initialSelection={{}} contextQuery="" canBrowseAttempts/></NavigationPage>;
}
