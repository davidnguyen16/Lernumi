pipeline {
    agent { label 'docker-local' }

    options {
        timestamps()
        disableConcurrentBuilds()
        skipDefaultCheckout(true)
        timeout(time: 45, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }

    triggers {
        pollSCM('H/5 * * * *')
    }

    stages {
        stage('Checkout') {
            steps {
                deleteDir()
                checkout scm

                script {
                    env.COMPOSE_FILE = "${env.WORKSPACE}/compose.ci.yaml"
                    env.COMPOSE_PROJECT_NAME = "lernumi-ci-${env.BUILD_TAG}"
                        .toLowerCase().replaceAll('[^a-z0-9-]', '-')
                }

                sh 'mkdir -p reports'
            }
        }

        stage('Prepare') {
            steps {
                sh '''
                    set -eu

                    docker compose config --quiet
                    docker compose build api e2e
                    docker compose up -d --wait postgres

                    docker compose run --rm -T api composer install --prefer-dist --no-interaction
                    docker compose run --rm -T api composer check-platform-reqs
                '''
            }
        }

        stage('Backend checks and tests') {
            steps {
                sh '''
                    set -eu

                    docker compose run --rm -T api sh -ec 'find app bootstrap config database routes tests -name "*.php" -print0 | xargs -0 -r -n1 php -l'

                    docker compose run --rm -T api php vendor/bin/phpunit --colors=never --log-junit /reports/phpunit.xml
                '''
            }
        }

        stage('Start API for browser tests') {
            steps {
                sh '''
                    set -eu

                    docker compose run --rm -T api php artisan migrate:fresh --seed --force
                    docker compose up -d --wait api
                '''
            }
        }

        stage('Frontend checks and build') {
            steps {
                sh '''
                    set -eu

                    docker compose run --rm -T e2e pnpm install --frozen-lockfile
                    docker compose run --rm -T e2e pnpm typecheck
                    docker compose run --rm -T e2e pnpm build
                '''
            }
        }

        stage('Browser tests') {
            steps {
                sh 'docker compose run --rm -T e2e pnpm exec playwright test --reporter=line,junit'
            }
        }
    }

    post {
        always {
            script {
                if (env.COMPOSE_PROJECT_NAME && fileExists('compose.ci.yaml')) {
                    sh returnStatus: true, script: 'docker compose logs --no-color > reports/services.log 2>&1'
                }
            }

            junit testResults: 'reports/*.xml', allowEmptyResults: true

            archiveArtifacts(
                artifacts: 'reports/**,client/test-results/**',
                allowEmptyArchive: true
            )
        }

        cleanup {
            script {
                if (env.COMPOSE_PROJECT_NAME && fileExists('compose.ci.yaml')) {
                    sh 'docker compose down --volumes --remove-orphans --rmi local'
                }
            }
        }
    }
}