import { Button } from '@/components/ui/button';
import { SourceCodeIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
export function LaunchReplayButton() {
    return <Button size="sm" variant="outline" disabled><HugeiconsIcon icon={SourceCodeIcon} strokeWidth={2}/>Open replay</Button>;
}
