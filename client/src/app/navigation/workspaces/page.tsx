import { NavigationPage } from '@/components/navigation/page-shell';
import { WorkspaceList } from '@/components/navigation/workspaces/workspace-list';
import { serverApi, requireSession } from '@/lib/api/server';
import { displayDate, type ApiPage, type WorkspaceDto } from '@/lib/learning/types';
import { first } from '@/lib/learning/types';
import Link from 'next/link';
export default async function Page({ searchParams }: PageProps<'/navigation/workspaces'>) {
    await requireSession();
    const page = Math.max(1, Number(first((await searchParams).page)) || 1);
    const result = await serverApi<ApiPage<WorkspaceDto>>(`/api/v1/workspaces?per_page=30&page=${page}`);
    return <NavigationPage title="Workspaces" description="Your assessment work and personal documents."><WorkspaceList workspaces={result.data.map(workspace => ({ id: workspace.id, name: workspace.name, kind: workspace.kind, detail: workspace.attempt?.assessmentName ?? 'Personal workspace', updated: displayDate(workspace.lastOpenedAt ?? workspace.updatedAt), documentCount: workspace.documentCount, submitted: workspace.submitted, href: `/navigation/workspaces/${workspace.id}` }))}/><div className="mt-6 flex justify-between text-sm">{page > 1 ? <Link href={`/navigation/workspaces?page=${page - 1}`}>Previous</Link> : <span />}{page < result.meta.last_page && <Link href={`/navigation/workspaces?page=${page + 1}`}>Next</Link>}</div></NavigationPage>;
}
