'use client';
import { Button } from '@/components/ui/button';
import { SourceCodeIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { launchTool } from '@/lib/api/tools';
export function LaunchReplayButton({ workSessionId, roleAssignmentId }: { workSessionId?: string | null; roleAssignmentId?: string | null }) {
    const [pending, setPending] = useState(false);
    async function launch() {
        if (!workSessionId || pending) return;
        setPending(true);
        try { await launchTool({ mode: 'REPLAY', workSessionId, ...(roleAssignmentId ? { roleAssignmentId } : {}) }); }
        catch (error) { toast.error(error instanceof Error ? error.message : 'Could not open replay.'); }
        finally { setPending(false); }
    }
    return <Button size="sm" variant="outline" disabled={pending || !workSessionId} onClick={() => void launch()}><HugeiconsIcon icon={SourceCodeIcon} strokeWidth={2}/>{pending ? 'Opening…' : 'Open replay'}</Button>;
}
