import { notFound } from 'next/navigation';
import Link from 'next/link';
import { InstitutionOverview } from '@/components/admin/institutions/institution-overview';
import { PeriodsTable } from '@/components/admin/terms/periods-table';
import { AssessmentsTable } from '@/components/admin/offerings/assessments-table';
import { OfferingsSection } from '@/components/admin/offerings/offerings-section';
import { PictureField } from '@/components/media/picture-field';
import { NavigationEmptyState, NavigationPage } from '@/components/navigation/page-shell';
import { Button } from '@/components/ui/button';
import { ApiError, requireSession, serverApi } from '@/lib/api/server';
import { academicAll, academicCan, academicCapabilities, academicContext, assessmentRows, periodRows,
    type AcademicAssessment, type AcademicInstitution, type AcademicOffering, type AcademicSubject,
    type AcademicTerm, type AcademicTool, type SearchParams } from '@/lib/admin/academic-data';

export async function academicResource<T>(path: string): Promise<T> {
    try { return (await serverApi<{ data: T }>(path)).data; }
    catch (error) { if (error instanceof ApiError && error.status === 404) notFound(); throw error; }
}

export async function InstitutionSetup({ institutionId }: { institutionId: string }) {
    const institution = await academicResource<AcademicInstitution>(`/api/v1/admin/institutions/${institutionId}`);
    const sections = [
        ['subjects', 'Subjects', 'Units taught by this institution.'],
        ['terms', 'Terms', 'Study sessions and calendar periods.'],
        ['roles', 'Roles', 'Institution roles and access.'],
        ['offerings', 'Offerings', 'Subjects offered in each term.'],
        ['people', 'People', 'People and their institution roles.'],
        ['requests', 'Requests', 'Membership and subject access requests.'],
    ];
    return <InstitutionOverview institution={institution} editEndpoint={`/api/v1/admin/institutions/${institutionId}`}
        heading={<div><h2 className="font-heading text-lg font-semibold">{institution.name}</h2>
            <p className="text-muted-foreground mt-1 text-sm">{institution.website ?? 'Institution details and academic setup.'}</p></div>}
        logo={<PictureField pictureKey={institution.logoUrl ?? null} fallback={institution.name.slice(0, 2).toUpperCase()} alt={`${institution.name} logo`}
            rounded="lg" endpoint={`/api/v1/institutions/${institutionId}/logo`} fileField="logo"/>}>
        <section className="divide-y overflow-hidden rounded-lg border">
            {sections.map(([path, label, description]) => <Link key={path} href={`/admin/institutions/${institutionId}/${path}`} className="hover:bg-muted/50 block px-4 py-4">
                <p className="text-sm font-medium">{label}</p><p className="text-muted-foreground text-sm">{description}</p></Link>)}
        </section>
    </InstitutionOverview>;
}

export async function TermPeriods({ institutionId, termId, searchParams = {}, navigation = false }: {
    institutionId: string; termId: string; searchParams?: SearchParams; navigation?: boolean;
}) {
    const session = await requireSession();
    const context = navigation ? await academicContext(searchParams) : null;
    const query = context?.query ?? '';
    const roleAssignmentId = context?.assignment?.roleAssignmentId;
    if (!academicCan(session, 'term.view', institutionId, undefined, roleAssignmentId)) {
        return <NavigationEmptyState title="Term access required" description="Choose an institution role with permission to view study terms."/>;
    }
    const term = await academicResource<AcademicTerm>(`/api/v1/institutions/${institutionId}/terms/${termId}`);
    const update = academicCan(session, 'term.update', institutionId, undefined, roleAssignmentId);
    const table = <PeriodsTable periods={periodRows(term.periods ?? [])} termId={termId}
        endpoint={`/api/v1/institutions/${institutionId}/terms/${termId}/periods`} contextQuery={query}
        capabilities={{ create: update, view: true, update, delete: update }}/>;
    const backHref = navigation ? `/navigation/institution/terms?${query}` : `/admin/institutions/${institutionId}/terms`;
    const title = `${term.year} · Session ${term.session}`;
    if (navigation) return <NavigationPage title={title} description="Calendar windows for teaching, breaks and exams." backHref={backHref}>{table}</NavigationPage>;
    return <div className="flex flex-col gap-5 pt-2"><Button nativeButton={false} variant="ghost" className="w-fit -ml-3" render={<Link href={backHref}/>}>Terms</Button>
        <div><h2 className="font-heading text-lg font-semibold tracking-tight">{title}</h2><p className="text-muted-foreground mt-1 text-sm">Calendar windows for teaching, breaks and exams.</p></div>{table}</div>;
}

