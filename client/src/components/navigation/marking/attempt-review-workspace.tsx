'use client';
import { LaunchReplayButton } from '@/components/navigation/learning/replay-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { Alert02Icon,CheckmarkCircle02Icon,Clock01Icon,NoteEditIcon,} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api/client';
type MarkStatus = 'DRAFT' | 'SUBMITTED' | 'SUPERSEDED';
export type ReviewMark = {
    id: string;
    markerName: string;
    markerEmail: string;
    score: string | null;
    outOf: string;
    feedback: string | null;
    status: MarkStatus;
    createdAt: string;
    submittedAt: string | null;
    official: boolean;
    own: boolean;
};
export type ReviewFlag = {
    key: string;
    severity: 'NOTICE' | 'REVIEW';
    label: string;
    detail: string;
    at: string;
};
type DraftMark = ReviewMark & {
    status: 'DRAFT';
    own: true;
};
export function AttemptReviewWorkspace({ institutionId, attemptId, roleAssignmentId, marks, flags, canViewMarks, canReplay, canMark, canFinalise = false, replay, }: {
    institutionId: string;
    attemptId: string;
    roleAssignmentId?: string | null;
    canFinalise?: boolean;
    marks: ReviewMark[];
    flags: ReviewFlag[];
    canViewMarks: boolean;
    canReplay: boolean;
    canMark: boolean;
    replay: {

        toolName: string;
        workSessionId: string;

    } | null;
}) {
    const [draft, setDraft] = useState<DraftMark | null>((marks.find((mark): mark is DraftMark => mark.status === 'DRAFT' && mark.own) ?? null));
    const [score, setScore] = useState(draft?.score ?? '');
    const [outOf, setOutOf] = useState(draft?.outOf ?? '100');
    const [feedback, setFeedback] = useState(draft?.feedback ?? '');
    const [working, setWorking] = useState<'start' | 'save' | 'submit' | 'finalise' | null>(null);
    const [resultNote, setResultNote] = useState('');
    const router = useRouter();
    const endpoint = `/api/v1/institutions/${institutionId}/attempts/${attemptId}`;
    const body = (values: Record<string, unknown> = {}) => ({ ...values, ...(roleAssignmentId ? { roleAssignmentId } : {}) });
    const errorMessage = (error: unknown) => toast.error(error instanceof Error ? error.message : 'The mark could not be saved.');
    async function startDraft(): Promise<void> {
        setWorking('start');
        try { const { mark } = await api<{ mark: ReviewMark }>(`${endpoint}/marks`, { method: 'POST', body: body() }); setDraft({ ...mark, status: 'DRAFT', own: true }); setScore(mark.score ?? ''); setOutOf(mark.outOf); setFeedback(mark.feedback ?? ''); router.refresh(); }
        catch (error) { errorMessage(error); }
        finally { setWorking(null); }
    }
    async function save(): Promise<void> {
        if (!draft) return;
        setWorking('save');
        try { const { mark } = await api<{ mark: ReviewMark }>(`${endpoint}/marks/${draft.id}`, { method: 'PATCH', body: body({ score: score.trim() || null, outOf, feedback: feedback || null }) }); setDraft({ ...mark, status: 'DRAFT', own: true }); toast.success('Draft saved.'); router.refresh(); }
        catch (error) { errorMessage(error); }
        finally { setWorking(null); }
    }
    async function submit(): Promise<void> {
        if (!draft) return;
        setWorking('submit');
        try { await api(`${endpoint}/marks/${draft.id}`, { method: 'PATCH', body: body({ score: score.trim() || null, outOf, feedback: feedback || null }) }); await api(`${endpoint}/marks/${draft.id}/submit`, { method: 'POST', body: body() }); setDraft(null); toast.success('Mark submitted.'); router.refresh(); }
        catch (error) { errorMessage(error); }
        finally { setWorking(null); }
    }
    async function finalise(markId: string | null): Promise<void> {
        setWorking('finalise');
        try { await api(`${endpoint}/result${roleAssignmentId && !markId ? `?roleAssignmentId=${roleAssignmentId}` : ''}`, { method: markId ? 'PUT' : 'DELETE', ...(markId ? { body: body({ markId, note: resultNote || null }) } : {}) }); toast.success(markId ? 'Official result published.' : 'Official result cleared.'); router.refresh(); }
        catch (error) { errorMessage(error); }
        finally { setWorking(null); }
    }
    return (<div className="grid min-h-[38rem] overflow-hidden border bg-card xl:grid-cols-[minmax(0,0.9fr)_minmax(34rem,1.1fr)]">
            <section className="min-w-0 border-b xl:border-r xl:border-b-0">
                <div className="border-b px-5 py-4">
                    <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Evidence</p>
                    <h2 className="font-heading mt-1 text-lg font-semibold">Replay and review flags</h2>
                    <p className="text-muted-foreground mt-1 text-sm">Flags point to moments worth reviewing; they are not conclusions.</p>
                </div>

                <div className="border-b px-5 py-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium">Recorded work</p>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                {replay ? `${replay.toolName} recording available` : 'No replayable session recorded'}
                            </p>
                        </div>
                        {canReplay && replay ? (<LaunchReplayButton workSessionId={replay.workSessionId} roleAssignmentId={roleAssignmentId} />) : (<Badge variant="outline">{canReplay ? 'No recording' : 'Replay permission required'}</Badge>)}
                    </div>
                </div>

                <div className="px-5 py-5">
                    <div className="flex items-baseline justify-between gap-3">
                        <h3 className="text-sm font-semibold">Quick flags</h3>
                        <span className="text-muted-foreground text-xs tabular-nums">{canReplay ? flags.length : '—'}</span>
                    </div>
                    {!canReplay ? (<p className="text-muted-foreground mt-3 border border-dashed px-4 py-8 text-center text-sm">Replay permission is required to inspect recorded flags.</p>) : flags.length === 0 ? (<div className="mt-3 flex items-start gap-3 border px-4 py-4">
                            <HugeiconsIcon icon={CheckmarkCircle02Icon} strokeWidth={2} className="text-brand-teal mt-0.5 size-5"/>
                            <div><p className="text-sm font-medium">Nothing flagged</p><p className="text-muted-foreground mt-0.5 text-xs">No quick-review signal was derived from the recorded events.</p></div>
                        </div>) : (<div className="mt-3 max-h-80 divide-y overflow-y-auto border">
                            {flags.map((flag) => (<article key={flag.key} className="flex gap-3 px-4 py-3">
                                    <HugeiconsIcon icon={Alert02Icon} strokeWidth={2} className={flag.severity === 'REVIEW' ? 'mt-0.5 size-4 text-amber-600' : 'text-muted-foreground mt-0.5 size-4'}/>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-medium">{flag.label}</p>
                                            <time className="text-muted-foreground text-[11px] tabular-nums">{flag.at}</time>
                                        </div>
                                        <p className="text-muted-foreground mt-0.5 text-xs leading-5">{flag.detail}</p>
                                    </div>
                                </article>))}
                        </div>)}
                </div>
            </section>

            <section className="min-w-0">
                <div className="flex flex-wrap items-end justify-between gap-4 border-b px-5 py-4">
                    <div>
                        <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Marking history</p>
                        <h2 className="font-heading mt-1 text-lg font-semibold">Independent marking rounds</h2>
                        <p className="text-muted-foreground mt-1 text-sm">Submitted marks stay immutable; corrections create another round.</p>
                    </div>
                    {canMark && !draft && (<Button onClick={() => void startDraft()} disabled={working !== null}>
                            {working === 'start' ? <Spinner /> : <HugeiconsIcon icon={NoteEditIcon} strokeWidth={2}/>}
                            Start a marking round
                        </Button>)}
                </div>

                {canMark && draft && (<div className="border-b bg-brand-teal/5 px-5 py-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold">Your draft</p>
                                <p className="text-muted-foreground mt-0.5 text-xs">Saved drafts remain here when you leave and return later.</p>
                            </div>
                            <Badge variant="outline">Draft</Badge>
                        </div>
                        <div className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <label className="grid gap-1.5 text-sm font-medium">
                                Score
                                <Input inputMode="decimal" value={score} onChange={(event) => setScore(event.target.value)} placeholder="—"/>
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Out of
                                <Input inputMode="decimal" value={outOf} onChange={(event) => setOutOf(event.target.value)}/>
                            </label>
                        </div>
                        <label className="mt-4 grid gap-1.5 text-sm font-medium">
                            Feedback
                            <Textarea className="min-h-36 resize-y rounded-md" value={feedback} onChange={(event) => setFeedback(event.target.value)} placeholder="Record feedback, evidence and follow-up notes…"/>
                        </label>
                        <div className="mt-4 flex flex-wrap justify-end gap-2">
                            <Button variant="outline" onClick={() => void save()} disabled={working !== null}>
                                {working === 'save' && <Spinner />} Save draft
                            </Button>
                            <Button onClick={() => void submit()} disabled={working !== null || !score.trim()}>
                                {working === 'submit' ? <Spinner /> : <HugeiconsIcon icon={CheckmarkCircle02Icon} strokeWidth={2}/>}
                                Submit mark
                            </Button>
                        </div>
                    </div>)}

                {canFinalise && <div className="border-b px-5 py-4"><label className="grid gap-2 text-sm">Official result note (optional)<Input value={resultNote} onChange={event => setResultNote(event.target.value)} maxLength={2000} /></label><div className="mt-3 flex flex-wrap gap-2">{marks.filter(mark => mark.status === 'SUBMITTED' && !mark.official).map(mark => <Button key={mark.id} size="sm" variant="outline" disabled={working !== null} onClick={() => void finalise(mark.id)}>Publish {mark.score}/{mark.outOf} by {mark.markerName}</Button>)}{marks.some(mark => mark.official) && <Button size="sm" variant="outline" disabled={working !== null} onClick={() => void finalise(null)}>Clear official result</Button>}</div></div>}
                {!canViewMarks && !canMark ? (<div className="px-6 py-14 text-center">
                        <p className="font-medium">Marking history is not available to this role.</p>
                        <p className="text-muted-foreground mt-1 text-sm">You can still use the replay controls allowed by your permissions.</p>
                    </div>) : marks.length === 0 ? (<div className="px-6 py-14 text-center">
                        <p className="font-medium">No marking rounds yet.</p>
                        <p className="text-muted-foreground mt-1 text-sm">A marker can start a draft and return to finish it later.</p>
                    </div>) : (<div className="max-h-[34rem] divide-y overflow-y-auto">
                        {marks.map((mark) => (<article key={mark.id} className="px-5 py-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">{mark.markerName}</p>
                                            <Badge variant={mark.status === 'SUBMITTED' ? 'secondary' : 'outline'}>{mark.status === 'SUPERSEDED' ? 'Superseded' : mark.status === 'SUBMITTED' ? 'Submitted' : 'Draft'}</Badge>
                                            {mark.official && <Badge>Official result</Badge>}
                                            {mark.own && <span className="text-brand-teal text-xs font-medium">You</span>}
                                        </div>
                                        <p className="text-muted-foreground mt-0.5 text-xs">{mark.markerEmail}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-lg font-semibold tabular-nums">{mark.score ?? '—'} <span className="text-muted-foreground text-sm font-normal">/ {mark.outOf}</span></p>
                                        <p className="text-muted-foreground mt-0.5 inline-flex items-center gap-1 text-[11px] tabular-nums"><HugeiconsIcon icon={Clock01Icon} strokeWidth={2} className="size-3"/>{mark.submittedAt ?? mark.createdAt}</p>
                                    </div>
                                </div>
                                {mark.feedback && <p className="mt-3 whitespace-pre-wrap border-l-2 pl-3 text-sm leading-6">{mark.feedback}</p>}
                            </article>))}
                    </div>)}
            </section>
        </div>);
}
