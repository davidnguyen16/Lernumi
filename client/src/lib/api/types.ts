export type ApiUser = {
    id: string;
    name: string;
    firstName: string | null;
    lastName: string | null;
    email: string;
    emailVerified: boolean;
    isAdmin: boolean;
    isSystemAdmin?: boolean;
    isActive: boolean;
    avatarUrl: string | null;
    createdAt: string;
};

export type ApiContext = {
    id: string;
    assignmentId: string;
    roleAssignmentId: string | null;
    institution: { id: string; name: string; logoUrl?: string | null };
    role: { id: string; roleCode: string; roleName: string; scopeType: 'INSTITUTION' | 'OFFERING' };
    permissions: string[];
    offeringIds: string[];
    offeringId?: string | null;
};

export type SessionData = { user: ApiUser; contexts: ApiContext[]; isAdmin: boolean };
export type Resource<T> = { data: T };
export type Paginated<T> = {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
};
export type SearchParams = Record<string, string | string[] | undefined>;
