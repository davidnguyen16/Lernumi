# Local Jenkins in Docker

This stack runs Jenkins and its build environment inside Linux containers on Docker Desktop. Java, Node.js, pnpm, and the Docker CLI for jobs are included in the agent image. Host Java is not used.

From the repository root:

```powershell
docker compose -f compose.jenkins.yaml up -d --build
docker compose -f compose.jenkins.yaml ps
```

Open <http://localhost:8080>. The existing `jenkins_home` volume keeps the administrator account, plugins, and jobs. In **Manage Jenkins > Nodes**, `docker-local` should be online. Its label is `docker-local`, its remote root is `/home/jenkins/agent`, and it runs one job at a time. The built-in node has zero executors.

## Services

| Service | Purpose |
| --- | --- |
| `jenkins` | Controller and web UI; uses the existing Jenkins home volume. |
| `agent` | Java 21, Git, Node 24, pnpm 11.10.0, Docker CLI, Compose, and Buildx; executes jobs as the non-root `jenkins` user. |
| `docker` | Dedicated Docker-in-Docker engine for CI job containers and images. Uses TLS and has no published ports. |
| `init-volumes` | One-time ownership setup for the workspace and agent credential volume. Exiting successfully is expected. |

The startup Groovy script registers the agent and shares its generated credential through a private Docker volume. No administrator password or agent secret is stored in Git or printed in logs. Subsequent starts preserve an existing `docker-local` node's configuration; if changing it manually, keep the name and remote root consistent with this stack.

The Docker-in-Docker service requires `privileged: true`, following Jenkins' local Docker installation pattern. Run trusted jobs in this local learning environment. It uses a separate engine rather than mounting the Docker Desktop host socket.

The agent and its Docker engine mount the workspace volume at the same path. This allows job containers to bind-mount checked-out source files, including relative paths resolved by Compose. PHP and Composer belong in job containers; browser E2E also needs Chromium and its system libraries installed in a suitable job image.

## Verify the environment

```powershell
docker compose -f compose.jenkins.yaml logs --tail 30 agent
docker compose -f compose.jenkins.yaml exec agent java -version
docker compose -f compose.jenkins.yaml exec agent node --version
docker compose -f compose.jenkins.yaml exec agent pnpm --version
docker compose -f compose.jenkins.yaml exec agent docker version
docker compose -f compose.jenkins.yaml exec agent docker compose version
docker compose -f compose.jenkins.yaml exec agent docker buildx version
```

The agent log should contain `Connected`. Inside the agent, the controller is `http://jenkins:8080`; `localhost` refers to the agent itself.

## Existing CI and the CD you write

The repository already includes `Jenkinsfile` and `compose.ci.yaml` for backend tests, frontend type checks/build, and browser E2E on `agent { label 'docker-local' }`. This Docker stack provides the controller and agent; create/configure the job in Jenkins to load the pipeline from SCM. The project owner writes CD, following the [CI/CD guide](../../../docs/ec2-jenkins-guide.md): transfer trusted release source through SSH, then build API and Nginx sequentially on EC2 ARM64, migrate, and check health. The frontend deploys separately to Vercel; a container registry is optional.

`compose.ci.yaml` provides a separate stack for CI application services. `server/compose.yaml` is for local development and publishes fixed API/database ports. The repository-wide `Jenkinsfile` and `compose.ci.yaml` live at the repository root, alongside `compose.jenkins.yaml` for starting Jenkins. Jenkins setup files live in `server/docker/jenkins/`; backend runtime configuration lives in `server/`. Existing browser tests use specific localhost URLs, supported by the CI network configuration. Containers started by the CI engine belong to its own network, so its published ports are not Docker Desktop host ports.

This localhost Jenkins installation cannot receive GitHub public webhooks directly. Start with **Build Now**; configure polling or a reachable endpoint later.

## Stop and restart

```powershell
docker compose -f compose.jenkins.yaml down
docker compose -f compose.jenkins.yaml up -d
```

`down` preserves the named volumes. Adding `-v` deletes Jenkins state, credentials, workspaces, and the CI engine's stored data. This stack is for the local build machine; it is not an EC2 deployment stack.

References: [Jenkins in Docker](https://www.jenkins.io/doc/book/installing/docker/), [official inbound agent](https://github.com/jenkinsci/docker-agents/blob/master/README_inbound-agent.md).
