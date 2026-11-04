'use client';

import { useRef, useState, type ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Upload01Icon } from '@hugeicons/core-free-icons';
import { useRouter } from 'next/navigation';
import { pictureUrl } from '@/components/media/picture-url';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { api } from '@/lib/api/client';
import { cn } from '@/lib/utils';

const SHAPE = { full: 'rounded-full after:rounded-full', lg: 'rounded-lg after:rounded-lg' } as const;
export function PictureField({ pictureKey, fallback, alt, rounded = 'full', layout = 'row', endpoint, fileField = 'avatar', onChanged }: {
    pictureKey: string | null; fallback: ReactNode; alt: string; rounded?: 'full' | 'lg'; layout?: 'row' | 'column' | 'profile'; endpoint?: string; fileField?: string; onChanged?: () => void;
}) {
    const router = useRouter();
    const input = useRef<HTMLInputElement>(null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [revision, setRevision] = useState(0);
    async function upload(file?: File) {
        if (!file || !endpoint) return;
        if (file.size > 5 * 1024 * 1024) { setError('Choose an image no larger than 5 MiB.'); return; }
        setPending(true); setError(null);
        try { const data = new FormData(); data.set(fileField, file); await api(endpoint, { method: 'POST', body: data }); setRevision(Date.now()); onChanged?.(); router.refresh(); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not upload your picture.'); }
        finally { setPending(false); if (input.current) input.current.value = ''; }
    }
    async function remove() {
        if (!endpoint) return;
        setPending(true); setError(null);
        try { await api(endpoint, { method: 'DELETE' }); onChanged?.(); router.refresh(); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not remove the picture.'); }
        finally { setPending(false); }
    }
    const baseSrc = pictureUrl(pictureKey);
    const src = baseSrc && revision ? `${baseSrc}${baseSrc.includes('?') ? '&' : '?'}v=${revision}` : baseSrc;
    const controls = endpoint && <div className="grid gap-2"><input ref={input} className="sr-only" type="file" accept="image/jpeg,image/png,image/webp" aria-label={`Upload ${alt}`} onChange={event => void upload(event.target.files?.[0])} /><Button type="button" variant="outline" size="sm" disabled={pending} onClick={() => input.current?.click()}>{pending ? <Spinner /> : <HugeiconsIcon icon={Upload01Icon} strokeWidth={2} />}Upload picture</Button>{pictureKey && <Button type="button" variant="ghost" size="sm" disabled={pending} onClick={() => void remove()}>Remove picture</Button>}<p className="text-muted-foreground text-xs">JPEG, PNG or WebP, up to 5 MiB.</p>{error && <p className="text-destructive text-xs" role="alert">{error}</p>}</div>;
    if (layout === 'profile') return <div className="w-full max-w-64"><div className="bg-muted relative aspect-square overflow-hidden rounded-xl border"><Avatar className="size-full rounded-xl after:rounded-xl"><AvatarImage src={src} alt={alt} className="rounded-xl object-cover" /><AvatarFallback className="rounded-xl text-4xl font-semibold">{fallback}</AvatarFallback></Avatar></div><div className="mt-3">{controls}</div></div>;
    return <div className={cn('flex gap-4', layout === 'column' ? 'flex-col items-start gap-2' : 'flex-wrap items-center')}><Avatar className={cn('size-16', SHAPE[rounded])}><AvatarImage src={src} alt={alt} className={SHAPE[rounded]} /><AvatarFallback className={cn('text-base', SHAPE[rounded])}>{fallback}</AvatarFallback></Avatar>{controls}</div>;
}
