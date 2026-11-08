# Lernumi Laravel API

The Lernumi REST API uses PHP 8.3, Laravel 13, Sanctum, and PostgreSQL 17. The connected Next.js frontend lives in `../client/`. See the [frontend setup](../client/README.md), [API reference](../docs/api-reference.md), and [integration guide](../docs/frontend-integration.md).

## Features

| Domain | Features |
| --- | --- |
| Accounts | Registration/login, email verification, password recovery/reset, profiles/avatars, session management, and API tokens |
| Access control | Institution/offering roles, role inheritance, permission grants/revocations, membership requests and approvals, join codes, user administration, and audit logs |
| Academic structure | Institution, Subject, StudyTerm/Period, Offering, and Assessment; access scoped by institution and permissions |
| Learning | Assessment groups, starting/submitting attempts, attempt workspaces, marking revisions, and finalizing results |
| Personal tools | Personal workspaces, folder/file trees, uploads/downloads, calendar events, and assessment plans |
| Tool platform | ZIP upload/validation, immutable versions, version activation, WORK/PERSONAL/REPLAY runtimes, JWTs, event hash chains, and replay |

Business APIs use the `/api/v1` prefix. The tool runtime uses `/api/tool/v1`. Authentication is required except for the public endpoints listed in the documentation. JSON fields use camelCase; pagination and some runtime responses have their own envelopes, documented in the API reference.

## Run locally with Docker

Run the following commands from the repository root with Docker Desktop running. The backend Compose file is `server/compose.yaml`; manual Docker commands below select it explicitly with `-f`.

Environment files and templates are kept outside version control. Before local setup, create and configure `server/.env` privately for your environment, including the frontend origin, session settings, and an `APP_KEY` entry. Setup generates an application key when that entry is empty; it preserves an existing key.

On Windows, this single command builds the image, installs dependencies from the lockfile, initializes the application key if missing, runs migrations and seeders, and waits for the API and database to become healthy:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

Add `-Smoke -Test` to run live HTTP checks and the full test suite. Use `-SkipBuild` when Dockerfile/php.ini has not changed:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1 -SkipBuild -Smoke -Test
```

The script preserves existing data, passwords, and APP_KEY. If Docker is not running, open Docker Desktop and try again. PHP, Composer, and PostgreSQL do not need to be installed on the host.

Add `-Client` to install the frontend dependencies from the lockfile and start Next.js in the foreground. This requires Node.js and pnpm on the host:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1 -SkipBuild -Client
```

Open `http://localhost:3001/auth`. The frontend proxies `/api/*` and `/sanctum/*` to Laravel, and its server components forward the authenticated session for private data. Ctrl+C stops Next.js; Docker services continue running. To start the frontend separately, run `pnpm install --frozen-lockfile` and `pnpm dev` from `client/`.

For manual startup or macOS/Linux:

```sh
docker compose -f server/compose.yaml build
docker compose -f server/compose.yaml run --rm api composer run setup
docker compose -f server/compose.yaml run --rm api php artisan migrate --seed --no-interaction
docker compose -f server/compose.yaml up -d
```

On a fresh clone, run the setup script or the manual `composer run setup` step before `docker compose -f server/compose.yaml up`. The API needs dependencies installed in its Docker volume before it can start.

- Frontend: `http://localhost:3001`.
- Main API: `http://localhost:8000`.
- Health: `GET /up`, `GET /api/v1/health` (includes a database connection check).
- Tool runtime: `http://127.0.0.1:8000`, using a separate hostname from the API/frontend to isolate cookies.
- PostgreSQL from the host: `127.0.0.1:5433`, database/user `lernumi`, local password `lernumi_dev`. The API container connects to `postgres:5432`.

The Compose configuration is intended for local development: it bind-mounts `server/` at `/var/www/html` and uses `artisan serve` with local settings. Composer dependencies are mounted separately at `/var/www/html/vendor` in the named `api_vendor` volume, reducing dependency-file access through the Windows source bind mount. An existing host `server/vendor/` is preserved but hidden by that mount inside the container; it is not the dependency directory used by the running API.

Run Composer through Docker whenever `server/composer.lock` changes. From the project root, rerun the setup script or install the locked dependencies directly into the volume:

```sh
docker compose -f server/compose.yaml run --rm api composer install --prefer-dist --no-interaction
```

`docker compose -f server/compose.yaml run --rm api composer run setup` requires your private `.env`, installs dependencies, and initializes an empty `APP_KEY`. Running Composer only against the host `server/vendor/` does not update the API's dependency volume.

PHP source edits remain available through the bind mount. Local OPcache has `validate_timestamps=On` and `revalidate_freq=0`, so changed PHP files are checked on the next request. After editing `.env`, run `docker compose -f server/compose.yaml restart api`. After editing Dockerfile/php.ini, run `docker compose -f server/compose.yaml up -d --build api`.

Both services use `restart: unless-stopped` and restart with the Docker engine unless you explicitly stop them. The API healthcheck calls `/api/v1/health` to check the database as well. Docker Desktop must be running after you start your computer; you can configure it to start when you sign in to Windows.

The seeder creates demo data only when `APP_ENV=local` or `testing`:

| Account | Email | Local password |
| --- | --- | --- |
| Admin | `admin@lernumi.test` | `LernumiDemo123!` |
| Teacher | `teacher@lernumi.test` | `LernumiDemo123!` |
| Student | `student@lernumi.test` | `LernumiDemo123!` |

