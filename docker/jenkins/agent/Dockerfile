FROM node:24-bookworm-slim AS node
FROM docker:29-cli AS docker

FROM jenkins/inbound-agent:jdk21

USER root

COPY --from=node /usr/local/ /usr/local/
COPY --from=docker /usr/local/bin/docker /usr/local/bin/docker
COPY --from=docker /usr/local/libexec/docker/cli-plugins/ /usr/local/libexec/docker/cli-plugins/

# Keep pnpm aligned with client/package.json; PHP/Composer run in job containers.
RUN npm install --global pnpm@11.10.0 \
    && npm cache clean --force

COPY --chmod=755 start-agent.sh /usr/local/bin/lernumi-jenkins-agent

USER jenkins
WORKDIR /home/jenkins/agent

ENTRYPOINT ["/usr/local/bin/lernumi-jenkins-agent"]
