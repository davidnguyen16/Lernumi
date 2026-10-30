import type { Metadata } from 'next';

import { LegalPage } from '@/components/legal/legal-page';

export const metadata: Metadata = { title: 'Terms of Service ? Lernumi' };

export default function TermsPage() {
    return <LegalPage title="Terms of Service" intro="" sections={[]} />;
}
