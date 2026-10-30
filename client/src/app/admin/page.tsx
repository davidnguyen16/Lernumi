import { ArrowRight01Icon,Key01Icon,Shield01Icon,ToolboxIcon,UniversityIcon,UserMultipleIcon,} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import Link from 'next/link';
const SECTIONS = [
    {
        href: '/admin/institutions',
        icon: UniversityIcon,
        title: 'Institutions',
        description: 'Schools and universities using the platform.',
    },
    {
        href: '/admin/users',
        icon: UserMultipleIcon,
        title: 'Users',
        description: 'Accounts, and what they can reach.',
    },
    {
        href: '/admin/roles',
        icon: Shield01Icon,
        title: 'Roles',
        description: 'Role definitions and the permissions behind them.',
    },
    {
        href: '/admin/permissions',
        icon: Key01Icon,
        title: 'Permissions',
        description: 'Code-owned actions available when composing roles.',
    },
    {
        href: '/admin/tools',
        icon: ToolboxIcon,
        title: 'Tools',
        description: 'Tools an assessment can hand to students.',
    },
] as const;
export default function AdminOverviewPage() {
    return (<div className="flex max-w-4xl flex-col gap-6 pt-1">
            <section className="flex flex-col gap-1.5">
                <h1 className="font-heading text-xl font-semibold tracking-tight">
                    Administration
                </h1>
                <p className="text-muted-foreground text-sm">
                    Platform-wide settings. Changes here affect every institution, so they are kept
                    apart from the rest of the app.
                </p>
            </section>

            <section className="divide-border bg-card divide-y overflow-hidden rounded-lg border">
                {SECTIONS.map((section) => (<Link key={section.href} href={section.href} className="hover:bg-muted/50 group flex items-center gap-4 px-4 py-3.5 transition-colors">
                        <div className="bg-muted text-muted-foreground group-hover:text-foreground flex size-8 shrink-0 items-center justify-center rounded-md transition-colors">
                            <HugeiconsIcon icon={section.icon} strokeWidth={2} className="size-4"/>
                        </div>

                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">{section.title}</p>
                            <p className="text-muted-foreground truncate text-sm">
                                {section.description}
                            </p>
                        </div>

                        <HugeiconsIcon icon={ArrowRight01Icon} strokeWidth={2} className="text-muted-foreground group-hover:text-foreground size-4 shrink-0 transition-colors"/>
                    </Link>))}
            </section>
        </div>);
}
