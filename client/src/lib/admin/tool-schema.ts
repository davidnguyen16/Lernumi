import { z } from 'zod';
export type ToolSchemaValue = z.output<typeof ToolSchema>;
const optionalText = (max: number) => z
    .string()
    .max(max)
    .nullable()
    .transform((value) => {
    const trimmed = value?.trim() ?? '';
    return trimmed === '' ? null : trimmed;
});
export const ToolSchema = z.strictObject({
    code: z
        .string()
        .trim()
        .toLowerCase()
        .min(2, { message: 'Code must be at least 2 characters' })
        .max(40)
        .regex(/^[a-z0-9-]+$/, {
        message: 'Code may only contain lowercase letters, numbers and hyphens',
    }),
    name: z.string().trim().min(1, { message: 'Name is required' }).max(120),
    description: optionalText(500),
    codeBaseLocation: optionalText(500),
});
export type ToolFormValue = z.input<typeof ToolSchema>;
export const EMPTY_TOOL_FORM: ToolFormValue = {
    code: '',
    name: '',
    description: '',
    codeBaseLocation: '',
};
