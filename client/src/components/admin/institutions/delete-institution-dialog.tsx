'use client';
import { DeleteResourceDialog } from '@/components/admin/delete-resource-dialog';
import type { ReactElement } from 'react';
export type InstitutionDeleteTarget = {
    id: string; name: string;
    counts: { subjects: number; terms: number; roles: number; requests: number };
};
export function DeleteInstitutionDialog({ institution, trigger }: { institution: InstitutionDeleteTarget; trigger: ReactElement }) {
    return <DeleteResourceDialog endpoint={`/api/v1/admin/institutions/${institution.id}`} title={`Delete ${institution.name}?`}
        description="This permanently removes the institution. Institutions with subjects, terms, roles or history must be retained."
        trigger={trigger}/>;
}
