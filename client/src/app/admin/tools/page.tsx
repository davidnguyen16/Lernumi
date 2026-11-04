import { ToolsTable } from '@/components/admin/tools/tools-table';
import { parseListParams } from '@/lib/admin/list-params';
import { all, type Query } from '@/lib/access-data';
import type { ToolData } from '@/lib/tool-data';
export default async function AdminToolsPage({ searchParams }: PageProps<'/admin/tools'>) {
    const query: Query = await searchParams;
    const { q, page, sort, dir } = parseListParams(query, ['name', 'code', 'versions', 'assessments'] as const);
    const tools = (await all<ToolData>('/api/v1/admin/tools')).filter(tool => !q || (tool.name + tool.code).toLowerCase().includes(q.toLowerCase()));
    tools.sort((a, b) => { const left = sort === 'versions' || sort === 'assessments' ? a.counts[sort] : a[sort]; const right = sort === 'versions' || sort === 'assessments' ? b.counts[sort] : b[sort]; return (typeof left === 'number' && typeof right === 'number' ? left - right : String(left).localeCompare(String(right))) * (dir === 'asc' ? 1 : -1); });
    return <div className="flex flex-col gap-6 pt-1"><section><h1 className="font-heading text-xl font-semibold">Tools</h1><p className="text-muted-foreground mt-2 text-sm">Independent applications and their immutable runtime packages.</p></section><ToolsTable tools={tools.slice((page - 1) * 10, page * 10)} page={page} pageCount={Math.max(1, Math.ceil(tools.length / 10))} total={tools.length} sort={sort} dir={dir} /></div>;
}
