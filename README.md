# Sprintax-Lite

Symfony questionnaire engine that collects answers, runs a pluggable calculation, and overlays the result onto IRS Form 1040-NR as a PDF.

A client walks a multi-page wizard. Finalize queues PDF generation on Messenger. A worker writes `var/pdf/{id}.pdf`, stores `{id}.pdf` on the submission, and marks it `pdf_ready`. The owner or an admin downloads it at `/submissions/{id}/pdf`.

## Architecture

Pragmatic Clean Architecture / hexagonal layout. HTTP never talks to Doctrine or PDF libraries directly.

```
Controller (Presentation)
    ↓
Application use case
    ↓
Domain
    ↑
Infrastructure implementations
```

| Layer | Namespace | Responsibility |
| --- | --- | --- |
| Domain | `App\Domain` | Entities, value objects, repository interfaces, calculation/PDF contracts. No Symfony, HTTP, Forms, or Twig. Doctrine mapping attributes and collections only. |
| Application | `App\Application` | Use cases: start/save/finalize a submission, calculate, generate and download a PDF. |
| Infrastructure | `App\Infrastructure` | Doctrine repositories, FPDI adapter, local filesystem, 1040-NR calculator, Messenger. |
| Presentation | `App\Presentation` | Thin controllers and forms for admin, client, and security. |

Ports: `CalculatorInterface`, `PdfGeneratorInterface`, `FileStorageInterface`, `QuestionnaireRepositoryInterface`, `SubmissionRepositoryInterface`, `UserRepositoryInterface`.

There are no generic managers, base CRUD services, or abstract domain service classes. Business rules do not live in controllers or Twig.

## Domain model

```
User
 └── QuestionnaireSubmission
       ├── Questionnaire
       │      └── QuestionnaireStep
       │            └── Question
       │                  └── QuestionOption
       └── Answer
```

`QuestionMapping` belongs to the questionnaire and points at a question **or** a computed field (page + X/Y mm + optional font size).

Invariants:

- Steps and questions keep a 1-based position within their parent. `Questionnaire` is the aggregate root for structure.
- Question `key` values are unique inside a questionnaire.
- Choice questions (`single_choice`, `multi_choice`) may have options; other types may not.
- `yes_no` is a dedicated type, not an admin-configured choice list.
- PDF mappings cannot reference a question that is not on the questionnaire.
- A submission starts on the first step, belongs to one client and one questionnaire, and keeps at most one answer per question.
- Status only moves `in_progress` → `finalized` → `pdf_ready`. The generated file path is stored when the submission becomes `pdf_ready`.
- Clients are created with `User::registerClient()`; admins with `User::provisionAdmin()`.

## Requirements

- Docker Desktop, **or** PHP 8.4+ with `pdo_sqlite` and Composer 2
- PHP 8.4+, Symfony 7.4, Doctrine ORM, SQLite, Twig, Forms, Security, Messenger, FPDI/FPDF, PHPUnit, PHPStan

## Docker setup

```bash
docker compose up --build
```

