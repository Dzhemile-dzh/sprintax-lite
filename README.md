# Sprintax-Lite

Symfony questionnaire engine and IRS Form 1040-NR PDF generator (take-home assignment).

Domain model, Doctrine persistence, server-side visibility, and a pluggable calculation engine are in place. The client wizard, admin builder, PDF overlay, and security land in later commits.

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
| Infrastructure | `App\Infrastructure` | Doctrine repositories, FPDI PDF adapter, 1040-NR calculator. |
| Presentation | `App\Presentation` | Thin controllers, forms, and other HTTP concerns for admin, client, and security. |

Meaningful boundaries:

- `CalculatorInterface` / `PdfGeneratorInterface`
- `QuestionnaireRepositoryInterface` / `SubmissionRepositoryInterface` / `UserRepositoryInterface`

There are no generic managers, base CRUD services, or abstract domain service classes. Business rules must not live in controllers or Twig.

Placeholder PDF adapter remains. Calculation is pluggable: `CalculateSubmission` picks a `CalculatorInterface` by questionnaire name. `Form1040NrCalculator` is a simplified 10% tax stand-in whose output keys (`taxable_income`, `tax_owed`, …) are meant for PDF mappings, not IRS tables.

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
- Status only moves `in_progress` → `finalized` → `pdf_ready`.
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
