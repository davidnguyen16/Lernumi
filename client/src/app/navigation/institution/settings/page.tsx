import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Institution profile" description="Public identity and institution details."><NavigationEmptyState title="No institution selected" description="Institution details will appear here when available."/></NavigationPage>;
}
