import { ToolPackageForm } from '@/components/admin/tools/tool-package-form';
import { getTool } from '@/lib/tool-data';
export default async function NewToolVersionPage({ params }: PageProps<'/admin/tools/[id]/versions/new'>) { const { id } = await params; const tool = await getTool(id); return <ToolPackageForm tool={tool} versionOnly backHref={'/admin/tools/' + id} />; }
