export type CalendarEventDto = {
    id: string;
    title: string;
    note: string | null;
    startAt: string;
    endAt: string;
    offeringId: string | null;
};

export type AssessmentPlanDto = {
    assessmentId: string;
    startDate: string;
    endDate: string;
};
