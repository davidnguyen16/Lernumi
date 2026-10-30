'use client';
import { AlertDialog,AlertDialogAction,AlertDialogCancel,AlertDialogContent,AlertDialogDescription,AlertDialogFooter,AlertDialogHeader,AlertDialogTitle,AlertDialogTrigger,} from '@/components/ui/alert-dialog';
import { Spinner } from '@/components/ui/spinner';
import { useState,type ReactElement } from 'react';
export type InstitutionDeleteTarget = {
    id: string;
    name: string;
    counts: {
        subjects: number;
        terms: number;
        roles: number;
        requests: number;
    };
};
export function DeleteInstitutionDialog({ institution, trigger, }: {
    institution: InstitutionDeleteTarget;
    trigger: ReactElement;
}) {
    const [open, setOpen] = useState(false);
    const deleting = false;
    const [error, setError] = useState<string | null>(null);
    async function confirmDelete() {
        setError('Deletion is unavailable.');
    }
    return (<AlertDialog open={open} onOpenChange={(next) => {
            setOpen(next);
            if (!next)
                setError(null);
        }}>
            <AlertDialogTrigger nativeButton={false} render={trigger}/>

            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete {institution.name}?</AlertDialogTitle>
                    <AlertDialogDescription>Deletion is unavailable.</AlertDialogDescription>
                </AlertDialogHeader>

                {error && (<div role="alert" className="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border px-3 py-2 text-sm">
                        {error}
                    </div>)}

                <AlertDialogFooter>
                    <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" disabled onClick={(event) => {
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
