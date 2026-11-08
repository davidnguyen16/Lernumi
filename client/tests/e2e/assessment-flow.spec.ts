import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { createHash, randomUUID } from 'node:crypto';
import { resolve } from 'node:path';

type Fixture = { runId: string; assessmentId: string; assessmentName: string; institutionId: string; offeringId: string };
const repositoryRoot = resolve(__dirname, '../../..');

function fixtureCommand<T>(action: 'create' | 'cleanup', runId: string): T {
    // Jenkins selects its isolated test stack through COMPOSE_FILE.
    const composeFileArguments = process.env.COMPOSE_FILE ? [] : ['-f', resolve(repositoryRoot, 'server/compose.yaml')];
    const output = execFileSync('docker', ['compose', ...composeFileArguments, 'exec', '-T', 'api', 'php', 'scripts/e2e-fixture.php', action, runId], {
        cwd: repositoryRoot, encoding: 'utf8', timeout: 30_000, stdio: ['ignore', 'pipe', 'pipe'],
    });
    return JSON.parse(output.trim()) as T;
}

async function signIn(page: Page, account: 'teacher' | 'student') {
    await page.goto('/auth');
    await page.getByLabel('Email', { exact: true }).fill(`${account}@lernumi.test`);
    await page.getByLabel('Password', { exact: true }).fill(process.env.E2E_PASSWORD ?? 'LernumiDemo123!');
    const login = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/api/v1/auth/login'));
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    expect((await login).status()).toBe(200);
    await expect(page).toHaveURL(/\/navigation\/dashboard/, { timeout: 60_000 });
}

