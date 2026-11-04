'use client';

import { PictureField } from '@/components/media/picture-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { api } from '@/lib/api/client';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

export function ProfileSettings({ firstName: initialFirstName, lastName: initialLastName, email, avatarKey, emailVerified = true }: {
    firstName: string; lastName: string; email: string; avatarKey: string | null; emailVerified?: boolean;
}) {
    const router = useRouter();
    const [firstName, setFirstName] = useState(initialFirstName);
    const [lastName, setLastName] = useState(initialLastName);
    const [nextEmail, setNextEmail] = useState(email);
    const [emailPassword, setEmailPassword] = useState('');
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    async function save() {
        setPending(true); setError(null);
        try {
            await api('/api/v1/me', { method: 'PATCH', body: { firstName, lastName, name: `${firstName} ${lastName}`.trim(), ...(nextEmail !== email ? { email: nextEmail, currentPassword: emailPassword } : {}) } });
            setEmailPassword('');
            toast.success('Profile updated.'); router.refresh();
        } catch (error) { setError(error instanceof Error ? error.message : 'Could not save your profile.'); }
        finally { setPending(false); }
    }
    async function resendVerification() {
        setPending(true); setError(null);
        try { await api('/api/v1/auth/email/verification-notification', { method: 'POST' }); toast.success('Verification email requested.'); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not request verification.'); }
        finally { setPending(false); }
    }
    const initials = `${firstName.at(0) ?? ''}${lastName.at(0) ?? ''}`.toUpperCase();
    return <div className="grid items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <section aria-label="Profile picture"><PictureField pictureKey={avatarKey} fallback={initials || '?'} alt="Your profile picture" layout="profile" endpoint="/api/v1/me/avatar" fileField="avatar" /></section>
        <section className="rounded-xl border p-6"><h2 className="font-heading text-lg font-semibold">Identity</h2><p className="text-muted-foreground mt-1 text-sm">Your name is shown beside role assignments, requests and assessment work.</p>
            <div className="mt-6 grid gap-5 sm:grid-cols-2"><Field label="First name"><Input value={firstName} onChange={event => setFirstName(event.target.value)} /></Field><Field label="Last name"><Input value={lastName} onChange={event => setLastName(event.target.value)} /></Field><Field label="Email"><Input type="email" value={nextEmail} onChange={event => setNextEmail(event.target.value)} /></Field>{nextEmail !== email && <Field label="Current password to change email"><Input type="password" autoComplete="current-password" value={emailPassword} onChange={event => setEmailPassword(event.target.value)} /></Field>}</div>
            {!emailVerified && <div className="mt-4 flex flex-wrap items-center gap-3"><p className="text-muted-foreground text-sm">Verify your email before using automatic institution enrollment.</p><Button variant="outline" size="sm" disabled={pending} onClick={() => void resendVerification()}>Resend verification email</Button></div>}
            {error && <p className="text-destructive mt-4 text-sm" role="alert">{error}</p>}
            <div className="mt-6 flex justify-end border-t pt-5"><Button disabled={pending} onClick={() => void save()}>{pending && <Spinner />}Save changes</Button></div>
        </section>
    </div>;
}

type Session = { id: string; ipAddress: string | null; userAgent: string | null; lastActivity: number; current: boolean };
export function SecuritySettings() {
    const router = useRouter();
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [sessions, setSessions] = useState<Session[]>([]);
    useEffect(() => { let active = true; api<{ data: Session[] }>('/api/v1/auth/sessions').then(result => { if (active) setSessions(result.data); }).catch(() => { if (active) setError('Could not load your active sessions.'); }); return () => { active = false; }; }, []);
    async function changePassword() {
        setPending(true); setError(null);
        try { await api('/api/v1/auth/password', { method: 'PUT', body: { currentPassword, password: newPassword, password_confirmation: newPassword } }); toast.success('Password changed. Sign in again.'); window.location.assign('/auth'); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not change your password.'); }
        finally { setPending(false); }
    }
    async function signOutEverywhere() {
        setPending(true); setError(null);
        try { await api('/api/v1/auth/logout-all', { method: 'POST' }); window.location.assign('/auth'); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not sign out.'); setPending(false); }
    }
    async function revoke(session: Session) {
        setPending(true); setError(null);
        try { await api(`/api/v1/auth/sessions/${encodeURIComponent(session.id)}`, { method: 'DELETE' }); if (session.current) { window.location.assign('/auth'); return; } setSessions(current => current.filter(item => item.id !== session.id)); router.refresh(); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not revoke this session.'); }
        finally { setPending(false); }
    }
    return <div className="space-y-6">{error && <p role="alert" className="text-destructive text-sm">{error}</p>}<section className="max-w-3xl rounded-xl border p-6"><h2 className="font-heading text-lg font-semibold">Password</h2><p className="text-muted-foreground mt-1 text-sm">Changing your password signs out all sessions.</p><div className="mt-6 grid gap-5 sm:grid-cols-2"><Field label="Current password"><Input type="password" autoComplete="current-password" value={currentPassword} onChange={event => setCurrentPassword(event.target.value)} /></Field><Field label="New password"><Input type="password" autoComplete="new-password" value={newPassword} onChange={event => setNewPassword(event.target.value)} /></Field></div><p className="text-muted-foreground mt-3 text-xs">Use at least 10 characters with letters and numbers.</p><div className="mt-6 flex justify-end border-t pt-5"><Button disabled={pending || !currentPassword || !newPassword} onClick={() => void changePassword()}>{pending && <Spinner />}Change password</Button></div></section><section className="max-w-3xl rounded-xl border p-6"><h2 className="font-heading text-lg font-semibold">Active sessions</h2><ul className="mt-4 divide-y">{sessions.map(session => <li key={session.id} className="flex items-center gap-4 py-4"><div className="min-w-0 flex-1"><p className="text-sm font-medium">{session.current ? 'This session' : session.ipAddress ?? 'Another device'}</p><p className="text-muted-foreground truncate text-xs">{session.userAgent}</p><p className="text-muted-foreground text-xs">Last active {new Date(session.lastActivity * 1000).toLocaleString()}</p></div><Button size="sm" variant="outline" disabled={pending} onClick={() => void revoke(session)}>Sign out</Button></li>)}</ul><Button variant="outline" className="mt-5" disabled={pending} onClick={() => void signOutEverywhere()}>Sign out everywhere</Button></section></div>;
}
function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="space-y-2 text-sm font-medium"><span>{label}</span>{children}</label>; }
