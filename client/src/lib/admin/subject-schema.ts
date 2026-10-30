import { z } from 'zod';
export type SubjectSchemaValue = z.output<typeof SubjectSchema>;
export const SubjectSchema = z.strictObject({
    institutionId: z.uuid({ message: 'Choose an institution' }),
    subjectCode: z
        .string()
        .trim()
        .toUpperCase()
        .min(2, { message: 'Code must be at least 2 characters' })
        .max(20)
        .regex(/^[A-Z0-9-]+$/, {
        message: 'Code may only contain letters, numbers and hyphens',
    }),
    subjectName: z.string().trim().min(1, { message: 'Name is required' }).max(200),
    subjectDescription: z
        .string()
        .max(1000)
        .nullable()
        .transform((value) => {
        const trimmed = value?.trim() ?? '';
        return trimmed === '' ? null : trimmed;
    }),
});
export type SubjectFormValue = z.input<typeof SubjectSchema>;
export const EMPTY_SUBJECT_FORM: SubjectFormValue = {
    institutionId: '',
    subjectCode: '',
    subjectName: '',
    subjectDescription: '',
};
