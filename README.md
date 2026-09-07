# Elixir Clinic — Laravel Backend

Laravel + MySQL API implementing the [laser-clinics-backend-plan](../laser-clinics-backend-plan.md).

Greenfield only — all endpoints are under **`/api/v1/*`**. There is no legacy NestJS/MongoDB compatibility layer.

## Stack

- Laravel 13
- MySQL 8
- Laravel Sanctum (API tokens)
- PHPUnit unit & feature tests

## Docker

From the repo root: `docker compose up --build` — open http://localhost:3018.

## Setup

```bash
cd backend
cp .env.example .env

# Configure MySQL in .env:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=elixir_clinic
# DB_USERNAME=root
# DB_PASSWORD=

composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve   # http://localhost:8000
```

## Frontend (via Next.js proxy)

In `my-app/.env.local`:

```
NEXT_PUBLIC_API_URL=http://localhost:8000
```

The frontend calls Laravel directly at `/api/v1/*`. CORS must allow `http://localhost:3000` (set `FRONTEND_URL` in backend `.env`). Admin panel: **`http://localhost:3000/Admin`**.

```bash
cd my-app
npm run dev   # http://localhost:3000
```

## API Structure (`/api/v1/*`)

| Group | Endpoints |
|-------|-----------|
| **Auth** | `POST /auth/register`, `/login`, `/verify-email`, `/resend-otp`, `/create-staff` |
| **Public** | `GET /clinics`, `/services`, `/doctors`; `POST /contact` |
| **Catalog** | `GET /clinics/{id}/services` |
| **Cart & checkout** | `/cart/*`, `/buy/checkout/*`, `/book/*`, `/payment/*` |
| **Customer** | `GET /customers/me`, `/appointments`, `/packages` |
| **Admin** | `/admin/services`, `/admin/appointments`, `/admin/users` |

## Domain Services

| Service | Responsibility |
|---------|----------------|
| `ClinicCatalogService` | Per-clinic service matrix & pricing |
| `CartPricingEngine` | Tier discounts + promo pipeline |
| `PrerequisiteService` | Treatment prerequisite checks |
| `AvailabilityService` | Slot generation |
| `SlotHoldService` | 5-minute slot holds |
| `PackageService` | Prepaid package lifecycle |
| `OtpService` | Email verification |
| `PromotionService` | Promo codes & auto discounts |

## Tests

```bash
php artisan test
```

## Seeded Data

- Admin: `admin@elixir.com` / `password`
- Clinics: Reading, London, Manchester
- 5 sample services with packages, benefits, FAQs
- Clinic-service availability matrix
- Sample doctors & schedules
