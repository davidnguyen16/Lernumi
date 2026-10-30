import { InstitutionTabs } from '@/components/admin/institutions/institution-tabs';
import { Breadcrumb,BreadcrumbItem,BreadcrumbLink,BreadcrumbList,BreadcrumbPage,BreadcrumbSeparator,} from '@/components/ui/breadcrumb';
import Link from 'next/link';
export default async function InstitutionLayout({ children, params, }: LayoutProps<'/admin/institutions/[id]'>) {
    const { id } = await params;
    return (<div className="flex flex-col gap-5 pt-1">
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink render={<Link href="/admin/institutions"/>}>
                            Institutions
                        </BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Institution</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>

            <h1 className="font-heading text-xl font-semibold tracking-tight">
                Institution
            </h1>

            <InstitutionTabs institutionId={id}/>

            {children}
        </div>);
}
