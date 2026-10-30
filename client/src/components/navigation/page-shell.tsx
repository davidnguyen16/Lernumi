import { LearningPageHeader } from '@/components/navigation/learning/page-header';
import { buttonVariants } from '@/components/ui/button';
import { ArrowLeft01Icon,Folder01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
export function NavigationPage({ title, description, eyebrow, backHref, children }: {
    title: string;
    description?: string;
    eyebrow?: string;
    backHref?: string;
    children: React.ReactNode;
}) {
    return (<main className="mx-auto w-full max-w-7xl py-7">
            {backHref && <Link href={backHref} className={buttonVariants({ variant: 'ghost', size: 'sm', className: 'mb-4' })}><HugeiconsIcon icon={ArrowLeft01Icon} strokeWidth={2}/>Back</Link>}
            <LearningPageHeader title={title} description={description ?? ''} eyebrow={eyebrow}/>
            {children}
        </main>);
}
export function NavigationEmptyState({ title, description }: {
    title: string;
    description?: string;
}) {
    return (<section className="bg-card flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed px-6 py-12 text-center">
            <span className="bg-brand-teal/10 text-brand-teal flex size-11 items-center justify-center rounded-lg"><HugeiconsIcon icon={Folder01Icon} strokeWidth={2} className="size-5"/></span>
            <h2 className="font-heading mt-4 text-lg font-semibold">{title}</h2>
            {description && <p className="text-muted-foreground mt-1 max-w-md text-sm leading-6">{description}</p>}
        </section>);
}
