import type { Metadata } from 'next';
import { Toaster } from 'sonner';

import { ThemeProvider, THEME_INIT_SCRIPT } from '@/components/theme-provider';
import './globals.css';

export const metadata: Metadata = {
    title: 'Lernumi',
    description: 'A learning platform whose tools record how the work happened.',
};

export default function RootLayout({ children }: LayoutProps<'/'>) {
    return (
        <html lang="en" className="h-full font-sans antialiased" suppressHydrationWarning>
            <head>
                <script dangerouslySetInnerHTML={{ __html: THEME_INIT_SCRIPT }} />
            </head>
            <body className="min-h-full bg-background text-foreground">
                <ThemeProvider>
                    {children}
                    <Toaster />
                </ThemeProvider>
            </body>
        </html>
    );
}
