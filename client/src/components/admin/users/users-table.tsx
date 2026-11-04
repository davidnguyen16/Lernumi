'use client';
import { useListQuery } from '@/components/admin/use-list-query';
import { Badge } from '@/components/ui/badge';
import { DataTable,columnHelperFor,type AdminColumnDef } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Shield01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useMemo, useState } from 'react';
import { api } from '@/lib/api/client';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
export type UserListItem = {
    id: string;
    email: string;
    firstName: string;
    lastName: string;
    isSystemAdmin: boolean;
    isActive: boolean;
    joined: string;
    counts: {
        roles: number;
        attempts: number;
    };
};
const column = columnHelperFor<UserListItem>();
export function UsersTable({ users, currentUserId, page, pageCount, total, sort, dir, }: {
    users: UserListItem[];
    currentUserId: string;
    page: number;
    pageCount: number;
    total: number;
    sort: string;
    dir: 'asc' | 'desc';
}) {
    const list = useListQuery();
    const columns = useMemo(() => [
        column.accessor('lastName', {
            header: 'Name',
            cell: (info) => (<div className="flex flex-col">
                            <span className="font-medium">
                                {info.row.original.firstName} {info.row.original.lastName}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {info.row.original.email}
                            </span>
                        </div>),
        }),
        column.accessor((row) => row.counts.roles, {
            id: 'roles',
            header: 'Active roles',
            enableSorting: false,
            cell: (info) => <span className="tabular-nums">{info.getValue()}</span>,
        }),
        column.accessor((row) => row.counts.attempts, {
            id: 'attempts',
            header: 'Attempts started',
            cell: (info) => <span className="tabular-nums">{info.getValue()}</span>,
        }),
        column.accessor('joined', {
            id: 'createdAt',
            header: 'Joined',
            cell: (info) => (<span className="text-muted-foreground text-sm">{info.getValue()}</span>),
        }),
        column.accessor('isSystemAdmin', {
            header: 'Admin',
            cell: (info) => (<AdminToggle user={info.row.original} currentUserId={currentUserId}/>),
        }),
        column.accessor('isActive', { header: 'Active', cell: (info) => <AccountToggle user={info.row.original} field="isActive" disabled={info.row.original.id === currentUserId} /> }),
    ] as Array<AdminColumnDef<UserListItem>>, [currentUserId]);
    return (<DataTable columns={columns} data={users} emptyMessage={list.input
            ? `No accounts match "${list.input}".`
            : page > 1
                ? `Page ${page} is past the end. There ${total === 1 ? 'is' : 'are'} only ${total}.`
                : 'No accounts yet.'} toolbar={<>
                    <Input value={list.input} onChange={(event) => list.setInput(event.target.value)} placeholder="Search by name or email…" className="max-w-xs" aria-label="Search accounts"/>
                    <span className="text-muted-foreground shrink-0 text-sm tabular-nums">
                        {total} total
                    </span>
                    <div className="flex-1"/>

                    <Badge variant="secondary">Accounts are created by signing up</Badge>
                </>} server={{
            page,
            pageCount,
            sort,
            dir,
            pending: list.isPending,
            onSort: (columnId) => list.setParams({
                sort: columnId,
                dir: sort === columnId && dir === 'asc' ? 'desc' : 'asc',
                page: null,
            }),
            onPage: (next) => list.setParams({ page: next === 1 ? null : next }),
        }}/>);
}
function AdminToggle({ user, currentUserId }: {
    user: UserListItem;
    currentUserId: string;
}) {
    const isSelf = user.id === currentUserId;
    return (<div className="flex flex-col gap-1">
            <div className="flex items-center gap-2">
                <AccountToggle user={user} field="isAdmin" />
                {user.isSystemAdmin && (<HugeiconsIcon icon={Shield01Icon} strokeWidth={2} className="text-brand-teal size-4"/>)}
            </div>

            {isSelf && <span className="text-muted-foreground text-xs">You</span>}
        </div>);
}
function AccountToggle({ user, field, disabled = false }: { user: UserListItem; field: 'isAdmin' | 'isActive'; disabled?: boolean }) {
    const [pending, setPending] = useState(false);
    const router = useRouter();
    const checked = field === 'isAdmin' ? user.isSystemAdmin : user.isActive;
    async function update(value: boolean) {
        setPending(true);
        try { await api(`/api/v1/admin/users/${user.id}`, { method: 'PATCH', body: { [field]: value } }); router.refresh(); }
        catch (error) { toast.error(error instanceof Error ? error.message : 'Could not update this account.'); }
        finally { setPending(false); }
    }
    return <Switch checked={checked} disabled={disabled || pending} onCheckedChange={value => void update(value)} aria-label={`${field === 'isAdmin' ? 'System admin' : 'Active account'} for ${user.email}`} />;
}
