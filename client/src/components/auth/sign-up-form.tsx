'use client';

import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';

import { FormInput } from '@/components/form';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { SignUpSchema, type SignUpSchemaValue } from '@/lib/auth/validation/credentials';

export function SignUpForm() {
    const form = useForm<SignUpSchemaValue>({
        resolver: zodResolver(SignUpSchema),
        mode: 'onBlur',
        defaultValues: { email: '', password: '', firstName: '', lastName: '' },
    });

    return (
        <form onSubmit={(event) => event.preventDefault()} noValidate>
            <FieldGroup>
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
                    description="At least 12 characters."
                    type="password"
                    autoComplete="new-password"
                />

                <Button type="submit" className="w-full" disabled>
                    Create account
                </Button>
                <p className="text-muted-foreground text-center text-xs">
                    Account creation is currently unavailable.
                </p>
            </FieldGroup>
        </form>
    );
}
