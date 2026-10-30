import { z } from 'zod';
export type RoleAssignmentSchemaValue = {
    userId: string;
    roleId: string;
    offeringId: string | null;
};
export const INSTITUTION_SCOPE_VALUE = 'institution';
export const UNSCOPED_OFFERING_VALUE = 'unscoped';
const optionalOfferingId = z
    .string()
    .nullable()
    .transform((value) => value === null ||
    value === '' ||
    value === INSTITUTION_SCOPE_VALUE ||
    value === UNSCOPED_OFFERING_VALUE
    ? null
    : value)
    .refine((value) => value === null || z.uuid().safeParse(value).success, {
    message: 'Choose a valid scope',
});
export const RoleAssignmentSchema = z.strictObject({
    userId: z.uuid({ message: 'Choose a user' }),
    roleId: z.uuid({ message: 'Choose a role' }),
    offeringId: optionalOfferingId,
});
export type RoleAssignmentFormValue = z.input<typeof RoleAssignmentSchema>;
export const EMPTY_ROLE_ASSIGNMENT_FORM: RoleAssignmentFormValue = {
    userId: '',
    roleId: '',
    offeringId: '',
};
