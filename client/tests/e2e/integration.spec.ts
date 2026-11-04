import { test, expect, type Page } from '@playwright/test';

async function signIn(page: Page, account: 'admin' | 'teacher' | 'student') {
    await page.goto('/auth');
    await page.getByLabel('Email', { exact: true }).fill(`${account}@lernumi.test`);
    await page.getByLabel('Password', { exact: true }).fill(process.env.E2E_PASSWORD ?? 'LernumiDemo123!');
    const login = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/api/v1/auth/login'));
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    expect((await login).status()).toBe(200);
    await expect(page).toHaveURL(/\/navigation\/dashboard/, { timeout: 60_000 });
}

test.beforeEach(async ({ baseURL }) => {
    if (!baseURL || !['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname)) throw new Error('These demo tests must run against a local frontend.');
});

test('student can sign in, restore the session, browse subjects and sign out', async ({ page }) => {
    await signIn(page, 'student');
    await expect(page.getByText('Demo Student', { exact: true }).first()).toBeVisible();
    await page.reload();
    await expect(page.getByText('Demo Student', { exact: true }).first()).toBeVisible();
    await page.goto('/navigation/subjects');
    await expect(page.getByText('COMP1000', { exact: false }).first()).toBeVisible();
    await page.goto('/navigation/calendar');
    await expect(page.getByRole('heading', { name: /calendar/i }).first()).toBeVisible();
    await page.getByRole('button').filter({ hasText: 'Demo Student' }).first().click();
    await page.getByRole('menuitem', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/\/auth/);
    await page.goto('/navigation/workspaces');
    await expect(page).toHaveURL(/\/auth/);
});

test('a student cannot open system administration', async ({ page }) => {
    await signIn(page, 'student');
    await page.goto('/admin/users');
    await expect(page).toHaveURL(/\/navigation\/dashboard/);
});

test('the administrator can load institutions, users and tools from the API', async ({ page }) => {
    await signIn(page, 'admin');
    for (const [path, label] of [['/admin/institutions', 'Lernumi Demo'], ['/admin/users', 'student@lernumi.test'], ['/admin/tools', 'Demo API probe']]) {
        await page.goto(path);
        await expect(page.getByText(label, { exact: false }).first()).toBeVisible();
        await expect(page.getByRole('heading', { name: 'We could not load this page' })).toHaveCount(0);
    }
    await page.getByRole('button', { name: 'Open notifications', exact: true }).click();
    const drawer = page.getByRole('complementary', { name: 'Notifications', exact: true });
    await expect(drawer).toBeVisible();
    await expect(drawer.locator('[aria-busy]')).toHaveAttribute('aria-busy', 'false', { timeout: 60_000 });
    await expect(drawer.getByRole('alert')).toHaveCount(0);
});

async function removeFixture(page: Page, endpoint: string) {
    const status = await page.evaluate(async path => {
        const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
        const response = await fetch(path, { method: 'DELETE', credentials: 'include', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '' } });
        return response.status;
    }, endpoint);
    expect([204, 404]).toContain(status);
}

test('a student can create a workspace, edit and download a file, open a tool and delete the workspace', async ({ page }) => {
    await signIn(page, 'student');
    const name = `E2E workspace ${Date.now()}`;
    let workspaceId: string | undefined;
    try {
        await page.goto('/navigation/workspaces');
        await page.getByRole('button', { name: 'New workspace', exact: true }).click();
        await page.getByLabel('Workspace name').fill(name);
        const creation = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/api/v1/workspaces'));
        await page.getByRole('button', { name: 'Create workspace', exact: true }).click();
        const response = await creation;
        expect(response.status()).toBe(201);
        workspaceId = (await response.json()).data.id;
        await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'New folder', exact: true }).click();
        await page.getByPlaceholder('Folder name', { exact: true }).fill('Notes');
        await page.getByRole('button', { name: 'Create folder', exact: true }).click();
        await expect(page.getByRole('row').filter({ hasText: 'Notes' })).toBeVisible();
        await page.locator('input[type="file"]').first().setInputFiles({ name: 'hello.txt', mimeType: 'text/plain', buffer: Buffer.from('First draft\n') });
        const row = page.getByRole('row').filter({ hasText: 'hello.txt' });
        await expect(row).toBeVisible();
        await row.click();
        await page.getByRole('button', { name: 'Open text', exact: true }).click();
        await expect(page.getByLabel('Document content')).toHaveValue('First draft\n');
        await page.getByLabel('Document content').fill('Saved through Lernumi\n');
        const save = page.waitForResponse(response => response.request().method() === 'PUT' && response.url().endsWith('/content'));
        await page.getByRole('dialog').getByRole('button', { name: 'Save', exact: true }).click();
        expect((await save).ok()).toBe(true);
        await page.getByRole('dialog').locator('[data-slot="dialog-footer"]').getByRole('button', { name: 'Close', exact: true }).click();
        await page.getByRole('button', { name: 'Open actions for hello.txt', exact: true }).click();
        const downloadEvent = page.waitForEvent('download');
        await page.getByRole('menuitem', { name: 'Download', exact: true }).click();
        const download = await downloadEvent;
        expect(download.suggestedFilename()).toBe('hello.txt');
        const stream = await download.createReadStream();
        const chunks: Buffer[] = [];
        for await (const chunk of stream!) chunks.push(Buffer.from(chunk));
        expect(Buffer.concat(chunks).toString('utf8')).toBe('Saved through Lernumi\n');

        await page.getByRole('button', { name: 'Open workspace with a tool', exact: true }).click();
        const popupEvent = page.waitForEvent('popup');
        await page.getByRole('menuitem').filter({ hasText: 'Demo API probe' }).click();
        const popup = await popupEvent;
        await expect(popup.locator('#status')).toHaveText('Connected to the Lernumi runtime API.');
        await expect(popup.locator('#workspace')).toHaveText(name);
        await expect(popup.locator('#mode')).toHaveText('OWNER');
        await expect(popup.locator('#read-only')).toHaveText('false');
        expect(new URL(popup.url()).hostname).toBe('127.0.0.1');
        await popup.close();

        await page.getByRole('button', { name: 'Delete workspace', exact: true }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete workspace', exact: true }).click();
        await expect(page).toHaveURL(/\/navigation\/workspaces$/);
        await expect(page.getByText(name, { exact: true })).toHaveCount(0);
    } finally {
        if (workspaceId) await removeFixture(page, `/api/v1/workspaces/${workspaceId}`);
    }
});

