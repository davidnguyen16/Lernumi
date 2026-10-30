import { Button } from '@/components/ui/button';
import { Table,TableBody,TableCell,TableHead,TableHeader,TableRow } from '@/components/ui/table';
import Link from 'next/link';
export default async function ToolDetailPage({ params }: PageProps<'/admin/tools/[id]'>) {
    const { id } = await params;
    return <div className="flex flex-col gap-7 pt-1">
        <header className="flex flex-wrap items-start gap-4 border-b pb-6">
            <Button nativeButton={false} variant="ghost" render={<Link href="/admin/tools"/>}>Tools</Button>
            <div className="flex-1"><h1 className="font-heading text-2xl font-semibold tracking-tight">Tool details</h1><p className="text-muted-foreground mt-1 text-sm">No tool selected.</p></div>
            <Button nativeButton={false} variant="outline" render={<Link href={`/admin/tools/${id}/edit`}/>}>Edit details</Button>
            <Button nativeButton={false} render={<Link href={`/admin/tools/${id}/versions/new`}/>}>Upload version</Button>
        </header>
        <section><h2 className="font-heading mb-4 text-base font-semibold">Version history</h2>
            <div className="overflow-hidden rounded-xl border"><Table>
                <TableHeader><TableRow><TableHead>Version</TableHead><TableHead>Package</TableHead><TableHead>Uploaded by</TableHead><TableHead>Uploaded</TableHead><TableHead>Release notes</TableHead></TableRow></TableHeader>
                <TableBody><TableRow><TableCell colSpan={5} className="text-muted-foreground h-28 text-center">No package has been uploaded.</TableCell></TableRow></TableBody>
            </Table></div>
        </section>
    </div>;
}
