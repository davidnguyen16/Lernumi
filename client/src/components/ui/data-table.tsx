'use client';
import { useState, type ReactNode } from 'react';
import { columnFilteringFeature, createColumnHelper, createCoreRowModel, createFilteredRowModel, createPaginatedRowModel, createSortedRowModel, filterFns, rowPaginationFeature, rowSortingFeature, sortFns, tableFeatures, useTable, type ColumnDef, type RowData, type TableFeatures, } from '@tanstack/react-table';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowDown01Icon, ArrowLeft01Icon, ArrowRight01Icon, ArrowUp01Icon, ArrowUpDownIcon, } from '@hugeicons/core-free-icons';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, } from '@/components/ui/table';
const FEATURES = tableFeatures({
    rowSortingFeature,
    columnFilteringFeature,
    rowPaginationFeature,
    coreRowModel: createCoreRowModel(),
    sortedRowModel: createSortedRowModel(),
    filteredRowModel: createFilteredRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    sortFns,
    filterFns,
});
export function columnHelperFor<TData extends RowData>() {
    return createColumnHelper<typeof FEATURES, TData>();
}
export type AdminColumnDef<TData extends RowData> = ColumnDef<typeof FEATURES, TData, unknown>;
export type ServerControls = {
    page: number;
    pageCount: number;
    sort: string | null;
    dir: 'asc' | 'desc';
    onSort: (columnId: string) => void;
    onPage: (page: number) => void;
    pending?: boolean;
};
export function DataTable<TData extends RowData>({ columns, data, filterColumnId, filterPlaceholder = 'Search…', emptyMessage = 'Nothing here yet.', toolbar, pageSize = 10, server, }: {
    columns: Array<AdminColumnDef<TData>>;
    data: TData[];
    filterColumnId?: string;
    filterPlaceholder?: string;
    emptyMessage?: ReactNode;
    toolbar?: ReactNode;
    pageSize?: number;
    server?: ServerControls;
}) {
    const [filter, setFilter] = useState('');
    const table = useTable({
        features: FEATURES,
        columns: columns as unknown as Array<ColumnDef<TableFeatures, RowData, unknown>>,
        data: data as RowData[],
        initialState: { pagination: { pageIndex: 0, pageSize } },
    });
    const rows = table.getRowModel().rows;
    const pageCount = server ? server.pageCount : table.getPageCount();
    const pageIndex = server ? server.page - 1 : table.state.pagination.pageIndex;
    const canPrevious = server ? server.page > 1 : table.getCanPreviousPage();
    const canNext = server ? server.page < server.pageCount : table.getCanNextPage();
    return (<div className="flex flex-col gap-4">
            {((filterColumnId && !server) || toolbar) && (<div className="flex w-full flex-wrap items-center gap-2">
                    {filterColumnId && !server && (<Input value={filter} onChange={(event) => {
                    const next = event.target.value;
                    setFilter(next);
                    table.getColumn(filterColumnId)?.setFilterValue(next);
                    table.setPageIndex(0);
                }} placeholder={filterPlaceholder} className="max-w-xs"/>)}


                    {filterColumnId && !server && <div className="flex-1"/>}

                    {server ? (<div className="flex w-full flex-wrap items-center gap-2">{toolbar}</div>) : (toolbar)}
                </div>)}


            <div className={cn('overflow-hidden rounded-xl border transition-opacity', server?.pending && 'pointer-events-none opacity-60')}>
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (<TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                const sortable = header.column.getCanSort();
                const sorted = server
                    ? server.sort === header.column.id
                        ? server.dir
                        : false
                    : header.column.getIsSorted();
                return (<TableHead key={header.id}>
                                            {header.isPlaceholder ? null : sortable ? (<button type="button" onClick={server
                            ? () => server.onSort(header.column.id)
                            : header.column.getToggleSortingHandler()} className="hover:text-foreground -ml-2 inline-flex items-center gap-1 rounded px-2 py-1 transition-colors">
                                                    <table.FlexRender header={header}/>
                                                    <HugeiconsIcon icon={sorted === 'asc'
                            ? ArrowUp01Icon
                            : sorted === 'desc'
                                ? ArrowDown01Icon
                                : ArrowUpDownIcon} strokeWidth={2} className="size-3.5 opacity-60"/>
                                                </button>) : (<table.FlexRender header={header}/>)}
                                        </TableHead>);
            })}
                            </TableRow>))}
                    </TableHeader>

                    <TableBody>
                        {rows.length === 0 ? (<TableRow>
                                <TableCell colSpan={columns.length} className="text-muted-foreground h-28 text-center">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>) : (rows.map((row) => (<TableRow key={row.id}>

                                    {row.getAllCells().map((cell) => (<TableCell key={cell.id}>
                                            <table.FlexRender cell={cell}/>
                                        </TableCell>))}
                                </TableRow>)))}
                    </TableBody>
                </Table>
            </div>


            {pageCount > 1 && (<div className="flex items-center justify-between">
                    <span className="text-muted-foreground text-sm tabular-nums">
                        Page {pageIndex + 1} of {pageCount}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => server ? server.onPage(server.page - 1) : table.previousPage()} disabled={!canPrevious}>
                            <HugeiconsIcon icon={ArrowLeft01Icon} strokeWidth={2}/>
                            Previous
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => server ? server.onPage(server.page + 1) : table.nextPage()} disabled={!canNext}>
                            Next
                            <HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2}/>
                        </Button>
                    </div>
                </div>)}
        </div>);
}
