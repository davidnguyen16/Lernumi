import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Assessment attempt" description="Your assessment workspace." backHref="/navigation/workspaces"><NavigationEmptyState title="No attempt selected" description="Attempt details will appear here when available."/></NavigationPage>;
}
