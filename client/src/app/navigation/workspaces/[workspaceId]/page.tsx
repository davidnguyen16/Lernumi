import { WorkspaceDetail } from '@/components/navigation/workspaces/workspace-detail';
import { learningApi as serverApi } from '@/lib/learning/server';
import { first, type DocumentDto, type WorkspaceDto, type LearningTool } from '@/lib/learning/types';
import { learningContext } from '@/lib/learning/server';
export default async function WorkspacePage({ params, searchParams }: PageProps<'/navigation/workspaces/[workspaceId]'>) {
    const { workspaceId } = await params;
    const search = await searchParams;
    const { selected } = await learningContext(search);
    const folder = first(search.folder);
    const [result, folders] = await Promise.all([
        serverApi<{ data: WorkspaceDto; currentFolderId: string | null; breadcrumbs: Array<{ id: string; name: string }>; documents: DocumentDto[]; tools: LearningTool[] }>(`/api/v1/workspaces/${workspaceId}${folder ? `?folder=${encodeURIComponent(folder)}` : ''}`),
        serverApi<{ documents: DocumentDto[] }>(`/api/v1/workspaces/${workspaceId}/documents?limit=200`),
    ]);
    return <main className="-mx-5 -mb-10 h-[calc(100dvh-4rem)] overflow-hidden md:-mx-6"><WorkspaceDetail workspaceId={workspaceId} readOnly={result.data.readOnly} currentFolderId={result.currentFolderId} folderIds={[...new Set([...folders.documents, ...result.documents].filter(document => document.entryKind === 'FOLDER').map(document => document.id).concat(result.breadcrumbs.map(folder => folder.id)))]} breadcrumbs={result.breadcrumbs} documents={result.documents.map(document => ({ ...document, hash: document.contentHash, updated: document.updatedAt, downloadHref: document.entryKind === 'FILE' ? `/api/v1/workspaces/${workspaceId}/documents/${document.id}` : null }))} tools={result.tools} toolLaunch={result.data.readOnly ? null : result.data.kind === 'PERSONAL' ? { mode: 'PERSONAL' } : result.data.attempt ? { mode: 'WORK', attemptId: result.data.attempt.id, roleAssignmentId: selected?.roleAssignmentId } : null}/></main>;
}
