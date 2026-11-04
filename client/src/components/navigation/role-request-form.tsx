'use client';
import { Button } from '@/components/ui/button';
import { Combobox,ComboboxContent,ComboboxEmpty,ComboboxInput,ComboboxItem,ComboboxList,} from '@/components/ui/combobox';
import { Select,SelectContent,SelectItem,SelectTrigger,SelectValue,} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ArrowRight01Icon,UniversityIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useRef, useState } from 'react';
import { api } from '@/lib/api/client';
import { useRouter } from 'next/navigation';
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
    const [submitting, setSubmitting] = useState(false);
    const router = useRouter();
    const roleLoad = useRef(0);
    const [error, setError] = useState<string | null>(null);
    function chooseInstitution(value: InstitutionRequestOption | null) {
        setInstitution(value);
        setRoleId(null);
        setRoles([]);
        setError(null);
        const load = ++roleLoad.current;
        if (!value) { setLoadingRoles(false); return; }
        setLoadingRoles(true);
        void Promise.all([
            api<{ data: RoleOption[] }>(`/api/v1/institutions/${value.id}/requestable-roles`),
            api<{ data: Array<{ roleId: string; status: string }> }>('/api/v1/me/role-requests?perPage=100'),
            api<{ data: Array<{ roleId: string }> }>('/api/v1/me/role-assignments?perPage=100'),
        ]).then(([available, requests, assignments]) => {
            if (load !== roleLoad.current) return;
            const held = new Set(assignments.data.map(item => item.roleId));
            setRoles(available.data.filter(role => !held.has(role.id)).map(role => ({ ...role, pending: requests.data.some(request => request.roleId === role.id && request.status === 'PENDING') })));
        }).catch(error => { if (load === roleLoad.current) setError(error instanceof Error ? error.message : 'Could not load roles.'); })
          .finally(() => { if (load === roleLoad.current) setLoadingRoles(false); });
    }
    async function submitRequest() {
        if (!institution || !roleId) return;
        setSubmitting(true); setError(null);
        try { await api(`/api/v1/institutions/${institution.id}/role-requests`, { method: 'POST', body: { roleId } }); setRoles(current => current.map(role => role.id === roleId ? { ...role, pending: true } : role)); setRoleId(null); toast.success('Request sent for approval.'); router.refresh(); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not send your request.'); }
        finally { setSubmitting(false); }
    }
    async function checkEnrollment() {
        if (!institution) return;
        setSubmitting(true); setError(null);
        try { await api(`/api/v1/institutions/${institution.id}/join`, { method: 'POST' }); toast.success('Institution enrollment checked.'); router.refresh(); }
        catch (error) { setError(error instanceof Error ? error.message : 'Could not check enrollment.'); }
        finally { setSubmitting(false); }
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

                <Button type="button" disabled={submitting || !roleId || loadingRoles} className="mt-1 w-full sm:w-auto" onClick={() => void submitRequest()}>
                    {submitting && <Spinner />}
                    Send request
                    <HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2} data-icon="inline-end"/>
                </Button>
                <Button type="button" variant="outline" disabled={submitting || !institution} className="w-full sm:w-auto" onClick={() => void checkEnrollment()}>Check institution email enrollment</Button>
            </div>

            <p className="text-muted-foreground border-border/70 mt-8 border-t pt-5 text-xs leading-5">
                Submitting a request does not grant access immediately. The institution controls
                approval and can revoke an assigned role later.
            </p>
        </div>);
}
