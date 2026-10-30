'use client';
import { Button } from '@/components/ui/button';
import { Combobox,ComboboxContent,ComboboxEmpty,ComboboxInput,ComboboxItem,ComboboxList,} from '@/components/ui/combobox';
import { Select,SelectContent,SelectItem,SelectTrigger,SelectValue,} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ArrowRight01Icon,UniversityIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';
import { toast } from 'sonner';
export type InstitutionRequestOption = {
    id: string;
    name: string;
    website: string | null;
};
type RoleOption = {
    id: string;
    roleName: string;
    scopeType: 'INSTITUTION' | 'OFFERING';
    pending: boolean;
};
export function RoleRequestForm({ institutions, }: {
    institutions: InstitutionRequestOption[];
}) {
    const [institution, setInstitution] = useState<InstitutionRequestOption | null>(null);
    const [roles, setRoles] = useState<RoleOption[]>([]);
    const [roleId, setRoleId] = useState<string | null>(null);
    const [loadingRoles, setLoadingRoles] = useState(false);
    const [submitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    function chooseInstitution(value: InstitutionRequestOption | null) {
        setInstitution(value);
        setRoleId(null);
        setRoles([]);
        setError(null);
        setLoadingRoles(false);
    }
    async function submitRequest() {
        toast.info('This action is unavailable.');
    }
    const rolePlaceholder = !institution
        ? 'Choose an institution first'
        : loadingRoles
            ? 'Loading available roles…'
            : roles.length === 0
                ? 'No roles are available to request'
                : 'Choose a role';
    return (<div className="p-8 sm:p-10">
            <div className="mb-8 flex items-start gap-3">
                <div className="bg-brand-teal/10 text-brand-teal mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg">
                    <HugeiconsIcon icon={UniversityIcon} strokeWidth={2} className="size-5"/>
                </div>
                <div>
                    <h2 className="font-heading text-xl font-semibold">Request a role</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Start by finding your institution.
                    </p>
                </div>
            </div>

            <div className="space-y-6">
                <div className="space-y-2">
                    <label htmlFor="institution" className="text-sm font-medium">
                        Institution
                    </label>
                    <Combobox items={institutions} value={institution} onValueChange={chooseInstitution} itemToStringLabel={(item: InstitutionRequestOption) => item.name} itemToStringValue={(item: InstitutionRequestOption) => item.id} isItemEqualToValue={(item: InstitutionRequestOption, value: InstitutionRequestOption) => item.id === value.id}>
                        <ComboboxInput id="institution" className="w-full" placeholder="Search by institution name" autoComplete="organization" showClear/>
                        <ComboboxContent>
                            <ComboboxEmpty>No institution found.</ComboboxEmpty>
                            <ComboboxList>
                                {(item: InstitutionRequestOption) => (<ComboboxItem key={item.id} value={item}>
                                        <span className="flex min-w-0 flex-col">
                                            <span className="truncate font-medium">{item.name}</span>
                                            {item.website && (<span className="text-muted-foreground truncate text-xs">
                                                    {item.website}
                                                </span>)}
                                        </span>
                                    </ComboboxItem>)}
                            </ComboboxList>
                        </ComboboxContent>
                    </Combobox>
                    <p className="text-muted-foreground text-xs">
                        All institutions registered with Lernumi are searchable here.
                    </p>
                </div>

                <div className="space-y-2">
                    <label htmlFor="requested-role" className="text-sm font-medium">
                        Requested role
                    </label>
                    <Select items={Object.fromEntries(roles.map((role) => [role.id, role.roleName]))} value={roleId} onValueChange={(value) => setRoleId(value)} disabled={!institution || loadingRoles || roles.length === 0}>
                        <SelectTrigger id="requested-role" className="w-full">
                            {loadingRoles && <Spinner />}
                            <SelectValue placeholder={rolePlaceholder}/>
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((role) => (<SelectItem key={role.id} value={role.id} disabled={role.pending}>
                                    <span>{role.roleName}</span>
                                    <span className="text-muted-foreground text-xs">
                                        {role.scopeType === 'INSTITUTION'
                ? 'Institution'
                : 'Offering · scope assigned later'}
                                    </span>
                                    {role.pending && (<span className="text-muted-foreground text-xs">Pending</span>)}
                                </SelectItem>))}
                        </SelectContent>
                    </Select>
                </div>

                {error && (<p role="alert" className="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border px-3 py-2 text-sm">
                        {error}
                    </p>)}

                <Button type="button" disabled className="mt-1 w-full sm:w-auto" onClick={() => void submitRequest()}>
                    {submitting && <Spinner />}
                    Send request
                    <HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2} data-icon="inline-end"/>
                </Button>
            </div>

            <p className="text-muted-foreground border-border/70 mt-8 border-t pt-5 text-xs leading-5">
                Submitting a request does not grant access immediately. The institution controls
                approval and can revoke an assigned role later.
            </p>
        </div>);
}
