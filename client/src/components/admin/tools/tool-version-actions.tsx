'use client';
import { Button } from '@/components/ui/button';
import { Download01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
export function ToolVersionActions({ active }: {
    toolId: string;
    versionId: string;
    active: boolean;
}) {
    return <div className="flex items-center justify-end gap-2">
        <Button variant="ghost" size="icon-sm" disabled aria-label="Download original package"><HugeiconsIcon icon={Download01Icon} strokeWidth={2}/></Button>
        <Button variant="outline" size="sm" disabled>{active ? 'Active' : 'Make active'}</Button>
    </div>;
}
