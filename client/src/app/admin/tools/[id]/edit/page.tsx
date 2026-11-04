import { ToolMetadataForm } from '@/components/admin/tools/tool-metadata-form';
import { getTool } from '@/lib/tool-data';
export default async function EditToolPage({ params }: PageProps<'/admin/tools/[id]/edit'>) { const { id } = await params; const tool = await getTool(id); return <ToolMetadataForm tool={tool} backHref={'/admin/tools/' + id} />; }
