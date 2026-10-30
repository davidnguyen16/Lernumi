import type { ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Upload01Icon } from '@hugeicons/core-free-icons';

import { pictureUrl } from '@/components/media/picture-url';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const SHAPE = {
    full: 'rounded-full after:rounded-full',
    lg: 'rounded-lg after:rounded-lg',
} as const;

export function PictureField({
    pictureKey,
    fallback,
    alt,
    rounded = 'full',
    layout = 'row',
}: {
    pictureKey: string | null;
    fallback: ReactNode;
    alt: string;
    rounded?: 'full' | 'lg';
    layout?: 'row' | 'column' | 'profile';
}) {
    if (layout === 'profile') {
        return (
            <div className="w-full max-w-64">
                <div className="bg-muted relative aspect-square overflow-hidden rounded-xl border">
                    <Avatar className="size-full rounded-xl after:rounded-xl">
                        <AvatarImage
                            src={pictureUrl(pictureKey)}
                            alt={alt}
                            className="rounded-xl object-cover"
                        />
                        <AvatarFallback className="rounded-xl text-4xl font-semibold">
                            {fallback}
                        </AvatarFallback>
                    </Avatar>
                </div>
                <div className="mt-3 grid gap-2">
                    <Button type="button" variant="outline" size="sm" disabled>
                        <HugeiconsIcon icon={Upload01Icon} strokeWidth={2} />
                        Upload picture
                    </Button>
                    <p className="text-muted-foreground text-xs">
                        Picture upload is currently unavailable.
                    </p>
                </div>
            </div>
        );
    }

    const column = layout === 'column';

    return (
        <div
            className={cn(
                'flex gap-4',
                column ? 'flex-col items-start gap-2' : 'flex-wrap items-center',
            )}
        >
            <Avatar className={cn('size-16', SHAPE[rounded])}>
                <AvatarImage src={pictureUrl(pictureKey)} alt={alt} className={SHAPE[rounded]} />
                <AvatarFallback className={cn('text-base', SHAPE[rounded])}>
                    {fallback}
                </AvatarFallback>
            </Avatar>

            <div className="grid gap-2">
                <Button type="button" variant={column ? 'ghost' : 'outline'} size="sm" disabled>
                    <HugeiconsIcon icon={Upload01Icon} strokeWidth={2} />
                    Upload picture
                </Button>
                <p className="text-muted-foreground text-xs">
                    Picture upload is currently unavailable.
                </p>
            </div>
        </div>
    );
}
