import { z } from 'zod';
export const PAGE_SIZE = 10;
export type ListParams<TSortField extends string> = {
    q: string;
    page: number;
    sort: TSortField;
    dir: 'asc' | 'desc';
    skip: number;
};
export function parseListParams<TSortField extends string>(searchParams: Record<string, string | string[] | undefined>, sortFields: readonly [
    TSortField,
    ...TSortField[]
]): ListParams<TSortField> {
    const schema = z.object({
        q: z.string().trim().max(200).catch(''),
        page: z.coerce.number().int().min(1).catch(1),
        sort: z.enum(sortFields).catch(sortFields[0]),
        dir: z.enum(['asc', 'desc']).catch('asc'),
    });
    const first = (value: string | string[] | undefined) => Array.isArray(value) ? value[0] : value;
    const parsed = schema.parse({
        q: first(searchParams.q),
        page: first(searchParams.page),
        sort: first(searchParams.sort),
        dir: first(searchParams.dir),
    });
    return { ...parsed, skip: (parsed.page - 1) * PAGE_SIZE };
}
export function pageCountOf(total: number): number {
    return Math.max(1, Math.ceil(total / PAGE_SIZE));
}
export function parseOptionalUuid(value: string | string[] | undefined): string | null {
    const first = Array.isArray(value) ? value[0] : value;
    const parsed = z.uuid().safeParse(first);
    return parsed.success ? parsed.data : null;
}
