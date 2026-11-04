import { WorkspaceHeader } from '@/components/navigation/workspaces/workspace-header';
import { requireSession } from '@/lib/api/server';
import { learningApi as serverApi } from '@/lib/learning/server';
import type { WorkspaceDto } from '@/lib/learning/types';
export default async function WorkspacePageHeader({ params }: { params: Promise<{ workspaceId: string }> }) {
    await requireSession();
    const { workspaceId } = await params;
    const { data } = await serverApi<{ data: WorkspaceDto }>(`/api/v1/workspaces/${workspaceId}`);
    return <WorkspaceHeader workspaceId={workspaceId} initialName={data.name} personal={data.kind === 'PERSONAL'} readOnly={data.readOnly}/>;
}
