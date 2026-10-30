'use client';
import { PictureField } from '@/components/media/picture-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useState } from 'react';
import { toast } from 'sonner';
export function ProfileSettings({ firstName: initialFirstName, lastName: initialLastName, email, avatarKey, }: {
    firstName: string;
    lastName: string;
    email: string;
    avatarKey: string | null;
}) {
    const [firstName, setFirstName] = useState(initialFirstName);
    const [lastName, setLastName] = useState(initialLastName);
    const [pending] = useState(false);
    async function save() {
        toast.info('This action is unavailable.');
    }
    const initials = `${firstName.at(0) ?? ''}${lastName.at(0) ?? ''}`.toUpperCase();
    return (<div className="grid items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <section aria-label="Profile picture">
                <PictureField pictureKey={avatarKey} fallback={initials || '?'} alt="Your profile picture" layout="profile"/>
            </section>

            <section className="rounded-xl border p-6">
                <h2 className="font-heading text-lg font-semibold">Identity</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Your name is shown beside role assignments, requests and assessment work.
                </p>
                <div className="mt-6 grid gap-5 sm:grid-cols-2">
                    <Field label="First name">
                        <Input value={firstName} onChange={(event) => setFirstName(event.target.value)}/>
                    </Field>
                    <Field label="Last name">
                        <Input value={lastName} onChange={(event) => setLastName(event.target.value)}/>
                    </Field>
                    <Field label="Email">
                        <Input value={email} disabled/>
                    </Field>
                </div>
                <div className="mt-6 flex justify-end border-t pt-5">
                    <Button disabled onClick={() => void save()}>
                        {pending && <Spinner />}
                        Save changes
                    </Button>
                </div>
            </section>
        </div>);
}
export function SecuritySettings() {
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [pending] = useState(false);
    async function changePassword() {
        toast.info('This action is unavailable.');
    }
    async function signOutEverywhere() {
        toast.info('This action is unavailable.');
    }
    return <div className="space-y-6"><section className="max-w-3xl rounded-xl border p-6"><h2 className="font-heading text-lg font-semibold">Password</h2><p className="text-muted-foreground mt-1 text-sm">Changing it also closes every other active session.</p><div className="mt-6 grid gap-5 sm:grid-cols-2"><Field label="Current password"><Input type="password" autoComplete="current-password" value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)}/></Field><Field label="New password"><Input type="password" autoComplete="new-password" value={newPassword} onChange={(event) => setNewPassword(event.target.value)}/></Field></div><div className="mt-6 flex justify-end border-t pt-5"><Button disabled onClick={() => void changePassword()}>{pending && <Spinner />}Change password</Button></div></section><section className="max-w-3xl rounded-xl border p-6"><h2 className="font-heading text-lg font-semibold">Active sessions</h2><p className="text-muted-foreground mt-1 text-sm">Use this if you left the application open on a shared device.</p><Button variant="outline" className="mt-5" disabled onClick={() => void signOutEverywhere()}>Sign out everywhere</Button></section></div>;
}
function Field({ label, children }: {
    label: string;
    children: React.ReactNode;
}) { return <label className="space-y-2 text-sm font-medium"><span>{label}</span>{children}</label>; }
