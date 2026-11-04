import { NavigationPage } from '@/components/navigation/page-shell';
import { AssessmentWorkspace } from '@/components/navigation/learning/assessment-workspace';
import { learningApi as serverApi } from '@/lib/learning/server';
import { can } from '@/lib/api/context';
import { learningContext, withQuery } from '@/lib/learning/server';
import type { LearningAssessment, LearningAttempt, LearningGroup } from '@/lib/learning/types';

export default async function Page({ params, searchParams }: PageProps<'/navigation/assessments/[assessmentId]'>) {
    const { assessmentId } = await params;
    const { session, selected, query, contextQuery } = await learningContext(await searchParams);
    const { assessment } = await serverApi<{ assessment: LearningAssessment }>(withQuery(`/api/v1/me/assessments/${assessmentId}`, query));
    const canStart = can(session, 'attempt.start', assessment.offering.institution.id, assessment.offering.id, selected?.roleAssignmentId ?? undefined);
    const canReadOwn = can(session, 'attempt.view_own', assessment.offering.institution.id, assessment.offering.id, selected?.roleAssignmentId ?? undefined);
    const [attemptData, groupData] = await Promise.all([
        canReadOwn ? serverApi<{ attempts: LearningAttempt[] }>(withQuery(`/api/v1/me/attempts?assessmentId=${assessmentId}&perPage=100`, query)) : Promise.resolve({ attempts: [] }),
        assessment.isGroup ? serverApi<{ groups: LearningGroup[] }>(withQuery(`/api/v1/me/assessments/${assessmentId}/groups`, query)) : Promise.resolve({ groups: [] }),
    ]);
    return <NavigationPage title={assessment.name} description={`${assessment.offering.subject.code} · ${assessment.offering.subject.name}`} backHref={withQuery(`/navigation/subjects/${assessment.offering.id}`, contextQuery)}><AssessmentWorkspace assessment={assessment} attempts={attemptData.attempts} groups={groupData.groups} canStart={canStart} canManageGroups={can(session, 'assessment.update', assessment.offering.institution.id, assessment.offering.id, selected?.roleAssignmentId ?? undefined)} userId={session.user.id} roleAssignmentId={selected?.roleAssignmentId} contextQuery={contextQuery} /></NavigationPage>;
}
