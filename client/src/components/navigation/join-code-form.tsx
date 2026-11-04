'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { toast } from 'sonner';
export function JoinCodeForm({ roles }: { roles: Array<{ id: string; name: string }> }) {
    const [assignmentId, setAssignmentId] = useState<string | null>(roles[0]?.id ?? null);
    const [code, setCode] = useState(''); const [pending, setPending] = useState(false); const [error, setError] = useState<string | null>(null); const router = useRouter();
    async function redeem() {
        if (!assignmentId || !code.trim()) return;
        setPending(true); setError(null);
        try { await api('/api/v1/scope-codes/redeem', { method: 'POST', body: { roleAssignmentId: assignmentId, code: code.trim() } }); setCode(''); toast.success('Offering access granted.'); router.refresh(); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not redeem this code.'); }
        finally { setPending(false); }
    }
    if (!roles.length) return null;
    return <section className="mb-8 rounded-xl border p-5"><h2 className="font-heading font-semibold">Have a one-time access code?</h2><p className="text-muted-foreground mt-1 text-sm">Use a code issued by your institution to add an offering to an existing role.</p><div className="mt-4 grid gap-3 md:grid-cols-[1fr_2fr_auto]"><Select value={assignmentId} onValueChange={setAssignmentId} items={Object.fromEntries(roles.map(role => [role.id, role.name]))}><SelectTrigger aria-label="Role receiving access"><SelectValue /></SelectTrigger><SelectContent>{roles.map(role => <SelectItem key={role.id} value={role.id}>{role.name}</SelectItem>)}</SelectContent></Select><Input aria-label="One-time access code" value={code} onChange={event => setCode(event.target.value)} autoComplete="off" placeholder="Paste your access code" /><Button disabled={pending || !assignmentId || !code.trim()} onClick={() => void redeem()}>{pending ? 'Redeeming…' : 'Redeem code'}</Button></div>{error && <p role="alert" className="text-destructive mt-3 text-sm">{error}</p>}</section>;
}
