'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api/client';
import { displayDate, type LearningAssessment, type LearningAttempt, type LearningGroup } from '@/lib/learning/types';

export function AssessmentWorkspace({ assessment, attempts, groups, canStart, canManageGroups, userId, roleAssignmentId, contextQuery }: {
  assessment: LearningAssessment; attempts: LearningAttempt[]; groups: LearningGroup[];
  canStart: boolean; canManageGroups: boolean; userId: string; roleAssignmentId?: string | null; contextQuery: string;
}) {
  const router = useRouter();
  const [working, setWorking] = useState(false);
  const [groupName, setGroupName] = useState('');
  const [memberEmails, setMemberEmails] = useState('');
  const [editingGroup, setEditingGroup] = useState<string | null>(null);
  const [groupId, setGroupId] = useState(groups.find(group => group.members.some(member => member.userId === userId))?.id ?? '');
  const context = roleAssignmentId ? { roleAssignmentId } : {};
  const href = (path: string) => contextQuery ? `${path}?${contextQuery}` : path;
  const endpoint = `/api/v1/me/assessments/${assessment.id}/groups`;
  async function startAttempt() {
    setWorking(true);
    try {
      const result = await api<{ attempt: LearningAttempt }>('/api/v1/me/attempts', { method: 'POST', body: { assessmentId: assessment.id, ...(assessment.isGroup ? { groupId } : {}), ...context } });
      router.push(href(`/navigation/assessments/${assessment.id}/attempts/${result.attempt.id}`));
    } catch (error) { toast.error(error instanceof Error ? error.message : 'Could not start the attempt.'); }
    finally { setWorking(false); }
  }
  async function saveGroup() {
    setWorking(true);
    try {
      const emails = memberEmails.split(/[\s,;]+/).map(email => email.trim()).filter(Boolean);
      await api(editingGroup ? `${endpoint}/${editingGroup}` : endpoint, { method: editingGroup ? 'PATCH' : 'POST', body: { name: groupName, memberEmails: emails, ...context } });
      setGroupName(''); setMemberEmails(''); setEditingGroup(null); router.refresh();
      toast.success('Group saved.');
    } catch (error) { toast.error(error instanceof Error ? error.message : 'Could not save the group.'); }
    finally { setWorking(false); }
  }
  async function deleteGroup(group: LearningGroup) {
    if (!window.confirm(`Delete the group "${group.name}"?`)) return;
    setWorking(true);
    try { await api(`${endpoint}/${group.id}`, { method: 'DELETE' }); if (groupId === group.id) setGroupId(''); router.refresh(); }
    catch (error) { toast.error(error instanceof Error ? error.message : 'Could not delete the group.'); }
    finally { setWorking(false); }
  }
  return <div className="space-y-6">
    <section className="bg-card rounded-xl border p-5"><p className="whitespace-pre-wrap text-sm leading-7">{assessment.description || 'No additional instructions.'}</p><div className="text-muted-foreground mt-4 flex flex-wrap gap-4 text-sm"><span>Weight: {assessment.weight}%</span><span>{assessment.isGroup ? 'Group assessment' : 'Individual assessment'}</span><span>Due: {displayDate(assessment.dueAt)}</span>{assessment.hurdle && <span>Hurdle requirement</span>}</div></section>
    {assessment.isGroup && <section className="bg-card space-y-4 rounded-xl border p-5"><h2 className="font-semibold">Your group</h2><p className="text-muted-foreground text-sm">Group members share an attempt and workspace. Membership is locked once an attempt starts.</p>{groups.map(group => <div key={group.id} className="rounded-lg border p-4"><div className="flex flex-wrap items-center gap-2"><label className="flex flex-1 items-center gap-2"><input type="radio" name="assessment-group" value={group.id} checked={groupId === group.id} onChange={() => setGroupId(group.id)} /><strong>{group.name}</strong></label>{group.locked && <span className="text-muted-foreground text-xs">Membership locked</span>}{!group.locked && (group.createdById === userId || canManageGroups) && <><Button size="sm" variant="outline" disabled={working} onClick={() => { setEditingGroup(group.id); setGroupName(group.name); setMemberEmails(group.members.map(member => member.email).join(', ')); }}>Edit</Button><Button size="sm" variant="outline" disabled={working} onClick={() => void deleteGroup(group)}>Delete</Button></>}</div><ul className="text-muted-foreground mt-3 space-y-1 text-sm">{group.members.map(member => <li key={member.userId}>{member.displayName} · {member.email}</li>)}</ul></div>)}{(canStart || canManageGroups) && <div className="grid gap-3 border-t pt-4"><label className="grid gap-1 text-sm">Group name<Input value={groupName} onChange={event => setGroupName(event.target.value)} maxLength={120} /></label><label className="grid gap-1 text-sm">Teammates&apos; email addresses<Input value={memberEmails} onChange={event => setMemberEmails(event.target.value)} placeholder="Separate email addresses with commas" /></label><p className="text-muted-foreground text-xs">{canStart ? 'You are included automatically. Teammates need access to this assessment.' : 'Include the email addresses of every participant. Members need access to this assessment.'}</p><div className="flex gap-2"><Button disabled={working || !groupName.trim() || (!canStart && !memberEmails.trim())} onClick={() => void saveGroup()}>{editingGroup ? 'Save group' : 'Create group'}</Button>{editingGroup && <Button variant="outline" onClick={() => { setEditingGroup(null); setGroupName(''); setMemberEmails(''); }}>Cancel</Button>}</div></div>}</section>}
    <section className="space-y-4"><div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-heading text-lg font-semibold">Your attempts</h2>{canStart && <Button disabled={working || (assessment.isGroup && !groupId)} onClick={() => void startAttempt()}>{working ? 'Starting…' : 'Start a new attempt'}</Button>}</div>{attempts.length === 0 ? <p className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">You have not started an attempt.</p> : <div className="grid gap-3">{attempts.map((attempt, index) => <Link className="bg-card hover:border-primary/50 flex items-center justify-between gap-3 rounded-xl border p-4" href={href(`/navigation/assessments/${assessment.id}/attempts/${attempt.id}`)} key={attempt.id}><div><p className="font-medium">Attempt {attempts.length - index}{attempt.group && ` · ${attempt.group.name}`}</p><p className="text-muted-foreground mt-1 text-sm">Started {displayDate(attempt.startAt)}</p></div><span className="text-sm">{attempt.submitAt ? 'Submitted' : 'Continue'}</span></Link>)}</div>}</section>
  </div>;
}
