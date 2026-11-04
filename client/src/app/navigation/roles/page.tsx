import { RoleManagement } from '@/components/navigation/role-management';
import { all, roleContexts, type Institution } from '@/lib/access-data';
export default async function Page() { const [contexts, institutions] = await Promise.all([roleContexts(), all<Institution>('/api/v1/institution-directory')]); return <RoleManagement contexts={contexts} institutions={institutions} />; }
