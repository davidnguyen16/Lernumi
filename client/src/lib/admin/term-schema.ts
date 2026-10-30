import { z } from 'zod';
export type TermSchemaValue = z.output<typeof TermSchema>;
export const TermSchema = z.strictObject({
    institutionId: z.uuid({ message: 'Choose an institution' }),
    year: z.coerce
        .number({ message: 'Year is required' })
        .int({ message: 'Year must be a whole number' })
        .min(2000, { message: 'Year must be 2000 or later' })
        .max(2100, { message: 'Year must be 2100 or earlier' }),
    session: z.coerce
        .number({ message: 'Session is required' })
        .int({ message: 'Session must be a whole number' })
        .min(1, { message: 'Session must be 1 or more' })
        .max(12, { message: 'Session must be 12 or less' }),
});
export type TermFormValue = z.input<typeof TermSchema>;
export const EMPTY_TERM_FORM = {
    institutionId: '',
    year: String(new Date().getFullYear()),
    session: '1',
} as unknown as TermFormValue;
