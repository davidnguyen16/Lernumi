import { BookOpen01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import { cn } from '@/lib/utils';

export function BrandLogo({
    alt = '',
    className = 'size-9',
}: {
    alt?: string;
    className?: string;
}) {
    return (
        <span
            role={alt ? 'img' : undefined}
            aria-label={alt || undefined}
            aria-hidden={alt ? undefined : true}
            className={cn(
                'bg-brand-teal/10 text-brand-teal inline-flex shrink-0 items-center justify-center rounded-xl p-1.5',
                className,
            )}
        >
            <HugeiconsIcon icon={BookOpen01Icon} strokeWidth={2} className="size-full" aria-hidden />
        </span>
    );
}
