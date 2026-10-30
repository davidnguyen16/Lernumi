'use client';
import { ResourceFormPage,ResourceFormSection } from '@/components/admin/resource-form-page';
import { FormInput } from '@/components/form';
import { EMPTY_TOOL_FORM,ToolSchema,type ToolFormValue,type ToolSchemaValue,} from '@/lib/admin/tool-schema';
import { useRouter } from 'next/navigation';
export type ToolMetadata = ToolSchemaValue & {
    id: string;
};
export function ToolMetadataForm({ tool, backHref }: {
    tool?: ToolMetadata;
    backHref?: string;
}) {
    const router = useRouter();
    const back = () => router.push(backHref ?? (tool ? `/admin/tools/${tool.id}` : '/admin/tools'));
    return (<ResourceFormPage<ToolFormValue, ToolSchemaValue> schema={ToolSchema} emptyValues={EMPTY_TOOL_FORM} editing={tool && {
            id: tool.id,
            values: {
                code: tool.code,
                name: tool.name,
                description: tool.description ?? '',
                codeBaseLocation: tool.codeBaseLocation ?? '',
            },
        }} createEndpoint="" title={{ create: 'Tool details', edit: 'Edit tool details' }} description={{
            create: 'Tool identity and public metadata.',
            edit: 'Metadata changes do not modify any deployed package.',
        }} onCancel={back} onSaved={back}>
            {(form) => (<ResourceFormSection title="Tool identity" description="The code is a permanent URL identifier. Runtime code is managed separately as immutable versions.">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormInput control={form.control} name="name" label="Name" placeholder="Code Workspace" autoComplete="off"/>
                        <FormInput control={form.control} name="code" label="Code" description="Permanent and cannot be changed." readOnly className="font-mono"/>
                        <FormInput control={form.control} name="description" label="Description" description="Optional." placeholder="What students do with it." autoComplete="off"/>
                        <FormInput control={form.control} name="codeBaseLocation" label="Source code" description="Optional repository or project URL." placeholder="https://github.com/…" autoComplete="off"/>
                    </div>
                </ResourceFormSection>)}
        </ResourceFormPage>);
}
