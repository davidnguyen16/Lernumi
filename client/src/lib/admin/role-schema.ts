import { z } from 'zod';
export type RoleSchemaValue = z.output<typeof RoleSchema>;
export const RoleSchema = z
    .strictObject({
    institutionId: z.uuid({ message: 'Choose an institution' }),
    roleCode: z
        .string()
        .trim()
        .toLowerCase()
        .min(1, { message: 'Code is required' })
        .max(100)
        .regex(/^(institution|offering):[a-z0-9]+(?:[._-][a-z0-9]+)*$/, {
        message: 'Use a scope prefix and lowercase segments, e.g. offering:tutor',
    }),
    roleName: z.string().trim().min(1, { message: 'Name is required' }).max(80),
    scopeType: z.enum(['INSTITUTION', 'OFFERING'], { message: 'Choose a scope' }),
    requestMode: z.enum(['DISABLED', 'PUBLIC', 'CONDITIONAL'], {
        message: 'Choose who may request this role',
    }),
    autoGrantEmailRegex: z
        .string()
        .max(200)
        .nullable()
        .transform((value) => {
        const trimmed = value?.trim() ?? '';
        return trimmed === '' ? null : trimmed;
    })
        .refine((value) => {
        if (value === null)
            return true;
        try {
            new RegExp(value);
            return true;
        }
        catch {
            return false;
        }
    }, { message: 'Not a valid regular expression' }),
    includedRoleIds: z.array(z.uuid({ message: 'Invalid role selection' })).max(100),
    permissionIds: z.array(z.uuid({ message: 'Invalid permission selection' })).max(200),
    prerequisiteRoleIds: z.array(z.uuid({ message: 'Invalid prerequisite role' })).max(100),
})
    .superRefine((value, context) => {
    if (new Set(value.includedRoleIds).size !== value.includedRoleIds.length) {
        context.addIssue({
            code: 'custom',
            message: 'A role can only be included once',
            path: ['includedRoleIds'],
        });
    }
    if (new Set(value.permissionIds).size !== value.permissionIds.length) {
        context.addIssue({
            code: 'custom',
            message: 'A permission can only be selected once',
            path: ['permissionIds'],
        });
    }
    if (new Set(value.prerequisiteRoleIds).size !== value.prerequisiteRoleIds.length) {
        context.addIssue({
            code: 'custom',
            message: 'A prerequisite role can only be selected once',
            path: ['prerequisiteRoleIds'],
        });
    }
    if (value.requestMode === 'CONDITIONAL' && value.prerequisiteRoleIds.length === 0) {
        context.addIssue({
            code: 'custom',
            message: 'Choose at least one prerequisite role',
            path: ['prerequisiteRoleIds'],
        });
    }
    if (value.requestMode !== 'CONDITIONAL' && value.prerequisiteRoleIds.length > 0) {
        context.addIssue({
            code: 'custom',
            message: 'Only conditional roles can have prerequisites',
            path: ['prerequisiteRoleIds'],
        });
    }
    const expectedPrefix = value.scopeType === 'INSTITUTION' ? 'institution:' : 'offering:';
    if (!value.roleCode.startsWith(expectedPrefix)) {
        context.addIssue({
            code: 'custom',
            message: `Code must start with ${expectedPrefix}`,
            path: ['roleCode'],
        });
    }
});
export type RoleFormValue = z.input<typeof RoleSchema>;
export const EMPTY_ROLE_FORM = {
    institutionId: '',
    roleCode: 'offering:',
    roleName: '',
    scopeType: 'OFFERING',
    requestMode: 'DISABLED',
    autoGrantEmailRegex: '',
    includedRoleIds: [],
    permissionIds: [],
    prerequisiteRoleIds: [],
} as unknown as RoleFormValue;
