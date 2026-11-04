'use client';
import { Button } from '@/components/ui/button';
import { Download01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { api } from '@/lib/api/client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
export function ToolVersionActions({ toolId, versionId, active }: { toolId: string; versionId: string; active: boolean }) {
    const [pending, setPending] = useState(false);
    const router = useRouter();
    async function activate() {
        setPending(true);
        try { await api(`/api/v1/admin/tools/${toolId}/versions/${versionId}/activate`, { method: 'POST' }); router.refresh(); toast.success('Active version updated.'); }
        catch (error) { toast.error(error instanceof Error ? error.message : 'Could not activate this version.'); }
        finally { setPending(false); }
    }
    return <div className="flex items-center justify-end gap-2"><Button variant="ghost" size="icon-sm" nativeButton={false} render={<a href={`/api/v1/admin/tools/${toolId}/versions/${versionId}/download`} />} aria-label="Download original package"><HugeiconsIcon icon={Download01Icon} strokeWidth={2} /></Button><Button variant="outline" size="sm" disabled={active || pending} onClick={() => void activate()}>{pending ? 'Activating…' : active ? 'Active' : 'Make active'}</Button></div>;
}
