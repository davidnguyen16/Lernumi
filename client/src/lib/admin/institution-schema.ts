import { z } from 'zod';
export type InstitutionSchemaValue = z.output<typeof InstitutionSchema>;
const websiteSchema = z
    .string()
    .trim()
    .max(2048, { message: 'Website must not exceed 2048 characters' })
    .nullable()
    .transform((value) => (value === '' ? null : value))
    .refine((value) => {
    if (value === null || !z.url().safeParse(value).success)
        return value === null;
    const protocol = new URL(value).protocol;
    return protocol === 'https:' || protocol === 'http:';
}, { message: 'Website must be an http:// or https:// URL' });
export const InstitutionSchema = z.strictObject({
    name: z
        .string()
        .trim()
        .min(1, { message: 'Name is required' })
        .max(200, { message: 'Name must not exceed 200 characters' }),
    website: websiteSchema,
});
export const EMPTY_INSTITUTION_FORM = { name: '', website: '' };
export type InstitutionFormValue = z.input<typeof InstitutionSchema>;
