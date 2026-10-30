import { SubjectsSection } from '@/components/admin/subjects/subjects-section';
import { NavigationPage } from '@/components/navigation/page-shell';
export default function Page() {
    return <NavigationPage title="Subjects" description="Browse the institution subject catalog."><SubjectsSection searchParams={{}} lockedInstitutionId="" endpoint="" contextQuery="" subjectBasePath="/navigation/institution/subjects" capabilities={{ create: false, view: true, update: false, delete: false }}/></NavigationPage>;
}
