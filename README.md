# Sprintax-Lite

Symfony questionnaire engine and IRS Form 1040-NR PDF generator (take-home assignment).

This is the project foundation only. Domain features are added in later commits.

## Stack

- PHP 8.4+
- Symfony 7.4 LTS
- Doctrine ORM + migrations
- SQLite
- Twig, Forms, Validator, Security, Messenger
- PHPUnit + WebTestCase
- PHPStan
- Docker

## Requirements

- Docker Desktop, or local PHP 8.4+ with the `pdo_sqlite` extension and Composer 2

## Docker

```bash
docker compose up --build
```

The app listens on [http://localhost:8080](http://localhost:8080).

SQLite data is stored in a Docker volume. Linux vendor packages are isolated from the host `vendor/` directory so Windows and container PHP builds do not mix.

## Local PHP

```bash
composer install
php -S 127.0.0.1:8000 -t public
```

## Configuration

Runtime settings come from environment variables. Defaults live in `.env` and `.env.test`. Use `.env.local` for machine-specific overrides.

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | `dev`, `test`, or `prod` |
| `APP_SECRET` | Symfony secret |
| `DATABASE_URL` | SQLite path |
| `MESSENGER_TRANSPORT_DSN` | Messenger transport (async usage comes later) |

## Quality commands

```bash
composer test
composer phpstan
composer lint
php bin/console doctrine:schema:validate --skip-mapping
php bin/console debug:container --env=dev >/dev/null
```

Warm the Symfony cache before PHPStan so the compiled container XML exists:

```bash
php bin/console cache:warmup
composer phpstan
```

## Repository

https://github.com/Dzhemile-dzh/sprintax-lite
