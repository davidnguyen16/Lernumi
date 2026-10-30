import { ContextSwitcher } from '@/components/navigation/context-switcher';
import type { ContextOption } from '@/lib/context/context-params';
import { Suspense } from 'react';
export function HeaderContext({ options }: {
    options: ContextOption[];
}) {
    return (<Suspense fallback={<div className="h-11 w-48"/>}>
            <ContextSwitcher options={options}/>
        </Suspense>);
}
