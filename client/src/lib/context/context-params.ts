export const CONTEXT_PARAM = {
    institution: 'institution',
    role: 'role',
    offering: 'offering',
} as const;
export type ContextOption = {
    assignmentId: string;
    institution: {
        id: string;
        name: string;
        logoKey: string | null;
    };
    role: {
        id: string;
        roleName: string;
        scopeType: 'INSTITUTION' | 'OFFERING';
    };
    offering: {
        id: string;
        subjectCode: string;
        subjectName: string;
        year: number;
        session: number;
    } | null;
};
export function contextKey(option: ContextOption): string {
    return `${option.assignmentId}:${option.offering?.id ?? 'no-offering'}`;
}
export function contextSearchParams(option: ContextOption): Record<string, string> {
    const params = roleSearchParams(option);
    if (option.offering) {
        params[CONTEXT_PARAM.offering] = option.offering.id;
    }
    return params;
}
export function roleSearchParams(option: ContextOption): Record<string, string> {
    return {
        [CONTEXT_PARAM.institution]: option.institution.id,
        [CONTEXT_PARAM.role]: option.role.id,
    };
}
export function contextLabel(option: ContextOption): string {
    return option.role.roleName;
}
export function distinctRoleContexts<TContext extends ContextOption>(options: readonly TContext[]): TContext[] {
    const seen = new Set<string>();
    return options.filter((option) => {
        if (seen.has(option.assignmentId))
            return false;
        seen.add(option.assignmentId);
        return true;
    });
}
