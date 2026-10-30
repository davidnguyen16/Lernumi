import { ScopeCodesTable } from '@/components/admin/scope-codes/scope-codes-table';
export type ScopeCodeCapabilities = {
    view: boolean;
    create: boolean;
    revoke: boolean;
};
export function ScopeCodesSection({ endpoint, contextQuery, capabilities }: {
    institutionId: string;
    endpoint: string;
    contextQuery: string;
    capabilities: ScopeCodeCapabilities;
}) {
    return (<ScopeCodesTable codes={[]} offerings={[]} endpoint={endpoint} contextQuery={contextQuery} capabilities={capabilities}/>);
}
