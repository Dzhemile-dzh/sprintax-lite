# Sprintax-Lite

Symfony questionnaire engine and IRS Form 1040-NR PDF generator (take-home assignment).

Domain model, Doctrine persistence, server-side visibility, a pluggable calculation engine, coordinate-based PDF overlay, Symfony Security, the admin questionnaire builder, and the client multi-page wizard are in place. Finalizing a submission queues PDF generation on Messenger. A worker consumes that job, writes `var/pdf/{submissionId}.pdf`, stores the path, and marks the submission `pdf_ready`. Owners and admins download the file at `/submissions/{id}/pdf`.

## Architecture

The app uses a pragmatic Clean Architecture / hexagonal layout. HTTP never talks to Doctrine or PDF libraries directly.

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
| Application | `App\Application` | Use cases that orchestrate starting a submission, saving a step, calculating, generating a PDF. |
| Infrastructure | `App\Infrastructure` | Doctrine repositories, FPDI PDF adapter, local filesystem, 1040-NR calculator, Messenger. |
| Presentation | `App\Presentation` | Thin controllers, forms, and other HTTP concerns for admin, client, and security. |

Meaningful boundaries:

- `CalculatorInterface` / `PdfGeneratorInterface` / `FileStorageInterface`
- `QuestionnaireRepositoryInterface` / `SubmissionRepositoryInterface` / `UserRepositoryInterface`

There are no generic managers, base CRUD services, or abstract domain service classes. Business rules must not live in controllers or Twig.

Calculation is pluggable: `CalculateSubmission` picks a `CalculatorInterface` by questionnaire name. `Form1040NrCalculator` is a simplified 10% tax stand-in whose output keys (`taxable_income`, `tax_owed`, …) are meant for PDF mappings, not IRS tables.

PDF overlay goes through `PdfGeneratorInterface`. File checks and directory creation go through `FileStorageInterface` rather than scattered `is_file` / `mkdir` calls. `GenerateSubmissionPdf` resolves the template as `resources/pdf/{form-name}.pdf`, maps visible answers and computed fields through admin `QuestionMapping` coordinates (mm), and `FpdiPdfGenerator` stamps those values. The generator has no hardcoded field positions. Finalizing a submission dispatches `GenerateSubmissionPdfMessage` on the async transport. The worker is idempotent: a second delivery of the same message does not write another PDF if the file is already there. Failed jobs retry, then land on the `failed` transport. Download uses `BinaryFileResponse` and `SubmissionVoter::DOWNLOAD`: another client gets 403, and a PDF that is not ready returns 404.

Security uses a `SecurityUser` adapter so the domain `User` stays free of Symfony. Clients register at `/register` (always `ROLE_CLIENT`). Admins cannot self-register. `SubmissionVoter` allows a client to view/edit/download only their own submission; admins can access any submission.

Admins manage questionnaires at `/admin`: ordered steps, questions (types, validation, visibility), choice options, and PDF mappings. Forms go through application use cases; clients receive 403.

Clients start and resume questionnaires at `/client`. Each step is its own route, saved with POST/redirect/GET. Hidden questions are ignored server-side, including extra POST fields, and answers are dropped when a condition hides them. Clients can go back to earlier steps but cannot skip ahead of `current_step`. Review is shown before submit; finalize marks the submission finalized and dispatches a Messenger message for PDF generation. The PDF is not built during the HTTP request. When the worker marks it `pdf_ready`, the owner (and any admin) can download it.

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

### Invariants

- Steps and questions keep a 1-based position within their parent; `Questionnaire` is the aggregate root for structure.
- Question `key` values are unique inside a questionnaire.
- Choice questions (`single_choice`, `multi_choice`) may have options; other types may not.
- `yes_no` is a dedicated type, not a choice list configured by the admin.
- PDF mappings cannot reference a question that is not on the questionnaire.
- A submission starts on the first step, belongs to one client and one questionnaire, and keeps at most one answer per question.
- Status only moves `in_progress` → `finalized` → `pdf_ready`. The generated file path is stored when the submission becomes `pdf_ready`.
- Clients are registered through `User::registerClient()`; admins are provisioned through `User::provisionAdmin()`.

