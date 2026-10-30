import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="My subjects" description="Your subjects and study periods."><NavigationEmptyState title="No subjects yet" description="Subjects will appear here when available."/></NavigationPage>;
}
