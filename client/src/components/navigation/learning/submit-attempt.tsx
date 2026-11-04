'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api/client';

export function SubmitAttempt({ attemptId, roleAssignmentId }: { attemptId: string; roleAssignmentId?: string | null }) {
  const [confirming, setConfirming] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  async function submit() {
    setPending(true);
    try { await api(`/api/v1/me/attempts/${attemptId}/submit`, { method: 'POST', body: roleAssignmentId ? { roleAssignmentId } : {} }); setConfirming(false); toast.success('Attempt submitted. Your workspace is now read-only.'); router.refresh(); }
    catch (error) { toast.error(error instanceof Error ? error.message : 'Could not submit the attempt.'); }
    finally { setPending(false); }
  }
  if (!confirming) return <Button onClick={() => setConfirming(true)}>Submit attempt</Button>;
  return <section className="bg-card rounded-lg border p-4"><p className="text-sm">Submitting locks this workspace for you and every group member. Check that all files have uploaded before continuing.</p><div className="mt-3 flex justify-end gap-2"><Button variant="outline" disabled={pending} onClick={() => setConfirming(false)}>Keep working</Button><Button disabled={pending} onClick={() => void submit()}>{pending ? 'Submitting…' : 'Confirm submission'}</Button></div></section>;
}