export async function OfferingAssessments({ institutionId, offeringId, searchParams = {}, navigation = false }: {
    institutionId: string; offeringId: string; searchParams?: SearchParams; navigation?: boolean;
}) {
    const session = await requireSession();
    const context = navigation ? await academicContext(searchParams) : null;
    const query = context?.query ?? '';
    const roleAssignmentId = context?.assignment?.roleAssignmentId;
    if (!academicCan(session, 'offering.view', institutionId, offeringId, roleAssignmentId)) {
        return <NavigationEmptyState title="Offering access required" description="Choose a role with permission to view this offering."/>;
    }
    const offering = await academicResource<AcademicOffering>(`/api/v1/institutions/${institutionId}/offerings/${offeringId}`);
    const capabilities = academicCapabilities(session, 'assessment', institutionId, offeringId, roleAssignmentId);
    const path = `/api/v1/institutions/${institutionId}/offerings/${offeringId}/assessments`;
    const assessments = capabilities.view ? await academicAll<AcademicAssessment>(path) : [];
    const tools = capabilities.create || capabilities.update ? await academicAll<AcademicTool>('/api/v1/tools') : [];
    const table = capabilities.view || capabilities.create ? <AssessmentsTable assessments={assessmentRows(assessments)} offeringId={offeringId}
        tools={tools} totalWeight={Math.round(assessments.reduce((sum, assessment) => sum + assessment.weight, 0) * 100) / 100} endpoint={path} contextQuery={query}
        capabilities={capabilities} canReviewAttempts={academicCan(session, 'attempt.view_all', institutionId, offeringId, roleAssignmentId)}/>
        : <NavigationEmptyState title="Assessment management access required" description="Open this subject from My subjects to view published assessments."/>;
    const title = `${offering.subject.subjectCode} · ${offering.term.year} · Session ${offering.term.session}`;
    const backHref = navigation ? `/navigation/institution/offerings?${query}` : `/admin/institutions/${institutionId}/offerings`;
    if (navigation) return <NavigationPage title={title} description={offering.subject.subjectName} backHref={backHref}>{table}</NavigationPage>;
    return <div className="flex flex-col gap-5 pt-2"><Button nativeButton={false} variant="ghost" className="w-fit -ml-3" render={<Link href={backHref}/>}>Offerings</Button>
        <div><h2 className="font-heading text-lg font-semibold tracking-tight">{title}</h2><p className="text-muted-foreground mt-1 text-sm">Grading, dates, hurdle rules and allowed tools.</p></div>{table}</div>;
}

export async function SubjectOverview({ subjectId, searchParams }: { subjectId: string; searchParams: SearchParams }) {
    const context = await academicContext(searchParams);
    if (!context.institution) return <NavigationPage title="Subject overview"><NavigationEmptyState title="Choose an institution" description="An active institution role is needed to manage subjects."/></NavigationPage>;
    const institutionId = context.institution.id;
    if (!academicCan(context.session, 'subject.view', institutionId, undefined, context.assignment?.roleAssignmentId)) {
        return <NavigationPage title="Subject overview"><NavigationEmptyState title="Subject access required" description="This role cannot view institution subjects."/></NavigationPage>;
    }
    const subject = await academicResource<AcademicSubject>(`/api/v1/institutions/${institutionId}/subjects/${subjectId}`);
    const params = { ...searchParams, subject: subject.id };
    return <NavigationPage title={`${subject.subjectCode} · ${subject.subjectName}`} description={subject.subjectDescription ?? 'Scheduled offerings for this subject.'}
        backHref={`/navigation/institution/subjects?${context.query}`}>
        <OfferingsSection institutionId={institutionId} searchParams={params} contextQuery={context.query} assessmentBasePath="/navigation/institution/offerings"
            capabilities={academicCapabilities(context.session, 'offering', institutionId, undefined, context.assignment?.roleAssignmentId)}/>
    </NavigationPage>;
}
