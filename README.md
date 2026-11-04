# Lernumi

A learning management application with a Next.js frontend and a Laravel REST API backed by PostgreSQL.

- [`client/`](client/README.md): the Next.js frontend, connected to the API through a same-origin proxy and authenticated server-side reads.
- [`server/`](server/README.md): the backend for accounts, permissions, academic management, assessment attempts and marking, workspaces, calendars, and the tool runtime.
- [`compose.yaml`](compose.yaml): the local Docker environment for the API and PostgreSQL 17.

Start with the [backend setup guide](server/README.md), then read the [frontend guide](client/README.md), [API reference](docs/api-reference.md), [integration guide](docs/frontend-integration.md), and [current ERD](docs/data-model.md).

On Windows, open Docker Desktop, install Node.js and pnpm, and run the following command from this directory. It starts the API and PostgreSQL, then runs the frontend in the foreground:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1 -Client
```

Open **http://localhost:3001/auth**. The API is at `http://localhost:8000`; database health is available at `/api/v1/health`. Use `localhost` for the frontend; `127.0.0.1:8000` is reserved for the isolated tool runtime.

Demo accounts are `admin@lernumi.test`, `teacher@lernumi.test`, and `student@lernumi.test`, all with the local password `LernumiDemo123!`. They are seeded only in local/testing environments.

Add `-Smoke -Test` to run real HTTP smoke checks and the full API test suite against a separate test database. Omit `-Client` to start only the backend. Ctrl+C stops the foreground frontend; `docker compose down` stops the backend and preserves the database volume.

Run the setup script before using plain `docker compose up` on a fresh clone. Composer dependencies live in the Docker `api_vendor` volume; the script installs them from `server/composer.lock`. Source files remain bind-mounted for local editing. After the lockfile changes, rerun the script or use the [Docker Composer commands](server/README.md) to update that volume. An existing host `server/vendor/` directory is preserved, but the API uses the volume's dependencies. `docker compose down` retains both the database and dependency volumes.

You will write the Jenkins CI/CD pipeline and deploy to EC2 yourself using [this guide](docs/ec2-jenkins-guide.md).

For a local Jenkins controller and build agent that both run in Docker, follow the [Jenkins Docker setup](docker/jenkins/README.md). This environment includes the build tools; you write the `Jenkinsfile` yourself.

The selected cloud instance is **t4g.nano ARM64**. See the [dedicated guide](docs/ec2-t4g-nano.md) for memory limits, costs, and deployment steps.
