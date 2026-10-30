'use client';

import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';

import { FormInput } from '@/components/form';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { LoginSchema, type LoginSchemaValue } from '@/lib/auth/validation/credentials';

export function SignInForm() {
    const form = useForm<LoginSchemaValue>({
        resolver: zodResolver(LoginSchema),
        mode: 'onBlur',
        defaultValues: { email: '', password: '' },
    });

    return (
        <form onSubmit={(event) => event.preventDefault()} noValidate>
            <FieldGroup>
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

                <Button type="submit" className="w-full" disabled>
                    Sign in
                </Button>
                <p className="text-muted-foreground text-center text-xs">
                    Sign in is currently unavailable.
                </p>
            </FieldGroup>
        </form>
    );
}
