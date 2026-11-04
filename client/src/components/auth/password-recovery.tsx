'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api/client';
import { FormAlert } from './form-alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function PasswordRecovery({ token, email = '' }: { token?: string; email?: string }) {
    const router = useRouter();
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    return <main className="mx-auto flex min-h-svh max-w-md items-center px-6"><form className="w-full space-y-5 rounded-xl border bg-card p-6" onSubmit={async event => {
        event.preventDefault();
        const fields = new FormData(event.currentTarget);
        setPending(true); setError(''); setMessage('');
        try {
            const body = token ? { token, email: fields.get('email'), password: fields.get('password'), password_confirmation: fields.get('confirmation') } : { email: fields.get('email') };
            const result = await api<{ message: string }>(`/api/v1/auth/${token ? 'reset-password' : 'forgot-password'}`, { method: 'POST', body });
            setMessage(result.message);
            if (token) router.replace('/auth?passwordReset=1');
        } catch (error) { setError(error instanceof Error ? error.message : 'Could not process this request.'); }
        finally { setPending(false); }
    }}><h1 className="font-heading text-2xl font-semibold">{token ? 'Reset password' : 'Forgot password'}</h1><FormAlert message={error} />{message && <p role="status" className="text-sm">{message}</p>}
        <div className="space-y-2"><Label htmlFor="recovery-email">Email</Label><Input id="recovery-email" name="email" type="email" autoComplete="email" defaultValue={email} required /></div>
        {token && <><div className="space-y-2"><Label htmlFor="recovery-password">New password</Label><Input id="recovery-password" name="password" type="password" autoComplete="new-password" minLength={10} required /></div><div className="space-y-2"><Label htmlFor="recovery-confirmation">Confirm password</Label><Input id="recovery-confirmation" name="confirmation" type="password" autoComplete="new-password" minLength={10} required /></div></>}
        <Button className="w-full" disabled={pending}>{pending ? 'Please wait…' : token ? 'Reset password' : 'Send reset link'}</Button><Link className="block text-center text-sm hover:underline" href="/auth">Back to sign in</Link>
    </form></main>;
}
