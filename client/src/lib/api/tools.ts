'use client';

import { api } from './client';

export type ToolLaunchInput = { mode: 'PERSONAL'; workspaceId: string; toolId: string; roleAssignmentId?: string | null }
    | { mode: 'WORK'; attemptId: string; toolId: string; roleAssignmentId?: string | null }
    | { mode: 'REPLAY'; workSessionId: string; roleAssignmentId?: string | null };

export async function launchTool(input: ToolLaunchInput): Promise<void> {
    const popup = window.open('about:blank', '_blank');
    if (popup) popup.opener = null;
    try {
        const body = { ...input };
        if (!body.roleAssignmentId) delete body.roleAssignmentId;
        const grant = await api<{ runtimeUrl: string }>('/api/tool/v1/sessions', { method: 'POST', body });
        const url = new URL(grant.runtimeUrl);
        if (!['http:', 'https:'].includes(url.protocol)) throw new Error('The runtime returned an invalid URL.');
        if (popup) popup.location.replace(url.href);
        else window.location.assign(url.href);
    } catch (error) {
        popup?.close();
        throw error;
    }
}
