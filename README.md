# Ride Booking Backend

A Laravel REST API for a simplified ride-booking system. The backend supports rider and driver authentication, ride creation, fare estimates, pickup ETA estimates, driver acceptance, ride lifecycle management, and rider ride history.

## Tech Stack

- PHP 8.3+
- Laravel 13
- PostgreSQL
- Laravel Sanctum personal access tokens
- PHPUnit

## Features

- Role-based rider and driver authentication.
- Sanctum bearer-token authentication with rider/driver token abilities.
- Ride creation with an idempotency key.
- Economy and Comfort vehicle categories.
- Haversine straight-line distance and configurable fare calculation.
- Pickup ETA based on the nearest available driver in the requested category.
- Driver ride discovery, acceptance, start, and completion.
- Rider cancellation while a ride is searching or accepted.
- Ride history, ride details, and status history.
- Database constraints to prevent multiple active assignments for a ride or driver.

## Requirements

- PHP 8.3 or later
- Composer
- PostgreSQL
- PHP extensions required by Laravel, including `pdo_pgsql`

## Installation

Run these commands from the backend project root:

```bash
composer install
```

Copy the example environment file:

```powershell
Copy-Item .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

## Environment Configuration

Update `.env` with your local PostgreSQL credentials.

Do not commit `.env` or put real credentials in `.env.example`.

```dotenv
APP_NAME="Ride Booking Backend"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ride_booking
DB_USERNAME=your_postgres_user
DB_PASSWORD=your_postgres_password
```

Create the PostgreSQL database before running migrations:

```sql
CREATE DATABASE ride_booking;
```

Run migrations and seed demo data:

```bash
php artisan migrate
php artisan db:seed
```

The default `DatabaseSeeder` invokes `DemoUserSeeder`. It creates one demo rider and ten demo drivers around Lahore.

> **Warning:** The demo seeder uses `updateOrCreate`. Running it again resets demo account passwords and driver availability/location data to the seeded values. Use demo credentials only in a local or disposable environment.

## Demo Accounts

All seeded accounts use the following credentials:

| Role         | Email                                           | Password       |
| ------------ | ----------------------------------------------- | -------------- |
| Rider        | `rider@example.com`                             | `Rider@12345`  |
| Driver 1     | `driver1@example.com`                           | `Driver@12345` |
| Drivers 2–10 | `driver2@example.com` to `driver10@example.com` | `Driver@12345` |

The seeder creates a mix of `economy` and `comfort` drivers. Driver 1 is an economy driver; Driver 2 is a comfort driver.

## Run the Application

```bash
php artisan serve
```

API base URL:

```text
http://127.0.0.1:8000/api
```

## Authentication

1. Send `POST /api/auth/login` with `email`, `password`, and `role`.
2. Copy `data.token` from the response.
3. Include it in protected requests using `Authorization: Bearer <token>`.
4. Use a rider token for rider endpoints and a driver token for driver endpoints.

Tokens are created with role-specific Sanctum abilities. The role middleware checks both the user's database role and token ability. A rider token cannot access driver routes, and vice versa.

## Fare and ETA Assumptions

The current sample configuration is:

| Category | Base Fare (PKR) | Per Kilometre (PKR) |
| -------- | --------------: | ------------------: |
| Economy  |             100 |                  30 |
| Comfort  |             150 |                  40 |

Fare distance uses the Haversine formula (straight-line distance between coordinates), not road distance. These are assessment/demo configuration values, not live market prices.

Pickup ETA is calculated using the nearest available driver with a matching vehicle category and a configured average speed of 25 km/h.

It is a straight-line estimate and does not account for roads, traffic, or live routing. If no eligible driver with coordinates is available, `pickup_eta_minutes` is `null`.

## Ride Lifecycle

```text
searching
    |
    | Driver accepts
    v
accepted
    |
    | Driver starts
    v
in_progress
    |
    | Driver completes
    v
completed

