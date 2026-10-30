'use client';
import { pictureUrl } from '@/components/media/picture-url';
import { Avatar,AvatarFallback,AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu,DropdownMenuContent,DropdownMenuGroup,DropdownMenuItem,DropdownMenuLabel,DropdownMenuSeparator,DropdownMenuTrigger,} from '@/components/ui/dropdown-menu';
import { CONTEXT_PARAM,contextKey,contextLabel,distinctRoleContexts,type ContextOption,} from '@/lib/context/context-params';
import { ArrowDown01Icon,Shield01Icon,Tick02Icon,UniversityIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { usePathname,useSearchParams } from 'next/navigation';
export function ContextSwitcher({ options }: {
    options: ContextOption[];
}) {
    const pathname = usePathname();
    const searchParams = useSearchParams();
    if (options.length === 0) {
        return (<span className="text-muted-foreground flex items-center gap-2 text-sm">
                <HugeiconsIcon icon={Shield01Icon} strokeWidth={2} className="size-4"/>
                No role yet
            </span>);
    }
    const wantedInstitution = searchParams.get(CONTEXT_PARAM.institution);
    const wantedRole = searchParams.get(CONTEXT_PARAM.role);
    const wantedOffering = searchParams.get(CONTEXT_PARAM.offering);
    const active = options.find((option) => option.institution.id === wantedInstitution &&
        option.role.id === wantedRole &&
        (!wantedOffering || option.offering?.id === wantedOffering)) ?? options[0];
    const roleOptions = distinctRoleContexts(options);
    function select(option: ContextOption) {
        const next = new URLSearchParams(searchParams);
        next.set(CONTEXT_PARAM.institution, option.institution.id);
        next.set(CONTEXT_PARAM.role, option.role.id);
        next.delete(CONTEXT_PARAM.offering);
        const destination = new URL(pathname, window.location.origin);
        destination.search = next.toString();
        window.location.assign(destination);
    }
    const grouped = new Map<string, {
        name: string;
        items: ContextOption[];
    }>();
    for (const option of roleOptions) {
        const bucket = grouped.get(option.institution.id);
        if (bucket) {
            bucket.items.push(option);
        }
        else {
            grouped.set(option.institution.id, {
                name: option.institution.name,
                items: [option],
            });
        }
    }
    return (<DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="h-11 max-w-64 justify-start gap-2 px-2" aria-label={`Acting as ${contextLabel(active)} at ${active.institution.name}`}>
                        <Avatar className="size-7 rounded-md after:rounded-md">
                            <AvatarImage src={pictureUrl(active.institution.logoKey)} alt="" className="rounded-md"/>
                            <AvatarFallback className="bg-brand-teal/12 text-brand-teal rounded-md">
                                <HugeiconsIcon icon={UniversityIcon} strokeWidth={2} className="size-4"/>
                            </AvatarFallback>
                        </Avatar>
                        <span className="grid flex-1 text-left leading-tight">
                            <span className="truncate text-sm font-medium">
                                {contextLabel(active)}
                            </span>
                            <span className="text-muted-foreground truncate text-xs font-normal">
                                {active.institution.name}
                            </span>
                        </span>
                        <HugeiconsIcon icon={ArrowDown01Icon} strokeWidth={2} className="text-muted-foreground size-4 shrink-0"/>
                    </Button>}/>

            <DropdownMenuContent align="end" className="w-72">
                {Array.from(grouped.values()).map((group, index) => (<DropdownMenuGroup key={group.name}>
                        {index > 0 && <DropdownMenuSeparator />}
                        <DropdownMenuLabel>{group.name}</DropdownMenuLabel>

                        {group.items.map((option) => {
                const key = contextKey(option);
                const isActive = option.assignmentId === active.assignmentId;
                return (<DropdownMenuItem key={key} onClick={() => select(option)}>
                                    <HugeiconsIcon icon={Tick02Icon} strokeWidth={2} className={isActive ? 'text-brand-teal' : 'opacity-0'}/>
                                    <span className="truncate">{option.role.roleName}</span>
                                </DropdownMenuItem>);
            })}
                    </DropdownMenuGroup>))}
            </DropdownMenuContent>
        </DropdownMenu>);
}
