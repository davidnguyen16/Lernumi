import 'server-only';
import { requireSession, serverApi } from '@/lib/api/server';
import { getActiveContext } from '@/lib/api/context';
import { ApiError } from '@/lib/api/errors';
import { notFound } from 'next/navigation';
import { first, type LearningSearch } from './types';

export async function learningContext(search: LearningSearch = {}) {
  const session = await requireSession();
  const institution = first(search.institution);
  const selected = getActiveContext(search, session);
  const query = new URLSearchParams();
  if (selected?.roleAssignmentId) query.set('roleAssignmentId', selected.roleAssignmentId);
  if (institution) query.set('institutionId', institution);
  return { session, selected, institutionId: institution ?? selected?.institution.id, query: query.toString(), contextQuery: new URLSearchParams(Object.entries(search).filter((entry): entry is [string, string] => typeof entry[1] === 'string' && ['institution', 'role', 'offering'].includes(entry[0]))).toString() };
}
export function withQuery(path: string, query: string): string { return query ? `${path}${path.includes('?') ? '&' : '?'}${query}` : path; }

export async function learningApi<T>(path: string): Promise<T> {
  try { return await serverApi<T>(path); }
  catch (error) { if (error instanceof ApiError && error.status === 404) notFound(); throw error; }
}
