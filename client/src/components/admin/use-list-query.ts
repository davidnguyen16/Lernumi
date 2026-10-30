'use client';
import { usePathname,useRouter,useSearchParams } from 'next/navigation';
import { useCallback,useEffect,useState,useTransition } from 'react';
import { useDebounceValue } from 'usehooks-ts';
export function useListQuery() {
    const router = useRouter();
    const pathname = usePathname();
    const searchParams = useSearchParams();
    const [isPending, startTransition] = useTransition();
    const currentQuery = searchParams.get('q') ?? '';
    const [draft, setDraft] = useState({ urlValue: currentQuery, value: currentQuery });
    const input = draft.urlValue === currentQuery ? draft.value : currentQuery;
    const setInput = useCallback((value: string) => setDraft({ urlValue: currentQuery, value }), [currentQuery]);
    const [debounced] = useDebounceValue(input, 300);
    const setParams = useCallback((next: Record<string, string | number | null>) => {
        const params = new URLSearchParams(searchParams.toString());
        for (const [key, value] of Object.entries(next)) {
            if (value === null || value === '') {
                params.delete(key);
            }
            else {
                params.set(key, String(value));
            }
        }
        startTransition(() => {
            router.replace(`${pathname}?${params.toString()}`, { scroll: false });
        });
    }, [pathname, router, searchParams]);
    useEffect(() => {
        if (draft.urlValue !== currentQuery)
            return;
        if (debounced === currentQuery)
            return;
        setParams({ q: debounced, page: null });
    }, [debounced, currentQuery, draft.urlValue, setParams]);
    return {
        input,
        setInput,
        isPending,
        setParams,
        sort: searchParams.get('sort'),
        dir: searchParams.get('dir') === 'desc' ? ('desc' as const) : ('asc' as const),
        page: Number(searchParams.get('page') ?? 1),
    };
}
