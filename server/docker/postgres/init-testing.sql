-- Feature tests use a separate database so migrations never clear demo data.
SELECT 'CREATE DATABASE lernumi_testing OWNER lernumi'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'lernumi_testing')
\gexec
