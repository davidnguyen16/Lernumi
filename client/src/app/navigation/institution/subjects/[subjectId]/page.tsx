import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Subject overview" description="Subject identity and scheduled offerings." backHref="/navigation/institution/subjects"><NavigationEmptyState title="No subject selected" description="Subject details will appear here when available."/></NavigationPage>;
}
