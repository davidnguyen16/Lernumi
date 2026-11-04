'use client';

import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';

import { FormInput } from '@/components/form';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { LoginSchema, type LoginSchemaValue } from '@/lib/auth/validation/credentials';
import { api, applyFormErrors } from '@/lib/api/client';
import { FormAlert } from './form-alert';
import Link from 'next/link';

export function SignInForm() {
    const form = useForm<LoginSchemaValue>({
        resolver: zodResolver(LoginSchema),
        mode: 'onBlur',
        defaultValues: { email: '', password: '' },
    });

    return (
        <form onSubmit={form.handleSubmit(async values => {
            try {
                await api('/api/v1/auth/login', { method: 'POST', body: values });
                // Reload across the authentication boundary to clear cached session data.
                window.location.replace('/navigation/dashboard');
            } catch (error) { applyFormErrors(error, form); }
        })} noValidate>
            <FieldGroup>
                <FormAlert message={form.formState.errors.root?.message} />
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
                    type="password"
                    autoComplete="current-password"
                />

                <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
                    {form.formState.isSubmitting ? 'Signing in…' : 'Sign in'}
                </Button>
                <p className="text-muted-foreground text-center text-xs">
                    <Link href="/forgot-password" className="hover:underline">Forgot your password?</Link>
                </p>
            </FieldGroup>
        </form>
    );
}
