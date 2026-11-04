import Link from 'next/link';
import { Button } from '@/components/ui/button';

export default function NotFound() {
    return <main className="mx-auto flex min-h-[60vh] max-w-lg flex-col justify-center gap-4 p-6"><h1 className="font-heading text-2xl font-semibold">Page not found</h1><p className="text-muted-foreground text-sm">This item may have been removed or is outside your account&apos;s access.</p><Button className="w-fit" render={<Link href="/navigation/dashboard" />}>Back to dashboard</Button></main>;
}
