import { NavigationPage } from '@/components/navigation/page-shell';
import { WorkspaceList } from '@/components/navigation/workspaces/workspace-list';
export default function Page() {
    return <NavigationPage title="Workspaces" description="Your assessment work and personal documents."><WorkspaceList workspaces={[]}/></NavigationPage>;
}
