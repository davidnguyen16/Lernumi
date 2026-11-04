'use client';
import { AlertDialog,AlertDialogAction,AlertDialogCancel,AlertDialogContent,AlertDialogDescription,AlertDialogFooter,AlertDialogHeader,AlertDialogTitle,AlertDialogTrigger,} from '@/components/ui/alert-dialog';
import { Spinner } from '@/components/ui/spinner';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { cloneElement,useState,type ComponentProps,type ReactElement,type ReactNode } from 'react';
import { api } from '@/lib/api/client';
import { useRouter } from 'next/navigation';
export function DeleteResourceDialog({ endpoint, title, description, trigger }: {
    endpoint: string;
    title: string;
    description: ReactNode;
    trigger: ReactElement;
}) {
    const [open, setOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const router = useRouter();
    const [error, setError] = useState<string | null>(null);
    // Let Menu.Item supply its menuitem role and roving tabIndex. An outer
    // Dialog.Trigger would replace them with standalone button semantics.
    const dialogTrigger = trigger.type === DropdownMenuItem
        ? cloneElement(trigger as ReactElement<ComponentProps<typeof DropdownMenuItem>>, {
            render: <AlertDialogTrigger nativeButton={false} render={<div />} />,
        })
        : <AlertDialogTrigger render={trigger} />;
    async function confirmDelete() {
        setDeleting(true);
        setError(null);
        try {
            await api(endpoint, { method: 'DELETE' });
            setOpen(false);
            router.refresh();
        } catch (error) { setError(error instanceof Error ? error.message : 'Could not delete this record.'); }
        finally { setDeleting(false); }
    }
    return (<AlertDialog open={open} onOpenChange={(next) => {
            setOpen(next);
            if (!next)
                setError(null);
        }}>
            {dialogTrigger}

            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>

                {error && (<div role="alert" className="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border px-3 py-2 text-sm">
                        {error}
                    </div>)}

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" disabled={deleting} onClick={(event) => {
            event.preventDefault();
            void confirmDelete();
        }}>
                        {deleting && <Spinner />}
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>);
}
