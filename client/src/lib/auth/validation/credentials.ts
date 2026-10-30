import { z } from "zod";
export type SignUpSchemaValue = z.output<typeof SignUpSchema>;
export const emailSchema = z.string().trim().toLowerCase().max(254).pipe(z.email());
export const SignUpSchema = z.strictObject({
    email: emailSchema,
    password: z
        .string()
        .min(12, { message: "Password must be at least 12 character" })
        .max(128, { message: "Password must not exceed 128 characters" }),
    firstName: z.string().trim().min(1, { message: "First name must be at least 1 character" }).max(100),
    lastName: z.string().trim().min(1, { message: "Last name must be at least 1 character" }).max(100),
});
export type LoginSchemaValue = z.output<typeof LoginSchema>;
export const LoginSchema = z.strictObject({
    email: emailSchema,
    password: z.string().min(1, { message: "Password is required" }).max(128),
});
