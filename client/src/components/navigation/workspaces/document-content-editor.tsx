'use client';

import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { api, ApiError } from '@/lib/api/client';

export function DocumentContentEditor({ workspaceId, document, readOnly, onClose, onSaved }: {
  workspaceId: string;
  document: { id: string; name: string; mimeType: string | null };
  readOnly: boolean;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [content, setContent] = useState('');
  const [etag, setEtag] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reload, setReload] = useState(0);
  const endpoint = `/api/v1/workspaces/${workspaceId}/documents/${document.id}/content`;
  useEffect(() => {
    const controller = new AbortController();
    setLoading(true); setError(null);
    void (async () => {
      try {
        const response = await fetch(endpoint, { credentials: 'include', cache: 'no-store', signal: controller.signal });
        if (!response.ok) throw new Error('Could not open this document.');
        const bytes = await response.arrayBuffer();
        if (bytes.byteLength > 1024 * 1024) throw new Error('This file is too large for the text editor. Download it to edit locally.');
        const text = new TextDecoder('utf-8', { fatal: true }).decode(bytes);
        if (text.includes('\u0000')) throw new Error('This file contains binary data. Download it to edit locally.');
        if (!controller.signal.aborted) { setContent(text); setEtag(response.headers.get('ETag')); }
      } catch (failure) { if (!controller.signal.aborted) setError(failure instanceof Error ? failure.message : 'Could not read the document.'); }
      finally { if (!controller.signal.aborted) setLoading(false); }
    })();
    return () => controller.abort();
  }, [endpoint, reload]);
  async function save() {
    if (!etag || readOnly || saving) return;
    setSaving(true); setError(null);
    try {
      const result = await api<{ data: { contentHash: string } }>(endpoint, { method: 'PUT', headers: { 'Content-Type': document.mimeType || 'text/plain', 'If-Match': etag }, body: content });
      setEtag(`"${result.data.contentHash}"`); onSaved();
    } catch (failure) { setError(failure instanceof ApiError && failure.status === 412 ? 'This file changed in another session. Copy your draft before reloading the latest version.' : failure instanceof Error ? failure.message : 'Could not save the document.'); }
    finally { setSaving(false); }
  }
  return <Dialog open onOpenChange={open => { if (!open && !saving) onClose(); }}><DialogContent className="sm:max-w-4xl"><DialogHeader><DialogTitle>{document.name}</DialogTitle><DialogDescription>{readOnly ? 'This workspace is read-only.' : 'Changes are saved only when you select Save.'}</DialogDescription></DialogHeader>{error && <p role="alert" className="text-destructive text-sm">{error}</p>}<Textarea aria-label="Document content" className="h-[55vh] resize-none font-mono text-sm" value={content} onChange={event => setContent(event.target.value)} readOnly={readOnly} disabled={loading || saving} spellCheck={false}/><DialogFooter><Button variant="outline" disabled={loading || saving} onClick={() => setReload(value => value + 1)}>Reload latest</Button><Button variant="outline" disabled={saving} onClick={onClose}>Close</Button>{!readOnly && <Button disabled={loading || saving || !etag} onClick={() => void save()}>{saving ? 'Saving…' : 'Save'}</Button>}</DialogFooter></DialogContent></Dialog>;
}