The data includes institution `11111111-1111-4111-8111-111111111111`, subjects `COMP1000`/`COMP1010`, a study term, an offering, assessments, roles, and a sample workspace. Rerunning the seeder preserves existing account passwords.

Tool `demo-api-probe` version `1.0.0` is an activated sample package for checking the runtime. The `Demo tool practice` assessment has a weight of 0 and allows this tool; after opening it through the launch API, it displays the workspace, mode, and capabilities. This package and its data are seeded only in local/testing environments.

To create an administrator using an interactive password prompt:

```sh
docker compose -f server/compose.yaml exec api php artisan lernumi:create-admin admin@example.com --name="Administrator"
```

When setting up production, select the production Compose file instead of `server/compose.yaml`.

If the email already exists, this command updates the account and revokes its active sessions and tokens. In production, the seeder only synchronizes the permission catalog; it does not create demo accounts.

## Try the API and run tests

Open [requests.http](requests.http) in VS Code REST Client to try the main workflows. [subjects.http](subjects.http) contains separate Subject CRUD examples. See the API reference for cookie/CSRF authentication and issuing Bearer tokens.

```sh
docker compose -f server/compose.yaml exec -T api composer test
docker compose -f server/compose.yaml exec -T api php scripts/smoke-local.php
docker compose -f server/compose.yaml exec -T api php artisan route:list --path=api
```

Tests run against the dedicated PostgreSQL database `lernumi_testing`; the base test class refuses to run against another database. The initialization SQL in `docker/postgres/init-testing.sql` creates this database when the volume is first initialized. For an existing volume that does not yet have the test database, run:

```sh
docker compose -f server/compose.yaml exec -T postgres psql -v ON_ERROR_STOP=1 -U lernumi -d postgres -f /docker-entrypoint-initdb.d/10-testing.sql
```

The suites cover authentication/access control, institution boundaries, CRUD and validation, group attempts/submission/marking, private files/ETags, ZIP packages, and runtime/replay. Run one suite at a time because they share the test database.

The smoke script makes real HTTP requests locally: it logs in with all three demo accounts, checks CSRF and academic/access data, uploads a 3 MiB multipart file, checks downloads/ETags and write conflicts, opens a personal tool runtime, loads assets using the runtime cookie, and refreshes the capability token. Temporary workspaces and files are cleaned up afterward. If you changed the demo password, set the matching `DEMO_PASSWORD` in the local `.env`; the script does not print passwords, cookies, or tokens. Local email uses `MAIL_MAILER=log`: verification/reset links appear in `storage/logs/laravel.log` rather than being delivered to an inbox.

`docker compose -f server/compose.yaml down` stops the services and preserves both named volumes: `postgres_data` for the database and `api_vendor` for Composer dependencies.

## Code structure

The usual request flow is **Route → authentication middleware → FormRequest → Controller → Model → Resource**. Workflows with multiple steps use services and transactions, such as granting access, submitting attempts, saving files, or receiving events.

| Path | Responsibility |
| --- | --- |
| `routes/api.php` | Combines API versions and domain route files |
| `routes/auth-access.php`, `academic.php`, `learning.php`, `workspaces.php`, `tools.php`, `tool-runtime.php`, `media.php` | URLs and middleware for each domain |
| `app/Http/Requests/` | Domain folders containing input normalization and validation |
| `app/Http/Controllers/` | Request orchestration, authorization, model/service calls, and HTTP responses |
| `app/Services/` | Shared business rules and workflows with multiple steps |
| `app/Models/` | Eloquent models, relationships, UUIDs, and casts |
| `app/Http/Resources/` | JSON response shapes |
| `database/migrations/` | Tables, foreign keys, indexes, and PostgreSQL constraints |
| `database/seeders/` | Permission catalog and local demo data |
| `storage/app/private/` | Private avatars/logos, documents, tool ZIPs, and tool assets; accessed through APIs that check permissions |
| `tests/Feature/` | API tests using PostgreSQL |

`database/` contains code defining the database schema and seed data. `storage/` contains runtime files, logs, caches, and user files; actual table data lives in the PostgreSQL volume.

## Further documentation

- [All endpoints and payloads](../docs/api-reference.md).
- [Frontend integration: Sanctum, CSRF, errors, and workflows](../docs/frontend-integration.md).
- [Access control and authentication](../docs/auth-access-api.md).
- [Current ERD](../docs/data-model.md). The `docs/lernumi-erd.drawio` file preserves an earlier design for reference.
- [Writing Jenkins CI/CD and deploying to EC2](../docs/ec2-jenkins-guide.md): required files, networking, TLS, production Docker, secrets, migrations, backups, and rollback.
- [EC2 t4g.nano: ARM64, memory, and costs](../docs/ec2-t4g-nano.md): historical guidance for an optional smaller-instance setup; the selected instance is t4g.small.

The selected deployment runs this API, Nginx, and PostgreSQL in Docker on an EC2 t4g.small ARM64 in Sydney, with 1 GiB swap. The frontend uses Vercel with Functions in Sydney; Jenkins CI runs in local Docker. `Dockerfile.prod` and `compose.prod.yaml` currently prepare HTTP ACME verification and loopback diagnostics; public HTTPS and frontend deployment are not complete. The project owner writes CD to transfer trusted release source through SSH, build API and Nginx sequentially on EC2, migrate, and check health. A registry/ECR is optional.