Apache and the Messenger worker start together. The app is [http://localhost:8080](http://localhost:8080). On first boot the app container runs migrations and loads demo fixtures. SQLite lives in the `sqlite_data` volume. Linux `vendor/` packages live in `vendor_data` so Windows and container PHP builds do not mix.

This stack is for local use (`APP_ENV=dev`). `/_profiler` and `/_wdt` require `ROLE_ADMIN`. Do not publish port 8080 on a shared host without `APP_ENV=prod`, `APP_DEBUG=0`, and a unique `APP_SECRET`.

Commands that write SQLite or `var/cache` should run as `www-data`:

```bash
docker compose exec --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec --user www-data app php bin/console doctrine:fixtures:load --no-interaction
docker compose exec app composer test
docker compose exec --user www-data app php bin/console cache:warmup --env=dev --no-interaction
docker compose exec app composer phpstan
docker compose logs -f worker
```

`doctrine:fixtures:load` purges data. If demo logins fail after a partial first boot, run fixtures again or `docker compose down -v`.

If the worker service is not running:

```bash
docker compose run --rm --user www-data worker php bin/console messenger:consume async
```

Readonly-database after a root-owned volume:

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/var/data
```

## Installation (local PHP)

```bash
composer install
cp .env.example .env
```

On Windows use `copy .env.example .env`. Docker Compose injects its own environment and does not need a committed `.env`. Put machine secrets in `.env.local`.

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | `dev`, `test`, or `prod` |
| `APP_SECRET` | Symfony secret |
| `DATABASE_URL` | SQLite path |
| `MESSENGER_TRANSPORT_DSN` | Async Messenger transport (Doctrine queue by default) |
| `MAILER_DSN` | Mailer transport (`null://null` discards mail) |
| `MAILER_FROM` | From address for the client PDF email |

## Database migration

Local schema (default env):

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Mapping and sync check, same as CI (`--env=test`):

```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=test
php bin/console doctrine:schema:validate --env=test
php bin/console doctrine:migrations:up-to-date --env=test
```

In `dev`, Messenger uses the Doctrine transport and expects `messenger_messages`, which migrations do not create.

## Fixtures

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

Seeds demo users and a two-step **1040-NR** questionnaire (married → spouse visibility, income choices, PDF mappings including computed `tax_owed`). This command purges existing data.

## Demo credentials

| Role | Email | Password | Entry |
| --- | --- | --- | --- |
| Admin | `admin@example.test` | `admin123` | `/admin` |
| Client | `client@example.test` | `client123` | `/client` |

New clients register at `/register` (`ROLE_CLIENT` only). Admins cannot self-register.

## Running Symfony

Local PHP built-in server:

```bash
php -S 127.0.0.1:8000 -t public
```

Docker: [http://localhost:8080](http://localhost:8080) after `docker compose up --build`.

Admins manage questionnaires at `/admin`: ordered steps, questions (types, validation, visibility), choice options, and PDF mappings. Clients start and resume at `/client`. Each wizard step is its own route, saved with POST/redirect/GET. Clients can go back but cannot skip ahead of `current_step`. Review is shown before submit.

## Running the Messenger worker

Finalize does **not** build the PDF in the HTTP request. It marks the submission finalized and dispatches `GenerateSubmissionPdfMessage` on the `async` transport.

```bash
php bin/console messenger:consume async
```

Docker Compose already runs that in the `worker` service (`docker compose logs -f worker`). Jobs retry up to three times, then move to `failed` (`doctrine://default?queue_name=failed`). The handler is idempotent: a second delivery does not write another file if the PDF is already there. After the file is stored, the worker emails it to the **client account email** (`MAILER_FROM` is only the From address) with the PDF attached. A second delivery does not send another email. Mailer uses `MAILER_DSN`. Docker always sends to Mailpit (`smtp://mailer:1025`, inbox at http://localhost:8025), which **catches** mail and does not forward it to Gmail. For local PHP, point `MAILER_DSN` at a real SMTP server in `.env.local` for internet delivery. Email send runs inside the PDF worker (`message_bus: false`) so `pdf_emailed_at` is recorded only after the transport accepts the message. If sending fails, the PDF stays `pdf_ready` for download and the job retries the email.

## Running tests

```bash
composer test
composer test:unit
composer test:functional
composer test:messenger
composer lint
```

| Suite | Covers |
| --- | --- |
| unit | Visibility, calculation, domain rules, PDF orchestration, voters, architecture |
| functional | Register, login, admin builder, wizard, resume, conditionals, review, finalize, authorization, PDF download, waiting UX, PDF email |
| messenger | Handler behavior and idempotency |

`composer test` runs all three. PHPUnit uses SQLite at `var/test.db`.

Docker: `docker compose exec app composer test`.

## Running PHPStan

Level 8, `src/` and `tests/`, PHP 8.4, no baseline. Warm the container XML first:

```bash
php bin/console cache:warmup
composer phpstan
```

Docker: warm the **dev** cache as `www-data`, then run PHPStan as the default user so it can write `var/phpstan`:

```bash
docker compose exec --user www-data app php bin/console cache:warmup --env=dev --no-interaction
docker compose exec app composer phpstan
```

## CI

GitHub Actions (`.github/workflows/ci.yml`) runs on push to `main` and on pull requests. PHP 8.4 and SQLite only — no MySQL/PostgreSQL. The job fails if Composer install, Symfony lint (test + prod), PHP syntax, PHPUnit, PHPStan, Doctrine mapping, or migrations fail.

## PDF generation

Overlay goes through `PdfGeneratorInterface`. `GenerateSubmissionPdf` resolves `resources/pdf/{formType}.pdf` from the questionnaire's `FormType` (locked after a client starts; for Form 1040-NR, `resources/pdf/1040-nr.pdf`). It maps **visible** answers and computed fields through admin `QuestionMapping` coordinates (mm), and `FpdiPdfGenerator` stamps those values. The generator has no hardcoded field positions. File checks and directory creation go through `FileStorageInterface`. The worker writes the file under `var/pdf/` and stores `{id}.pdf` on the submission. It then emails that file to the client. The display name can be renamed without changing the template.

The official IRS form is **not** in this repository (`resources/pdf/` is empty aside from `.gitkeep`). Download a blank Form 1040-NR and save it as `resources/pdf/1040-nr.pdf` before generating a real overlay. Tests use their own dummy PDFs.

Download is `GET /submissions/{id}/pdf` (`BinaryFileResponse`). `SubmissionVoter::DOWNLOAD`: another client gets 403; a PDF that is not ready returns 404.

While status is `finalized`, the confirmation, review, client home, and submission pages refresh every 5 seconds, send `Cache-Control: no-store`, and show a preparing message with a **Check now** link. Refresh stops when the status becomes `pdf_ready` and the download link appears.

## Conditional visibility

Rules live on the question (`equals` / `not_equals`). `QuestionVisibilityEvaluator` applies them **server-side** against answers keyed by question key — not JavaScript.

- Every condition on a question must hold (AND).
- Missing or blank text answers hide both `equals` and `not_equals` dependents.
- An empty multi-choice list is “none selected”; `equals` / `not_equals` mean contains / does not contain.
- A hidden or missing controller hides its dependents. Cyclic rules hide both sides.
- Extra POST fields for a hidden question are not stored. When a condition hides a question, its previous answer is dropped. Finalize does not require hidden questions.

## Authorization

`SecurityUser` adapts the domain `User` so the domain stays free of Symfony. Routes use `#[IsGranted('ROLE_ADMIN')]` / `ROLE_CLIENT`. `SubmissionVoter` allows a client to view/edit/download only their own submission; admins can access any submission.

## Calculation architecture

`CalculateSubmission` picks a `CalculatorInterface` by the questionnaire's `FormType` (selected when the questionnaire is created, independent of the display name). `Form1040NrCalculator` is a simplified 10% tax stand-in (`taxable_income = max(0, wages − treaty)`, `tax_owed = 10%`). Output keys (`taxable_income`, `tax_owed`, …) are for PDF mappings, not IRS rate tables. Form type cannot change after a client has started the questionnaire.

## Known limitations

- Tax figures are a stand-in, not IRS Publication 519 / 1040-NR worksheets.
- SQLite is the only supported database; the schema is not tuned for concurrent production traffic.
- The IRS 1040-NR blank is not shipped. Without `resources/pdf/1040-nr.pdf`, Messenger PDF jobs fail. Overlay is coordinate-based; there is no interactive form-field fill.
- Messenger `messenger_messages` is created by Doctrine transport auto-setup in `dev`, not by migrations.
- Demo passwords are fixtures for local/CI use, not a production identity store.
- `MAILER_DSN` defaults to Mailpit in Docker (`smtp://mailer:1025`, inbox at http://localhost:8025) and local PHP (`smtp://127.0.0.1:1025`). Mailpit does not send to the public internet. For local PHP, use a real SMTP DSN in `.env.local` for Gmail delivery, or `null://null` to discard mail. Docker Compose keeps `smtp://mailer:1025` so the host `.env` DSN cannot leak into containers.

## AI assistance

Cursor/AI assistance was used on tests and review of that written code. I directed each assignment phase and decided what to keep in the repository.

## Repository

https://github.com/Dzhemile-dzh/sprintax-lite
