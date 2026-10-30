import Link from 'next/link';

import { BrandLogo } from '@/components/brand-logo';

const LINKS = [
    { href: '/about', label: 'About' },
    { href: '/privacy', label: 'Privacy Policy' },
    { href: '/terms', label: 'Terms of Service' },
] as const;

export function SiteFooter() {
    return (
        <footer className="border-border bg-card border-t">
            <div className="mx-auto max-w-7xl px-5 py-10 text-center md:px-8">
                <nav
                    aria-label="Footer navigation"
                    className="mb-6 flex flex-wrap justify-center gap-x-8 gap-y-3"
                >
                    {LINKS.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="text-muted-foreground hover:text-foreground text-sm font-medium transition-colors"
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>

                <div className="text-muted-foreground flex flex-col items-center gap-3 text-sm">
                    <BrandLogo className="size-7" alt="Lernumi" />
                    <p>© 2026 Lernumi. All rights reserved.</p>
                </div>
            </div>
        </footer>
    );
}
