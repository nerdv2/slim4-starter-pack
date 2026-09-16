# Documentation

Developer documentation for the Customer DB application (Slim 4 backend).

| Document | Description |
|----------|-------------|
| [Architecture](architecture.md) | Stack, bootstrap sequence, request lifecycle and layering. |
| [Authentication](authentication.md) | Access/refresh tokens, rotation, cookies and roles. |
| [API Conventions](api-conventions.md) | Response envelope, authentication, pagination, errors and CORS. |
| [Database](database.md) | Connections, schema, `BaseModel`, query patterns and migrations. |
| [Development](development.md) | Local setup, environment variables, commands and adding an endpoint. |
| [Background Jobs](background-jobs.md) | Queue architecture, the CSV jobs, the worker and the dev runner. |
| [Deployment](deployment.md) | Container image, migrations, worker containers, Compose and CI. |
| [Caching](caching.md) | Redis design, key format, invalidation map and operations. |

Related files:

- [README.md](../README.md) — project overview and quick start.
- [AGENTS.md](../AGENTS.md) — contributor and AI-agent rules.
- [CONTRIBUTING.md](../CONTRIBUTING.md) — contribution workflow, quality gates and commit
  conventions.
- [SECURITY.md](../SECURITY.md) — vulnerability reporting and deployment hardening.
- [public/openapi.yaml](../public/openapi.yaml) — generated API reference (Swagger UI at
  `/swaggerui`).
