import { ToolPackageForm } from '@/components/admin/tools/tool-package-form';
export default async function NewToolVersionPage({ params }: PageProps<'/admin/tools/[id]/versions/new'>) {
    const { id } = await params;
    return <ToolPackageForm versionOnly backHref={`/admin/tools/${id}`} />;
}
