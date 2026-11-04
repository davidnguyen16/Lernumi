FROM node:24-bookworm-slim AS node
FROM docker:29-cli AS docker

FROM mcr.microsoft.com/playwright:v1.64.1-noble

COPY --from=node /usr/local/ /usr/local/
COPY --from=docker /usr/local/bin/docker /usr/local/bin/docker
COPY --from=docker /usr/local/libexec/docker/cli-plugins/ /usr/local/libexec/docker/cli-plugins/

RUN npm install --global pnpm@11.10.0

USER pwuser