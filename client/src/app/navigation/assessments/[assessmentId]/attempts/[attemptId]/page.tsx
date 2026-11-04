import { notFound } from 'next/navigation';
import Link from 'next/link';
import { NavigationPage } from '@/components/navigation/page-shell';
import { WorkspaceDetail } from '@/components/navigation/workspaces/workspace-detail';
import { SubmitAttempt } from '@/components/navigation/learning/submit-attempt';
import { LaunchReplayButton } from '@/components/navigation/learning/replay-actions';
import { can } from '@/lib/api/context';
import { learningApi as serverApi } from '@/lib/learning/server';
import { learningContext, withQuery } from '@/lib/learning/server';
import { displayDate, first, type LearningAttempt, type LearningAssessment, type LearningResult, type WorkspaceDto, type DocumentDto, type LearningTool } from '@/lib/learning/types';

export default async function Page({ params, searchParams }: PageProps<'/navigation/assessments/[assessmentId]/attempts/[attemptId]'>) {
    const { assessmentId, attemptId } = await params;
    const search = await searchParams;
    const { session, selected, query, contextQuery } = await learningContext(search);
    const result = await serverApi<{ attempt: LearningAttempt; assessment: LearningAssessment; result: LearningResult | null; canReplay: boolean; sessions: Array<{ id: string; toolName: string; startedAt: string; endedAt: string | null; eventBatchCount: number }> }>(withQuery(`/api/v1/me/attempts/${attemptId}`, query));
    if (result.attempt.assessmentId !== assessmentId) notFound();
    const workspaceId = result.attempt.workspaceId;
    const folder = first(search.folder);
    const workspace = workspaceId ? await serverApi<{ data: WorkspaceDto; documents: DocumentDto[]; tools: LearningTool[]; breadcrumbs: Array<{ id: string; name: string }>; currentFolderId: string | null }>(`/api/v1/workspaces/${workspaceId}${folder ? `?folder=${encodeURIComponent(folder)}` : ''}`) : null;
    return <NavigationPage title={result.assessment.name} description={`Started ${displayDate(result.attempt.startAt)} · ${result.attempt.submitAt ? `Submitted ${displayDate(result.attempt.submitAt)}` : 'In progress'}`} backHref={withQuery(`/navigation/assessments/${assessmentId}`, contextQuery)}>
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">{workspaceId && <Link className="text-primary text-sm underline underline-offset-4" href={`/navigation/workspaces/${workspaceId}`}>Open full workspace</Link>}{!result.attempt.submitAt && can(session, 'attempt.submit', result.assessment.offering.institution.id, result.assessment.offering.id, selected?.roleAssignmentId ?? undefined) && <SubmitAttempt attemptId={attemptId} roleAssignmentId={selected?.roleAssignmentId} />}</div>
        {result.attempt.group && <p className="text-muted-foreground mb-4 text-sm">Group: {result.attempt.group.name} · {result.attempt.group.members.map(member => member.displayName).join(', ')}</p>}
        {result.result && <section className="bg-card mb-5 rounded-xl border p-5"><h2 className="font-semibold">Official result: {result.result.mark.score} / {result.result.mark.outOf}</h2><p className="text-muted-foreground mt-2 whitespace-pre-wrap text-sm">{result.result.mark.feedback || 'No additional feedback.'}</p>{result.result.note && <p className="mt-2 text-sm">{result.result.note}</p>}</section>}
        {result.canReplay && result.sessions.length > 0 && <section className="bg-card mb-5 rounded-xl border p-5"><h2 className="mb-3 font-semibold">Recorded work</h2><div className="grid gap-3">{result.sessions.map(recording => <div key={recording.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"><div><p className="font-medium">{recording.toolName}</p><p className="text-muted-foreground mt-1 text-xs">{displayDate(recording.startedAt)} · {recording.eventBatchCount} recorded batches</p></div><LaunchReplayButton workSessionId={recording.id} roleAssignmentId={selected?.roleAssignmentId} /></div>)}</div></section>}
        {workspace && workspaceId && <div className="h-[38rem] overflow-hidden rounded-xl border"><WorkspaceDetail workspaceId={workspaceId} readOnly={workspace.data.readOnly} currentFolderId={workspace.currentFolderId} folderIds={[...new Set(workspace.documents.filter(document => document.entryKind === 'FOLDER').map(document => document.id).concat(workspace.breadcrumbs.map(folder => folder.id)))]} breadcrumbs={workspace.breadcrumbs} documents={workspace.documents.map(document => ({ ...document, hash: document.contentHash, updated: document.updatedAt, downloadHref: document.entryKind === 'FILE' ? `/api/v1/workspaces/${workspaceId}/documents/${document.id}` : null }))} tools={workspace.tools} toolLaunch={workspace.data.readOnly ? null : { mode: 'WORK', attemptId, roleAssignmentId: selected?.roleAssignmentId }}/></div>}
    </NavigationPage>;
}
