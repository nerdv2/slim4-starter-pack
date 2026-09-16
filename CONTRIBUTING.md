# Contributing

Thanks for considering a contribution. This project is a Slim 4 starter template; bug reports,
feature requests and pull requests are all welcome.

## Before You Start

- Read [AGENTS.md](AGENTS.md) — it documents the architecture rules, layering and conventions the
  code follows. Changes that ignore them will be asked to change.
- Read [docs/development.md](docs/development.md) for the local setup and command reference.
- For larger features or anything that changes routes, response envelopes or dependencies, open an
  issue first so the approach can be agreed on.
- Security issues must go through [SECURITY.md](SECURITY.md), not the public issue tracker.

## Development Setup

```bash
composer install
cp .env.example .env          # set database credentials and JWT_SECRET
composer run migrate
composer run serve            # http://127.0.0.1:8080
```

SQLite works for a quick start (`DB_DRIVER=sqlite`, `DB_NAME=storage/test_database.sqlite`); the
test suite always runs on SQLite and needs no database server.

## Quality Gates

Every pull request must pass:

```bash
composer run check            # composer validate + PHPStan level 5 + PHPCS (PSR-12)
composer run test             # PHPUnit unit + integration suites
php -l path/to/changed.php    # syntax check for each changed PHP file
```

Run `composer run generate-openapi-docs` after changing `#[OA\...]` attributes and
`composer run routes:cache` when routes change (regenerate before a deployment).

## Commit Messages

This repository uses [Conventional Commits](https://www.conventionalcommits.org/):

```text
type(scope): description
```

Types: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `chore`, `ci`, `build`, `release`.
Descriptions are lowercase, imperative and have no trailing period. Keep one logical change per
commit. See [AGENTS.md](AGENTS.md#commit-messages) for the full convention.

## Pull Requests

- Keep the change focused; avoid reformatting unrelated code.
- Add or update tests for behaviour changes.
- Preserve the existing API contract (`status`/`message`/`data` envelope and route paths); extend
  instead of renaming.
- Never commit secrets, credentials or `.env` values.
- Update `README.md`, `AGENTS.md` and `docs/` when behaviour or conventions change.
