#!/bin/sh
set -eu

# The controller writes this credential to a private Docker volume at startup.
while [ ! -s /run/jenkins-agent/secret ]; do
    echo 'Waiting for the controller to register docker-local...'
    sleep 2
done

exec /usr/local/bin/jenkins-agent \
    -url http://jenkins:8080/ \
    -name docker-local \
    -secret @/run/jenkins-agent/secret \
    -webSocket \
    -workDir /home/jenkins/agent
