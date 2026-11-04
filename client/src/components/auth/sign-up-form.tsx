'use client';

import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';

import { FormInput } from '@/components/form';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { SignUpSchema, type SignUpSchemaValue } from '@/lib/auth/validation/credentials';
import { api, applyFormErrors } from '@/lib/api/client';
import { FormAlert } from './form-alert';

export function SignUpForm() {
    const form = useForm<SignUpSchemaValue>({
        resolver: zodResolver(SignUpSchema),
        mode: 'onBlur',
        defaultValues: { email: '', password: '', firstName: '', lastName: '' },
    });

    return (
        <form onSubmit={form.handleSubmit(async values => {
            try {
                await api('/api/v1/auth/register', { method: 'POST', body: { ...values, name: `${values.firstName} ${values.lastName}`.trim(), password_confirmation: values.password } });
                window.location.replace('/navigation/dashboard');
            } catch (error) { applyFormErrors(error, form); }
        })} noValidate>
            <FieldGroup>
                <FormAlert message={form.formState.errors.root?.message} />
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormInput
                        control={form.control}
                        name="firstName"
                        label="First name"
                        autoComplete="given-name"
                    />
                    <FormInput
                        control={form.control}
                        name="lastName"
                        label="Last name"
                        autoComplete="family-name"
                    />
                </div>

                <FormInput
                    control={form.control}
                    name="email"
                    label="Email"
                    type="email"
                    autoComplete="email"
                    placeholder="you@school.edu"
                />

                <FormInput
                    control={form.control}
                    name="password"
                    label="Password"
                    description="At least 10 characters, including letters and numbers; up to 72 bytes."
                    type="password"
                    autoComplete="new-password"
                />

                <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
                    {form.formState.isSubmitting ? 'Creating account…' : 'Create account'}
                </Button>
                <p className="text-muted-foreground text-center text-xs">
                    After signing up, request a role to join your institution.
                </p>
            </FieldGroup>
        </form>
    );
}