Conditional visibility lives on the question (`equals` / `not_equals`). `QuestionVisibilityEvaluator` applies those rules server-side against answers keyed by question key:

- Every condition on a question must hold (AND).
- Missing or blank text answers hide both `equals` and `not_equals` dependents.
- An empty multi-choice list is “none selected”; `equals` / `not_equals` mean contains / does not contain.
- A hidden or missing controller hides its dependents. Cyclic rules hide both sides.

## Stack

- PHP 8.4+
- Symfony 7.4 LTS
- Doctrine ORM + migrations
- SQLite
- Twig, Forms, Validator, Security, Messenger
- FPDI + FPDF (coordinate overlay)
- PHPUnit + WebTestCase
- PHPStan
- GitHub Actions
- Docker

## Requirements

- Docker Desktop, or local PHP 8.4+ with the `pdo_sqlite` extension and Composer 2

## Docker

```bash
docker compose up --build
```

The app listens on [http://localhost:8080](http://localhost:8080). Compose also starts a `worker` service that waits until `var/data` is writable, then consumes async PDF jobs as `www-data`.

After the containers are up, apply the schema and demo data (as `www-data` so Apache can write the SQLite file):

```bash
docker compose exec --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec --user www-data app php bin/console doctrine:fixtures:load --no-interaction
```

If login fails with a readonly-database error, the data volume was created as root. Fix it with:

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/var/data
```

SQLite data is stored in a Docker volume. Linux vendor packages are isolated from the host `vendor/` directory so Windows and container PHP builds do not mix.

## Local PHP

```bash
composer install
copy .env.example .env
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8000 -t public
```

On Linux or macOS use `cp .env.example .env` instead of `copy`.

## Configuration

Runtime settings come from environment variables. Copy `.env.example` to `.env` for local PHP. Docker Compose sets its own values. Use `.env.local` for machine-specific secrets; `.env` files are not committed.

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | `dev`, `test`, or `prod` |
| `APP_SECRET` | Symfony secret |
| `DATABASE_URL` | SQLite path |
| `MESSENGER_TRANSPORT_DSN` | Async Messenger transport (Doctrine queue by default) |

## Quality commands

```bash
composer test
composer test:unit
composer test:functional
composer test:messenger
composer phpstan
composer lint
php bin/console doctrine:schema:validate --env=test
php bin/console doctrine:migrations:up-to-date --env=test
php bin/console debug:container --env=dev >/dev/null
```

PHPUnit suites follow the assignment layers: **unit** (visibility, calculation, domain rules, PDF orchestration, voters), **functional** (kernel/HTTP smoke, persistence, register, login, admin builder, wizard, resume, conditionals, review, finalize, authorization, PDF download), and **messenger** (handler behavior and idempotency). `composer test` runs all three.

PHPStan runs at level 8 against `src/` and `tests/` (PHP 8.4). There is no baseline: type issues are fixed in code. Warm the Symfony cache before PHPStan so the compiled container XML exists:

```bash
php bin/console cache:warmup
composer phpstan
```

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@example.test` | `admin123` |
| Client | `client@example.test` | `client123` |

Admins open `/admin`. Clients open `/client` (or register a new client at `/register`). The fixtures also load a two-step **1040-NR** questionnaire with a married/spouse visibility rule, income choices, and PDF mappings including computed `tax_owed`.

## PDF worker

After a client finalizes a submission, consume the async transport so the PDF is generated:

```bash
php bin/console messenger:consume async
```

Docker Compose runs that command in the `worker` service. Jobs retry up to three times, then move to the `failed` transport (`doctrine://default?queue_name=failed`). Generated files are written to `var/pdf/{submissionId}.pdf`. Download is `GET /submissions/{id}/pdf`.

## Continuous integration

GitHub Actions (`.github/workflows/ci.yml`) runs on every push to `main` and on pull requests. The job uses PHP 8.4 and SQLite only. It fails if Composer install, Symfony lint, PHP syntax, PHPUnit, PHPStan, Doctrine mapping, or migrations fail.

## Repository

https://github.com/Dzhemile-dzh/sprintax-lite
