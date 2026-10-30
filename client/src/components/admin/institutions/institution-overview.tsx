'use client';
import { InstitutionFormPage,type InstitutionRow,} from '@/components/admin/institutions/institution-form-page';
import { Button } from '@/components/ui/button';
import { useState,type ReactNode } from 'react';
export function InstitutionOverview({ institution, heading, logo, canEdit = true, editEndpoint, children, }: {
    institution?: InstitutionRow;
    heading: ReactNode;
    logo?: ReactNode;
    canEdit?: boolean;
    editEndpoint?: string;
    children: ReactNode;
}) {
    const [editing, setEditing] = useState(false);
    if (editing) {
        return (<InstitutionFormPage formTitle={institution ? undefined : 'Institution details'} institution={institution} editEndpoint={editEndpoint} onCancel={() => setEditing(false)}/>);
    }
    return (<div className="flex flex-col gap-6 pt-1">
            <section className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex flex-wrap items-start gap-5">
                    {logo}
                    {heading}
                </div>
                {canEdit && (<Button variant="outline" onClick={() => setEditing(true)}>
                        Edit details
                    </Button>)}
            </section>
            {children}
        </div>);
}
