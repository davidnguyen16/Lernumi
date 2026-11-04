import { MarkingWorkspace } from '@/components/navigation/marking/marking-workspace';
import { NavigationPage, NavigationEmptyState } from '@/components/navigation/page-shell';
import type { MarkingTermSummary } from '@/components/navigation/marking/marking-workspace';
import { serverApi } from '@/lib/api/server';
import { learningContext, withQuery } from '@/lib/learning/server';
import { first } from '@/lib/learning/types';
export default async function Page({ searchParams }: PageProps<'/navigation/marking'>) {
    const search = await searchParams;
    const { session, selected, institutionId, query, contextQuery } = await learningContext(search);
    const allowed = session.isAdmin || selected?.permissions.some(permission => ['attempt.view_all', 'attempt.mark', 'attempt.result.finalize', 'tool_session.replay_all', 'assessment.view'].includes(permission));
    if (!institutionId || !allowed) return <NavigationPage title="Marking & replay" description="Review assessment work, feedback and recordings."><NavigationEmptyState title="Marking access required" description="Choose an institution role with permission to review attempts." /></NavigationPage>;
    const filters = new URLSearchParams(query);
    for (const key of ['assessmentId', 'subjectId', 'termId', 'studentId']) { const value = first(search[key]); if (value) filters.set(key, value); }
    const { terms } = await serverApi<{ terms: MarkingTermSummary[] }>(withQuery(`/api/v1/institutions/${institutionId}/marking`, filters.toString()));
    const targetAssessment = first(search.assessmentId);
    const targetOffering = first(search.offeringId);
    const selectedTerm = terms.find(term => term.subjects.some(subject => subject.offeringId === targetOffering || (targetAssessment && subject.students.some(student => student.attempts.some(attempt => attempt.assessmentId === targetAssessment)))));
    const selectedSubject = selectedTerm?.subjects.find(subject => subject.offeringId === targetOffering || (targetAssessment && subject.students.some(student => student.attempts.some(attempt => attempt.assessmentId === targetAssessment))));
    const links = new URLSearchParams(contextQuery); links.set('institution', institutionId);
    return <NavigationPage title="Marking & replay" description="Review assessment work, feedback and recordings."><MarkingWorkspace terms={terms} initialSelection={{ termId: first(search.termId) ?? selectedTerm?.id, subjectId: first(search.subjectId) ?? selectedSubject?.id, studentId: first(search.studentId) }} contextQuery={links.toString()} canBrowseAttempts/></NavigationPage>;
}
