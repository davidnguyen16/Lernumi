'use client';
import { AlertDialog,AlertDialogAction,AlertDialogCancel,AlertDialogContent,AlertDialogDescription,AlertDialogFooter,AlertDialogHeader,AlertDialogTitle,AlertDialogTrigger,} from '@/components/ui/alert-dialog';
import { Spinner } from '@/components/ui/spinner';
import { useState,type ReactElement,type ReactNode } from 'react';
export function ResourceActionDialog({ title, description, confirmLabel, destructive = false, trigger }: {
    endpoint: string;
    method?: 'POST' | 'PATCH' | 'DELETE';
    title: string;
    description: ReactNode;
    confirmLabel: string;
    destructive?: boolean;
    trigger: ReactElement;
    onCompleted?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const working = false;
    const [error, setError] = useState<string | null>(null);
    async function confirm() {
        setError('This action is unavailable.');
    }
    return (<AlertDialog open={open} onOpenChange={(next) => {
            setOpen(next);
            if (!next)
                setError(null);
        }}>
            <AlertDialogTrigger nativeButton={false} render={trigger}/>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                {error && (<div role="alert" className="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border px-3 py-2 text-sm">
                        {error}
                    </div>)}
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={working}>Cancel</AlertDialogCancel>
                    <AlertDialogAction variant={destructive ? 'destructive' : 'default'} disabled onClick={(event) => {
            event.preventDefault();
            void confirm();
        }}>
                        {working && <Spinner />}
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>);
}
