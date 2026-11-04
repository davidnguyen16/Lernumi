'use client';

import { Button } from '@/components/ui/button';
import Link from 'next/link';

export default function AppError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
    return <main className="mx-auto flex min-h-[60vh] max-w-lg flex-col justify-center gap-4 p-6"><h1 className="font-heading text-2xl font-semibold">We could not load this page</h1><p className="text-muted-foreground text-sm">Please try again. If the problem continues, check that the backend is running and your account still has access.</p><div className="flex gap-3"><Button onClick={reset}>Try again</Button><Button variant="outline" render={<Link href="/navigation/dashboard" />}>Dashboard</Button></div></main>;
}