test('the administrator can create, update and delete an institution', async ({ page }) => {
    await signIn(page, 'admin');
    const name = `E2E institution ${Date.now()}`;
    let institutionId: string | undefined;
    try {
        await page.goto('/admin/institutions');
        await page.getByRole('button', { name: 'New institution', exact: true }).click();
        await page.getByLabel('Name', { exact: true }).fill(name);
        const creation = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/api/v1/admin/institutions'));
        await page.getByRole('button', { name: 'Create', exact: true }).click();
        const response = await creation;
        expect(response.status()).toBe(201);
        institutionId = (await response.json()).data.id;
        const row = page.getByRole('row').filter({ hasText: name });
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: 'Open actions', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Edit details', exact: true }).click();
        await page.getByLabel('Website', { exact: true }).fill('https://example.com');
        await page.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect(row.getByRole('link', { name: 'example.com', exact: true })).toHaveAttribute('href', 'https://example.com');
        await row.getByRole('button', { name: 'Open actions', exact: true }).click();
        await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
        await expect(page.getByRole('alertdialog').getByRole('heading', { name: `Delete ${name}?`, exact: true })).toBeVisible();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(row).toHaveCount(0);
    } finally {
        if (institutionId) await removeFixture(page, `/api/v1/admin/institutions/${institutionId}`);
    }
});
