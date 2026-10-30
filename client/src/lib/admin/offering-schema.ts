import { z } from 'zod';
export type OfferingSchemaValue = z.output<typeof OfferingSchema>;
export const OfferingSchema = z.strictObject({
    subjectId: z.uuid({ message: 'Choose a subject' }),
    termId: z.uuid({ message: 'Choose a term' }),
});
export type OfferingFormValue = z.input<typeof OfferingSchema>;
export const EMPTY_OFFERING_FORM: OfferingFormValue = { subjectId: '', termId: '' };
