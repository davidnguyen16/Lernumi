import { ToolMetadataForm } from '@/components/admin/tools/tool-metadata-form';
export default async function EditToolPage({ params }: PageProps<'/admin/tools/[id]/edit'>) {
    const { id } = await params;
    return <ToolMetadataForm backHref={`/admin/tools/${id}`} />;
}
