# Lernumi

A learning management application with a Next.js frontend and a Laravel REST API backed by PostgreSQL.

- [`client/`](client/): the Next.js frontend, which you will connect to the API yourself.
- [`server/`](server/README.md): the backend for accounts, permissions, academic management, assessment attempts and marking, workspaces, calendars, and the tool runtime.
- [`compose.yaml`](compose.yaml): the local Docker environment for the API and PostgreSQL 17.

Start with the [backend setup guide](server/README.md), then read the [API reference](docs/api-reference.md), [frontend integration guide](docs/frontend-integration.md), and [current ERD](docs/data-model.md).

On Windows, open Docker Desktop and run the following command from this directory:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

Add `-Smoke -Test` to run real HTTP smoke checks and the full API test suite against a separate test database. Main API: `http://localhost:8000`; database health: `/api/v1/health`.

You will write the Jenkins CI/CD pipeline and deploy to EC2 yourself using [this guide](docs/ec2-jenkins-guide.md).

The selected cloud instance is **t4g.nano ARM64**. See the [dedicated guide](docs/ec2-t4g-nano.md) for memory limits, costs, and deployment steps.
