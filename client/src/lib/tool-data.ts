import 'server-only';
import { serverApi } from '@/lib/api/server';
export type ToolVersion = { id: string; toolId: string; version: string; entryPath: string; sha256: string; compressedSize: number; unpackedSize: number; fileCount: number; releaseNotes: string | null; createdById: string; createdAt: string };
export type ToolData = { id: string; code: string; name: string; description: string | null; codeBaseLocation: string | null; activeVersionId: string | null; activeVersion: ToolVersion | null; versions?: ToolVersion[]; counts: { versions: number; assessments: number; sessions: number }; createdAt: string; updatedAt: string };
export async function getTool(id: string) { return (await serverApi<{ data: ToolData }>('/api/v1/admin/tools/' + id)).data; }
