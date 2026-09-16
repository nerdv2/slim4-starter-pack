# Security Policy

## Supported Versions

This project is a starter template: fixes land on `main` and there are no maintained release
branches. If you build a product from it, pin a commit or tag and review the diff before updating.

## Reporting a Vulnerability

Please **do not open a public issue** for security problems. Use one of these private channels:

- Preferred: [GitHub private vulnerability reporting](https://github.com/nerdv2/slim4-starter-pack/security/advisories/new)
  (Security → Report a vulnerability).
- Alternatively, email **work@gemawardian.com** with a description, reproduction steps and the
  affected version or commit.

You can expect an acknowledgement within a few days. Once a fix is ready it is released on `main`
and described in a GitHub security advisory; reporters are credited unless they prefer otherwise.

## Scope

Reports are useful for issues in the application code in this repository, for example:

- authentication or authorization bypasses (`src/Middleware`, `src/Helper/JwtHelper.php`);
- SQL injection or unsafe query construction (`src/Model`, `src/DTO`);
- secret leakage through configuration, logs or error responses;
- unsafe file upload handling (`src/Helper/UploadHelper.php`).

Vulnerabilities in third-party packages (Slim, Phinx, Twig, Predis, ...) should be reported
upstream; Dependabot monitors those dependencies in this repository.

## Deployment Hardening

When you deploy an application built from this starter, at minimum:

- set a random `JWT_SECRET` (at least 32 bytes) and a random `HEALTHCHECK_TOKEN`;
- keep `DISPLAY_ERROR_DETAILS=false` in production;
- keep `.env` out of version control (the repository `.gitignore` already covers it);
- restrict `CORS_ALLOWED_ORIGINS` to exact origins, or disable `CORS_ENABLED` and handle CORS at
  the reverse proxy;
- terminate TLS at the ingress or reverse proxy and keep `storage/` writable but not web-exposed.

See [docs/deployment.md](docs/deployment.md) and [docs/development.md](docs/development.md) for the
full configuration reference.
