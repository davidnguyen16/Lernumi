import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Assessment" description="Assessment instructions and attempts." backHref="/navigation/subjects"><NavigationEmptyState title="No assessment selected" description="Assessment details will appear here when available."/></NavigationPage>;
}
