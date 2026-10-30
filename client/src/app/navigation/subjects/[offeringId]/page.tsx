import { NavigationEmptyState,NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Subject overview" description="Assessments and study details." backHref="/navigation/subjects"><NavigationEmptyState title="No subject selected" description="Select a subject to view its assessments."/></NavigationPage>;
}
