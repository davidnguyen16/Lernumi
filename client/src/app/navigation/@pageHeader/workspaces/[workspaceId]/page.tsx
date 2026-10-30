import { WorkspaceHeader } from '@/components/navigation/workspaces/workspace-header';
export default function WorkspacePageHeader() {
    return <WorkspaceHeader initialName="Workspace" personal={false} readOnly/>;
}
