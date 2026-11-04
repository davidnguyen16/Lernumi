export type LearningSearch = Record<string, string | string[] | undefined>;
export type LearningTool = { id: string; code: string; name: string; description: string | null };
export type LearningOffering = {
  id: string;
  institution: { id: string; name: string };
  subject: { id: string; code: string; name: string; description: string | null };
  term: { id: string; year: number; session: number; periods: Array<{ id: string; name: string; type: 'TEACHING' | 'BREAK' | 'EXAM'; startAt: string; endAt: string }> };
  publishedAssessmentCount: number | null;
  assessments?: LearningAssessment[] | null;
};
export type LearningAssessment = {
  id: string; name: string; description: string | null; weight: number; hurdle: boolean; isGroup: boolean;
  publishAt: string | null; dueAt: string | null; expectedDue: string | null; ownAttemptCount: number | null;
  offering: LearningOffering; tools?: LearningTool[];
};
export type LearningGroup = { id: string; assessmentId: string; name: string; createdById: string; locked: boolean; members: Array<{ id: string; userId: string; displayName: string; email: string }> };
export type LearningAttempt = {
  id: string; userId: string; assessmentId: string; groupId: string | null; startAt: string; submitAt: string | null;
  workspaceId: string | null; workspace: { id: string; name: string; lastOpenedAt: string | null } | null;
  group?: LearningGroup | null;
};
export type WorkspaceDto = {
  id: string; name: string; kind: 'PERSONAL' | 'ASSIGNMENT'; documentCount: number; createdAt: string; updatedAt: string;
  lastOpenedAt: string | null; submitted: boolean; readOnly: boolean;
  attempt: { id: string; assessmentId: string; assessmentName: string; submitAt: string | null; offeringId: string } | null;
};
export type DocumentDto = { id: string; name: string; workspaceId: string; parentFolderId: string | null; entryKind: 'FILE' | 'FOLDER'; contentHash: string | null; mimeType: string | null; sizeBytes: number | null; updatedAt: string };
export type ApiPage<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number; per_page: number } };
export type LearningMark = {
  id: string; attemptId: string; markedById: string; markerName: string; markerEmail: string;
  score: string | null; outOf: string; feedback: string | null; status: 'DRAFT' | 'SUBMITTED' | 'SUPERSEDED';
  createdAt: string; submittedAt: string | null; official: boolean; own: boolean;
};
export type LearningResult = { attemptId: string; markId: string; note: string | null; mark: LearningMark; finalisedAt: string };
export function first(value: string | string[] | undefined): string | undefined { return Array.isArray(value) ? value[0] : value; }
export function displayDate(value: string | null | undefined): string { return value ? new Intl.DateTimeFormat('en-AU', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Australia/Sydney' }).format(new Date(value)) : 'Not set'; }