searching  ------> cancelled
accepted   ------> cancelled
```

A rider may cancel a ride only while it is `searching` or `accepted`.

Accepted ride cancellation and ride completion close the active driver assignment and release the driver.

## Run Automated Tests

The project is configured to use PostgreSQL for automated tests.

Configure `.env.testing` with a dedicated test database, for example:

```dotenv
APP_ENV=testing
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ride_booking_test
DB_USERNAME=your_postgres_user
DB_PASSWORD=your_postgres_password
```

Ensure the test database exists before running the suite.

Run:

```bash
php artisan test
```

Do not point the test environment at your development database. Avoid running destructive commands such as `php artisan migrate:fresh` against the development database.

## API Overview

All endpoints are under the `/api` prefix.

| Method | Endpoint                            | Access                                                    |
| ------ | ----------------------------------- | --------------------------------------------------------- |
| POST   | `/api/auth/login`                   | Public                                                    |
| GET    | `/api/auth/me`                      | Authenticated                                             |
| POST   | `/api/auth/logout`                  | Authenticated                                             |
| POST   | `/api/rides`                        | Rider                                                     |
| GET    | `/api/rides`                        | Rider                                                     |
| GET    | `/api/rides/{ride}`                 | Rider (own rides only)                                    |
| POST   | `/api/rides/{ride}/cancel`          | Rider (own rides only)                                    |
| GET    | `/api/driver/rides/available`       | Driver                                                    |
| GET    | `/api/driver/rides/current`         | Driver (if current-ride controller method is implemented) |
| POST   | `/api/driver/rides/{ride}/accept`   | Driver                                                    |
| POST   | `/api/driver/rides/{ride}/start`    | Driver                                                    |
| POST   | `/api/driver/rides/{ride}/complete` | Driver                                                    |

## Important API Notes

- Send JSON requests with `Accept: application/json` and, when sending a body, `Content-Type: application/json`.
- Ride creation requires an `Idempotency-Key` header of at most 100 characters.
- Reusing the same idempotency key with the same payload returns the original ride.
- Reusing the same idempotency key with a different payload is rejected with HTTP 409.
- The current controller returns HTTP 201 for an idempotency replay as well as a newly created ride.
- Available driver rides are filtered by the authenticated driver's vehicle category and have a page size of 10.
- Database transactions, row-level locking, and partial unique indexes protect ride assignment operations.

## Documentation and Resources

Additional project documentation and resources are available in the `docs/` directory.

| File                                                                                     | Description                                                                                                   |
| ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| [Architecture & Technical Decisions](docs/Architecture%20%26%20Technical%20Decisions.md) | Technology choices, architecture, concurrency handling, scalability, deployment, and production improvements. |
| [API Documentation](docs/documentation.md)                                               | API request and response examples and technical details.                                                      |
| [Postman Collection](docs/postman_collection.json)                                       | Importable collection for testing backend API endpoints.                                                      |
| [Architecture Diagram](docs/ride_booking_diagram.png)                                    | Visual representation of the system architecture and database relationships.                                  |
| [AI Disclosure](docs/AI_DISCLOSURE.md)                                                   | Disclosure of AI coding assistance and verification approach.                                                 |
| [Known Limitations](docs/known-limitations.md)                                           | Current limitations and proposed improvements.                                                                |

### API Testing

Import `docs/postman_collection.json` into Postman to explore and test the available API endpoints.

### Architecture

Refer to the architecture document and diagram in `docs/` for the system design and technical decisions.

## Frontend Repository

The frontend is maintained separately using Next.js and TypeScript.

**Frontend repository:** [Add your frontend GitHub repository URL here]

## Known Limitations

- No integrated payment gateway.
- No road-based routing or traffic-aware ETA.
- No WebSocket or push notification implementation; periodic polling is required for status updates.
- Driver matching is based on vehicle category and the available driver information.
- Production deployment, monitoring, and load testing have not been verified.

See [Known Limitations and Proposed Improvements](docs/known-limitations.md) for further details.
