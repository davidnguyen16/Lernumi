import { RolesSection } from '@/components/admin/roles/roles-section';
export default async function AdminRolesPage({ searchParams }: PageProps<'/admin/roles'>) {
    return (<div className="flex flex-col gap-6 pt-1">
            <section className="flex flex-col gap-1.5">
                <h1 className="font-heading text-xl font-semibold tracking-tight">Roles</h1>
                <p className="text-muted-foreground max-w-2xl text-sm">
                    Every role across all institutions. To manage one institution&rsquo;s roles, open that institution instead.
                </p>
            </section>

            <RolesSection searchParams={await searchParams}/>
        </div>);
}