test('a learner submits a file and receives an official result from the teacher', async ({ page: learner, browser, baseURL }) => {
    test.setTimeout(360_000);
    if (!baseURL || !['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname)) throw new Error('Assessment fixtures require a local frontend.');
    const runId = randomUUID();
    const fixture = fixtureCommand<Fixture>('create', runId);
    let teacherContext: BrowserContext | undefined;
    try {
        teacherContext = await browser.newContext({ baseURL });
        const teacher = await teacherContext.newPage();
        await signIn(learner, 'student');
        const scope = new URLSearchParams({ institution: fixture.institutionId, offering: fixture.offeringId });
        await learner.goto(`/navigation/assessments/${fixture.assessmentId}?${scope}`);
        await expect(learner.getByRole('heading', { name: fixture.assessmentName, exact: true })).toBeVisible();
        const creation = learner.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/api/v1/me/attempts'));
        await learner.getByRole('button', { name: 'Start a new attempt', exact: true }).click();
        const created = await creation;
        expect(created.status()).toBe(201);
        const { attempt } = await created.json() as { attempt: { id: string; workspaceId: string } };
        await expect(learner).toHaveURL(new RegExp(`/attempts/${attempt.id}`), { timeout: 60_000 });
        const attemptPath = `/navigation/assessments/${fixture.assessmentId}/attempts/${attempt.id}?${scope}`;
        // Exercise the proxy above its default 10 MiB body-buffer limit.
        const largeBytes = Buffer.alloc(11 * 1024 * 1024, 0x61);
        const largeUpload = learner.waitForResponse(response => response.request().method() === 'POST' && response.url().includes(`/workspaces/${attempt.workspaceId}/documents`));
        await learner.locator('input[type="file"]').first().setInputFiles({ name: 'transport.bin', mimeType: 'application/octet-stream', buffer: largeBytes });
        const uploaded = await largeUpload;
        expect(uploaded.status()).toBe(201);
        const largeDocument = (await uploaded.json()).data;
        expect(largeDocument.sizeBytes).toBe(largeBytes.length);
        expect(largeDocument.contentHash).toBe(createHash('sha256').update(largeBytes).digest('hex'));
        await expect(learner.getByRole('row').filter({ hasText: 'transport.bin' })).toBeVisible();
        const fileName = 'submission.txt';
        const upload = learner.waitForResponse(response => response.request().method() === 'POST' && response.url().includes(`/workspaces/${attempt.workspaceId}/documents`));
        await learner.locator('input[type="file"]').first().setInputFiles({ name: fileName, mimeType: 'text/plain', buffer: Buffer.from(`Browser submission ${runId}\n`) });
        expect((await upload).status()).toBe(201);
        await expect(learner.getByRole('row').filter({ hasText: fileName })).toBeVisible();
        await learner.getByRole('button', { name: 'Submit attempt', exact: true }).click();
        const submission = learner.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith(`/attempts/${attempt.id}/submit`));
        await learner.getByRole('button', { name: 'Confirm submission', exact: true }).click();
        expect((await submission).status()).toBe(200);
        await expect(learner.getByRole('button', { name: 'Submit attempt', exact: true })).toHaveCount(0);
        await expect(learner.getByRole('button', { name: 'Upload files', exact: true })).toHaveCount(0);

        await signIn(teacher, 'teacher');
        const reviewPath = `/navigation/marking/attempts/${attempt.id}?${scope}`;
        await teacher.goto(reviewPath);
        await teacher.getByRole('link', { name: 'Open submitted workspace', exact: true }).click();
        await teacher.getByRole('row').filter({ hasText: fileName }).click();
        await teacher.getByRole('button', { name: 'Open text', exact: true }).click();
        await expect(teacher.getByLabel('Document content')).toHaveValue(`Browser submission ${runId}\n`);
        await expect(teacher.getByLabel('Document content')).toHaveAttribute('readonly', '');
        await teacher.getByRole('dialog').locator('[data-slot="dialog-footer"]').getByRole('button', { name: 'Close', exact: true }).click();
        await teacher.goto(reviewPath);
        const draftCreation = teacher.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith(`/attempts/${attempt.id}/marks`));
        await teacher.getByRole('button', { name: 'Start a marking round', exact: true }).click();
        expect((await draftCreation).status()).toBe(201);
        await teacher.getByLabel('Score', { exact: true }).fill('87');
        await teacher.getByLabel('Out of', { exact: true }).fill('100');
        const feedback = `Reviewed submission ${runId}`;
        await teacher.getByRole('textbox', { name: 'Feedback', exact: true }).fill(feedback);
        const draftSave = teacher.waitForResponse(response => response.request().method() === 'PATCH' && /\/marks\/[^/]+$/.test(new URL(response.url()).pathname));
        await teacher.getByRole('button', { name: 'Save draft', exact: true }).click();
        expect((await draftSave).status()).toBe(200);
        await teacher.reload();
        await expect(teacher.getByLabel('Score', { exact: true })).toHaveValue('87.00');
        await expect(teacher.getByRole('textbox', { name: 'Feedback', exact: true })).toHaveValue(feedback);
        const markSubmission = teacher.waitForResponse(response => response.request().method() === 'POST' && /\/marks\/[^/]+\/submit$/.test(new URL(response.url()).pathname));
        await teacher.getByRole('button', { name: 'Submit mark', exact: true }).click();
        expect((await markSubmission).status()).toBe(200);
        const result = teacher.waitForResponse(response => response.request().method() === 'PUT' && response.url().endsWith(`/attempts/${attempt.id}/result`));
        await teacher.getByLabel('Official result note (optional)').fill('Result published through the browser');
        await teacher.getByRole('button', { name: /^Publish 87(?:\.00)?\/100(?:\.00)? by / }).click();
        expect((await result).status()).toBe(200);
        await expect(teacher.getByText(/^Official result: 87(?:\.00)? \/ 100(?:\.00)?$/)).toBeVisible();

        await learner.goto(attemptPath);
        await expect(learner.getByRole('heading', { name: /^Official result: 87(?:\.00)? \/ 100(?:\.00)?$/ })).toBeVisible();
        await expect(learner.getByText(feedback, { exact: true })).toBeVisible();
        await expect(learner.getByText('Result published through the browser', { exact: true })).toBeVisible();
        await expect(learner.getByRole('row').filter({ hasText: fileName })).toBeVisible();
        await expect(learner.getByRole('button', { name: 'Upload files', exact: true })).toHaveCount(0);
    } finally {
        try { await teacherContext?.close(); }
        finally {
            const cleanup = fixtureCommand<{ cleaned: boolean }>('cleanup', runId);
            expect(cleanup.cleaned).toBe(true);
        }
    }
});
