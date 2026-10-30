import { OfferingsTable } from '@/components/admin/offerings/offerings-table';
import { parseListParams,parseOptionalUuid } from '@/lib/admin/list-params';
import type { CrudCapabilities } from '@/lib/admin/resource-capabilities';
export function OfferingsSection({ institutionId, searchParams, endpoint, assessmentBasePath, detailActionLabel, contextQuery, capabilities, emptyMessage }: {
    institutionId: string;
    searchParams: Record<string, string | string[] | undefined>;
    endpoint?: string;
    assessmentBasePath?: string | null;
    detailActionLabel?: string;
    contextQuery?: string;
    capabilities?: CrudCapabilities;
    allowedOfferingIds?: readonly string[] | null;
    emptyMessage?: string;
}) {
    const SORT_FIELDS = ['subject', 'term', 'assessments'] as const;
    const { page, sort, dir } = parseListParams(searchParams, SORT_FIELDS);
    const subjectId = parseOptionalUuid(searchParams.subject);
    const termId = parseOptionalUuid(searchParams.term);
    return (<OfferingsTable offerings={[]} institutionId={institutionId} subjects={[]} terms={[]} subjectId={subjectId} termId={termId} page={page} pageCount={1} total={0} sort={sort} dir={dir} endpoint={endpoint} assessmentBasePath={assessmentBasePath} detailActionLabel={detailActionLabel} contextQuery={contextQuery} capabilities={capabilities} emptyMessage={emptyMessage}/>);
}
