'use client';
import { DeleteInstitutionDialog,type InstitutionDeleteTarget,} from '@/components/admin/institutions/delete-institution-dialog';
import { InstitutionFormPage } from '@/components/admin/institutions/institution-form-page';
import { useListQuery } from '@/components/admin/use-list-query';
import { Button,buttonVariants } from '@/components/ui/button';
import { DataTable,columnHelperFor,type AdminColumnDef } from '@/components/ui/data-table';
import { DropdownMenu,DropdownMenuContent,DropdownMenuItem,DropdownMenuSeparator,DropdownMenuTrigger,} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Add01Icon,ArrowRight01Icon,Delete02Icon,Edit02Icon,LinkSquare02Icon,MoreVerticalIcon,} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
import { useMemo,useState } from 'react';
export type InstitutionListItem = InstitutionDeleteTarget & {
    website: string | null;
};
const column = columnHelperFor<InstitutionListItem>();
export function InstitutionsTable({ institutions, page, pageCount, total, sort, dir, }: {
    institutions: InstitutionListItem[];
    page: number;
    pageCount: number;
    total: number;
    sort: string;
    dir: 'asc' | 'desc';
    query: string;
}) {
    const list = useListQuery();
    const [editing, setEditing] = useState<InstitutionListItem | 'create' | null>(null);
    const columns = useMemo(() => [
        column.accessor('name', {
            header: 'Name',
            cell: (info) => (<Link href={`/admin/institutions/${info.row.original.id}`} className="hover:text-primary font-medium hover:underline">
                            {info.getValue()}
                        </Link>),
        }),
        column.accessor('website', {
            header: 'Website',
            enableSorting: false,
            cell: (info) => {
                const website = info.getValue();
                if (!website) {
                    return <span className="text-muted-foreground">—</span>;
                }
                return (<a href={website} target="_blank" rel="noopener noreferrer" className="text-primary inline-flex items-center gap-1 hover:underline">
                                {website.replace(/^https?:\/\//, '')}
                                <HugeiconsIcon icon={LinkSquare02Icon} strokeWidth={2} className="size-3.5"/>
                            </a>);
            },
        }),
        column.accessor((row) => row.counts.subjects, {
            id: 'subjects',
            header: 'Subjects',
            cell: (info) => <span className="tabular-nums">{info.getValue()}</span>,
        }),
        column.accessor((row) => row.counts.terms, {
            id: 'terms',
            header: 'Terms',
            cell: (info) => <span className="tabular-nums">{info.getValue()}</span>,
        }),
        column.display({
            id: 'actions',
            header: '',
            cell: (info) => (<InstitutionActions institution={info.row.original} onEdit={() => setEditing(info.row.original)}/>),
        }),
    ] as Array<AdminColumnDef<InstitutionListItem>>, []);
    if (editing) {
        return (<InstitutionFormPage institution={editing === 'create' ? undefined : editing} onCancel={() => setEditing(null)}/>);
    }
    return (<DataTable columns={columns} data={institutions} emptyMessage={list.input
            ? `No institutions match "${list.input}".`
            : page > 1
                ? `Page ${page} is past the end. There ${total === 1 ? 'is' : 'are'} only ${total}.`
                : 'No institutions yet. Create the first one.'} toolbar={<>

                    <Input value={list.input} onChange={(event) => list.setInput(event.target.value)} placeholder="Search institutions…" className="max-w-xs" aria-label="Search institutions"/>

                    <span className="text-muted-foreground shrink-0 text-sm tabular-nums">
                        {total} total
                    </span>

                    <div className="flex-1"/>

                    <Button onClick={() => setEditing('create')}>
                        <HugeiconsIcon icon={Add01Icon} strokeWidth={2}/>
                        New institution
                    </Button>
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
function OpenSetupLink({ id }: {
    id: string;
}) {
    return (<div className="flex justify-end">
            <Link href={`/admin/institutions/${id}`} aria-label="Open setup" className={buttonVariants({ variant: 'ghost', size: 'icon-sm' })}>
                <HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2}/>
            </Link>
        </div>);
}
function InstitutionActions({ institution, onEdit, }: {
    institution: InstitutionListItem;
    onEdit: () => void;
}) {
    return (<div className="flex justify-end gap-1">
            <OpenSetupLink id={institution.id}/>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button variant="ghost" size="icon-sm" aria-label="Open actions">
                            <HugeiconsIcon icon={MoreVerticalIcon} strokeWidth={2}/>
                        </Button>}/>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={onEdit}>
                        <HugeiconsIcon icon={Edit02Icon} strokeWidth={2}/>
                        Edit details
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DeleteInstitutionDialog institution={institution} trigger={<DropdownMenuItem variant="destructive" closeOnClick={false}>
                                <HugeiconsIcon icon={Delete02Icon} strokeWidth={2}/>
                                Delete
                            </DropdownMenuItem>}/>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>);
}
