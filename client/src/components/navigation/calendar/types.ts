export type CalendarPeriod = {
    id: string;
    name: string;
    type: 'TEACHING' | 'BREAK' | 'EXAM';
    startAt: string;
    endAt: string;
};
export type CalendarTerm = {
    id: string;
    year: number;
    session: number;
    periods: CalendarPeriod[];
};
export type CalendarAssessment = {
    id: string;
    offeringId: string;
    name: string;
    weight: number;
    hurdle: boolean;
    isGroup: boolean;
    dueAt: string | null;
    expectedDue: string | null;
    href: string;
};
export type CalendarOffering = {
    id: string;
    termId: string;
    subject: {
        code: string;
        name: string;
    };
    assessments: CalendarAssessment[];
};
export type CalendarData = {
    timeZone: string;
    terms: CalendarTerm[];
    offerings: CalendarOffering[];
    canSeeAssessments: boolean;
};
