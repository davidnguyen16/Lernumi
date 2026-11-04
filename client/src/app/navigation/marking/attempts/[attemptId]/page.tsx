import { AttemptReviewWorkspace } from '@/components/navigation/marking/attempt-review-workspace';
import { NavigationPage } from '@/components/navigation/page-shell';
import { NavigationEmptyState } from '@/components/navigation/page-shell';
import type { ReviewMark, ReviewFlag } from '@/components/navigation/marking/attempt-review-workspace';
import { learningApi as serverApi } from '@/lib/learning/server';
import { learningContext, withQuery } from '@/lib/learning/server';
import { displayDate, type LearningAttempt, type LearningAssessment, type LearningResult } from '@/lib/learning/types';
import Link from 'next/link';
import { LaunchReplayButton } from '@/components/navigation/learning/replay-actions';
export default async function Page({ params, searchParams }: PageProps<'/navigation/marking/attempts/[attemptId]'>) {
    const { attemptId } = await params;
    const { session, selected, institutionId, query, contextQuery } = await learningContext(await searchParams);
    if (!institutionId) return <NavigationPage title="Attempt review"><NavigationEmptyState title="Choose an institution" description="Select the role associated with the attempt." /></NavigationPage>;
    const result = await serverApi<{ attempt: LearningAttempt; assessment: LearningAssessment; student: { displayName: string; email: string }; marks: ReviewMark[]; flags: ReviewFlag[]; result: LearningResult | null; canViewMarks: boolean; canReplay: boolean; canMark: boolean; canFinalise: boolean; replay: { toolName: string; workSessionId: string } | null; sessions: Array<{ id: string; toolName: string; startedAt: string; eventBatchCount: number }> }>(withQuery(`/api/v1/institutions/${institutionId}/attempts/${attemptId}`, query));
    const own = result.attempt.userId === session.user.id || result.attempt.group?.members.some(member => member.userId === session.user.id);
    return <NavigationPage title={result.assessment.name} description={`${result.student.displayName} · ${result.student.email} · Started ${displayDate(result.attempt.startAt)}`} backHref={withQuery('/navigation/marking', contextQuery)}>
        {result.attempt.workspaceId && <Link className="text-primary mb-5 inline-block text-sm underline underline-offset-4" href={`/navigation/workspaces/${result.attempt.workspaceId}`}>{result.attempt.submitAt ? 'Open submitted workspace' : 'Open attempt workspace'}</Link>}
        {result.result && <p className="bg-card mb-5 rounded-lg border p-4 font-medium">Official result: {result.result.mark.score} / {result.result.mark.outOf}</p>}
        {result.canReplay && result.sessions.length > 1 && <section className="bg-card mb-5 rounded-xl border p-5"><h2 className="mb-3 font-semibold">Recorded sessions</h2><div className="grid gap-3">{result.sessions.map(recording => <div key={recording.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"><div><p className="font-medium">{recording.toolName}</p><p className="text-muted-foreground mt-1 text-xs">{displayDate(recording.startedAt)} · {recording.eventBatchCount} recorded batches</p></div><LaunchReplayButton workSessionId={recording.id} roleAssignmentId={selected?.roleAssignmentId} /></div>)}</div></section>}
        <AttemptReviewWorkspace institutionId={institutionId} attemptId={attemptId} roleAssignmentId={selected?.roleAssignmentId} marks={result.marks} flags={result.flags} canViewMarks={result.canViewMarks} canReplay={result.canReplay} canMark={result.canMark} canFinalise={result.canFinalise && !own} replay={result.replay}/>
    </NavigationPage>;
}
