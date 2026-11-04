import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 180_000,
    expect: { timeout: 20_000 },
    retries: 0,
    use: { baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:3001', ...devices['Desktop Chrome'], channel: process.env.PLAYWRIGHT_CHANNEL ?? (process.platform === 'win32' ? 'chrome' : undefined), actionTimeout: 30_000, navigationTimeout: 60_000, trace: 'retain-on-failure' },
    webServer: { command: 'pnpm dev', url: 'http://127.0.0.1:3001/auth', reuseExistingServer: !process.env.CI, timeout: 120_000 },
});
