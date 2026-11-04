# Lernumi frontend

The Next.js frontend uses the Laravel API in `../server/`. Authentication uses Sanctum session cookies and CSRF protection. Browser requests go through Next.js rewrites; authenticated server components forward the session to the configured API.

## Run locally

Start Docker Desktop and the backend from the repository root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

Run this setup before plain `docker compose up` on a fresh clone. Backend Composer dependencies live in Docker's `api_vendor` volume, while PHP source remains bind-mounted. After `server/composer.lock` changes, rerun the setup script or use the [backend's Docker Composer command](../server/README.md); an existing host `server/vendor/` does not control the running API's dependencies.

Then run these commands from `client/` with Node.js and pnpm installed:

```powershell
Copy-Item .env.example .env.local
pnpm install --frozen-lockfile
pnpm dev
```

Copy the environment example only when `.env.local` does not already exist. Its defaults target the local API at `http://localhost:8000` and frontend at `http://localhost:3001`.

Open **http://localhost:3001/auth**. Keep the frontend on `localhost`; the tool runtime uses the separate hostname `127.0.0.1:8000` to isolate cookies. The development server uses port 3001 to avoid another local application using port 3000.

Alternatively, run `scripts/start-local.ps1 -Client` from the root to start both parts. Ctrl+C stops the frontend; `docker compose down` stops the backend while retaining both the database and Composer dependency volumes.

Local demo users share the password `LernumiDemo123!`:

| Email | Access |
| --- | --- |
| `admin@lernumi.test` | Global administration and all institutions |
| `teacher@lernumi.test` | Teaching and marking within the seeded offering |
| `student@lernumi.test` | Learning, attempts, calendar, and personal workspaces |

Local email uses Laravel's log mailer. Verification and password-reset links appear in `server/storage/logs/laravel.log`.

## Connected features

The UI connects accounts and profiles; role/scope requests and approvals; institution, subject, term, offering, and assessment management; student attempts and group membership; marking and official results; personal workspaces and files; calendar events and plans; and administrator tool packages and versions.

The workspace text editor uses ETags to detect conflicting writes. Tool launch and replay use the `runtimeUrl` returned by Laravel. The included demo tool displays runtime context and capabilities.

API helpers live in `src/lib/api/`. Domain adapters live in `src/lib/admin/`, `src/lib/learning/`, and `src/lib/access-data.ts`. The backend remains responsible for authorization and validation. See the [integration guide](../docs/frontend-integration.md) and [API reference](../docs/api-reference.md).

## Checks

```sh
pnpm install --frozen-lockfile
pnpm typecheck
pnpm build
pnpm test:e2e
```

End-to-end tests require the Docker backend and seeded demo users to be running before `pnpm test:e2e`. Playwright starts or reuses the development frontend. On Windows the configuration uses installed Google Chrome. If Chrome is unavailable, use installed Microsoft Edge:

```powershell
$env:PLAYWRIGHT_CHANNEL = 'msedge'
pnpm test:e2e
Remove-Item Env:PLAYWRIGHT_CHANNEL
```

On other platforms, install Playwright's Chromium with `pnpm exec playwright install chromium`. Tests are restricted to local hostnames. Set `E2E_PASSWORD` if you changed the demo password. Avoid committing browser traces or environment files containing credentials.

Do not run PHPUnit suites in parallel against their shared `lernumi_testing` database. For CI browser tests, finish PHPUnit first, then prepare a separate seeded local backend for the UI workflows so database resets cannot interrupt a browser run. Keep each job's ports, database, and storage isolated. The current Playwright setup uses local hostnames for both cookie and runtime isolation; the CI runner must provide the same reachable local topology.

## Build and deployment configuration

Set `LERNUMI_API_URL` **before `pnpm build`**. Next.js records the rewrites from `next.config.ts` in its build output; changing the environment only when starting the server does not replace the compiled proxy destination. Supply the same backend URL when running SSR so server-side reads and browser proxy requests reach the same API. Build for the target environment when staging and production use different backend URLs, or provide an intentionally stable backend address across environments.

Set `FRONTEND_URL` to the public application origin in both Next.js and Laravel, for example `https://app.example.com`, and align Laravel's CORS/Sanctum settings. Browser `/api/*` and `/sanctum/*` requests remain on the application origin; tool runtime requests use the separate tool hostname. `LERNUMI_API_URL` is the backend address reachable by Next.js and may be an internal address; it is not the public frontend origin.

Keep `experimental.proxyClientMaxBodySize: '30mb'` in `next.config.ts` when building the frontend. Next.js otherwise buffers only the first 10 MiB of proxied request bodies, which can truncate uploads before Laravel receives them. The 30 MiB proxy limit matches PHP's `post_max_size=30M`; application limits remain 20 MiB per document and 25 MiB per tool ZIP. Configure any outer production proxy to allow the full multipart body, such as the 32 MiB limit in the deployment guide, and verify uploads through the complete browser-to-API path.

The frontend and backend are connected locally; neither has been deployed to the cloud. Jenkins CI/CD and EC2 deployment are left to the project owner. Include dependency installation, type checking, a target-specific Next build, seeded local browser E2E, artifact/image publication, and a staging check in the pipeline you write. The [deployment guide](../docs/ec2-jenkins-guide.md) describes these steps. No Jenkinsfile or production image configuration is generated by this guide.

The t4g.nano guide targets a small **Laravel + PostgreSQL demo**. Next.js SSR needs a separate host or an instance with more memory; the full application stack is not promised to fit in 0.5 GiB RAM.
