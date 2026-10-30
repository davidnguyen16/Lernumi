'use client';
import { Button } from '@/components/ui/button';
import { Select,SelectContent,SelectItem,SelectTrigger,SelectValue,} from '@/components/ui/select';
import { Cancel01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
export type InstitutionOption = {
    id: string;
    name: string;
};
export function institutionItems(institutions: InstitutionOption[]): Record<string, string> {
    return Object.fromEntries(institutions.map((institution) => [institution.id, institution.name]));
}
export function InstitutionFilter({ institutions, value, onChange, }: {
    institutions: InstitutionOption[];
    value: string | null;
    onChange: (value: string | null) => void;
}) {
    return (<div className="flex shrink-0 items-center gap-1">
            <Select items={institutionItems(institutions)} value={value ?? ''} onValueChange={(next) => onChange(next || null)}>
                <SelectTrigger className="w-52" aria-label="Filter by institution">
                    <SelectValue placeholder="All institutions"/>
                </SelectTrigger>
                <SelectContent>
                    {institutions.map((institution) => (<SelectItem key={institution.id} value={institution.id}>
                            {institution.name}
                        </SelectItem>))}
                </SelectContent>
            </Select>

            {value && (<Button variant="ghost" size="icon-sm" aria-label="Clear institution filter" onClick={() => onChange(null)}>
                    <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2}/>
                </Button>)}
        </div>);
}
