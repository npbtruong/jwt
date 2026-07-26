# Webhook Receiver — OTA Reservation Microservice

A small, production-shaped Laravel microservice that ingests reservation
webhooks from an OTA (Booking.com), authenticates them with **JWT**, persists
each reservation **synchronously** to its own **MySQL** database, and sends the
guest a notification email **asynchronously** via a **Redis-backed queue**.

It is deliberately minimal in scope but built with the patterns you'd use in a
real service, so it can be read as a reference for **JWT auth + Redis idempotency
+ Redis queue + clean layering**.

---

## What it does (the flow)

```
                POST /api/v1/oauth/token
  Booking.com  ───────────────────────────►  AuthController
   (server)     { client_id, client_secret }        │ verify HASHED secret
                                                     ▼
                ◄───────────────────────────  short-lived JWT (expires_in)

                POST /api/v1/webhook
  Booking.com  ───────────────────────────►  [ jwt middleware ]  verify sig+exp, resolve client
   (server)     Authorization: Bearer <jwt>          │
                reservation JSON                      ▼
                                              WebhookService::process
                                              1. Redis SET NX EX <event_id>  ── duplicate? ─► 200 "already processed"
                                              2. DB transaction: upsert reservation   (SYNC — done before responding)
                                              3. dispatch email job ->afterCommit()    (onto "notifications" queue)
                ◄───────────────────────────  200 { reservation_id, event_id }
                                                     │
                             (separate worker container)
                                                     ▼
                                     queue:work redis --queue=notifications
                                     sends mail · retries $tries/$backoff · failure → failed_jobs + log
```

### The patterns worth studying
- **Atomic idempotency** — a single Redis `SET <key> 1 EX <ttl> NX` decides
  "have we seen this `event_id`?". Concurrent OTA retries can never both win.
  See `app/Support/Idempotency/RedisIdempotencyStore.php`.
- **After-commit dispatch** — the email job is queued with `->afterCommit()`
  *inside* the DB transaction, so it never fires if the write rolls back.
  See `app/Services/Webhook/WebhookService.php`.
- **Hashed secrets** — clients store a bcrypt hash; the raw secret is verified
  with `Hash::check()` (constant-time) and never persisted.
- **Consistent envelope** — one `ApiResponse` helper + one exception `Handler`
  produce the same JSON shape for success (200), validation (422), auth (401)
  and duplicates (200). This is the service's stable public contract.
- **Sync persist / async email** — the reservation is durable *before* the 200;
  the slow SMTP work happens off-request on the worker.

---

## Stack

| Service     | Image                | Role                                             |
|-------------|----------------------|--------------------------------------------------|
| `app`       | build (`php:8.3-fpm`) | HTTP API (php-fpm)                               |
| `nginx`     | `nginx:1.27-alpine`  | reverse proxy → `app:9000`, docroot `public/`    |
| `worker`    | same as `app`        | `queue:work` on the `notifications` queue        |
| `mysql`     | `mysql:8.0`          | this service's own database (named volume)       |
| `redis`     | `redis:7-alpine`     | idempotency store **and** queue backend          |
| `phpmyadmin`| `phpmyadmin:5`       | optional DB UI at <http://localhost:8081>        |

No third-party PHP packages are required — the JWT codec is a small,
dependency-free HS256 implementation (`app/Support/Jwt/HmacJwtCodec.php`).

---

## Setup (from a clean clone)

> Only Docker is required on your machine — no PHP/Composer locally.

```bash
# 1) Environment file
cp .env.example .env

# 2) Build images and start everything (app, nginx, worker, mysql, redis)
docker compose up -d --build

# 3) Application key + a dedicated JWT secret
docker compose exec app php artisan key:generate
docker compose exec app sh -lc 'php -r "echo \"JWT_SECRET=\".bin2hex(random_bytes(32)).PHP_EOL;"'
#   → paste the printed JWT_SECRET=... line into your .env, then:
docker compose exec app php artisan config:clear

# 4) Create tables and seed one test client (prints its id + raw secret)
docker compose exec app php artisan migrate --seed
```

The seeder prints:

```
client_id:     booking_com_client
client_secret: s3cr3t_raw_value   (raw — shown once)
```

Web entry points: API at <http://localhost:8080>, phpMyAdmin at <http://localhost:8081>.

---

## Running & scaling the worker

The worker runs automatically as its own container (`docker compose up`). Because
the app is stateless, you can scale workers horizontally and independently of the
web tier:

```bash
# Run 4 worker replicas
docker compose up -d --scale worker=4

# Watch what the worker is doing
docker compose logs -f worker

# Restart workers after changing job code (queue workers are long-lived)
docker compose restart worker

# Inspect / retry failed jobs
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

The worker command (in `docker-compose.yml`):

```
php artisan queue:work redis --queue=notifications --tries=3 --backoff=10 --max-time=3600
```

---

## Full curl flow

```bash
# 1) Get a token
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/oauth/token \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"client_id":"booking_com_client","client_secret":"s3cr3t_raw_value"}' \
  | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')

# 2) Send the reservation webhook
curl -s -X POST http://localhost:8080/api/v1/webhook \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{
        "event_id": "evt_9f8c2a1b7d",
        "event_type": "reservation.created",
        "reservation": {
          "ota_reservation_id": "BK-2026-778812",
          "property_id": "PROP-4471",
          "guest_name": "Nguyen Van A",
          "guest_email": "guest@example.com",
          "check_in": "2026-08-01",
          "check_out": "2026-08-04",
          "room_type": "Deluxe Double",
          "total_amount": 3600000,
          "currency": "VND",
          "status": "confirmed"
        }
      }'
# → {"success":true,"message":"Reservation received","data":{"reservation_id":1,"event_id":"evt_9f8c2a1b7d"}}

# 3) Watch the worker send the email (MAIL_MAILER=log → written to the log)
docker compose logs --tail=20 worker
docker compose exec app sh -lc 'grep "Reservation confirmed" storage/logs/laravel.log | tail -1'

# 4) Confirm the row landed in MySQL
docker compose exec mysql mysql -uwebhook -psecret webhook \
  -e 'SELECT id, event_id, guest_name, total_amount, currency, status FROM reservations;'

# 5) Re-send the SAME event_id → idempotent, no re-insert / no second email
curl -s -X POST http://localhost:8080/api/v1/webhook -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"event_id":"evt_9f8c2a1b7d","event_type":"reservation.created","reservation":{ ... }}'
# → {"success":true,"message":"Event already processed","data":{"event_id":"evt_9f8c2a1b7d","duplicate":true}}
```

---

## Service Contract

Consumers depend on this — treat it as the stable interface.

### Response envelope

Every response, on every path, is one of these two shapes:

```jsonc
// success
{ "success": true,  "message": "Reservation received", "data": { "reservation_id": 12, "event_id": "evt_9f8c2a1b7d" } }

// error
{ "success": false, "message": "The given data was invalid.", "errors": { "reservation.check_in": ["The check in field is required."] } }
```

| Outcome            | HTTP | Shape                                                        |
|--------------------|------|--------------------------------------------------------------|
| Success            | 200  | `success:true`, `data`                                       |
| Duplicate event    | 200  | `success:true`, `message:"Event already processed"`, `data.duplicate:true` |
| Validation failed  | 422  | `success:false`, `errors{}`                                  |
| Auth failed        | 401  | `success:false`, `errors:{}`                                 |
| Not found / other  | 404 / 4xx / 500 | `success:false`, `errors:{}`                     |

### `POST /api/v1/oauth/token`

Exchange client credentials for a short-lived Bearer JWT. Rate limit: `10/min`.

Request:
```json
{ "client_id": "booking_com_client", "client_secret": "s3cr3t_raw_value" }
```
Response:
```json
{ "success": true, "message": "Token issued",
  "data": { "access_token": "<jwt>", "token_type": "Bearer", "expires_in": 3600 } }
```

### `POST /api/v1/webhook`

Ingest a reservation. Requires `Authorization: Bearer <jwt>`. Rate limit: `60/min`.

Request body: see the curl example above (`event_id`, `event_type`, nested
`reservation` object). Response:
```json
{ "success": true, "message": "Reservation received",
  "data": { "reservation_id": 12, "event_id": "evt_9f8c2a1b7d" } }
```

---

## Project layout

```
app/
├── Http/
│   ├── Controllers/Api/V1/ AuthController · WebhookController   (thin)
│   ├── Middleware/         JwtMiddleware                        (verify + resolve client)
│   ├── Requests/           TokenRequest · WebhookRequest        (validation)
│   └── Resources/          ReservationResource
├── Services/
│   ├── Auth/               AuthService                          (verify secret, issue JWT)
│   └── Webhook/            WebhookService                       (dedupe → tx upsert → afterCommit dispatch)
├── Repositories/
│   ├── Contracts/          *RepositoryInterface
│   ├── ClientRepository.php · ReservationRepository.php
├── Support/
│   ├── ApiResponse.php                                         (the envelope)
│   ├── Idempotency/        RedisIdempotencyStore (SET NX EX)
│   └── Jwt/                HmacJwtCodec (HS256)
├── Jobs/                   SendReservationNotificationJob       (ShouldQueue, notifications queue)
├── Mail/                   ReservationReceivedMail
├── Models/                 Client · Reservation
├── Exceptions/             Handler (envelope) + typed exceptions
└── Providers/              RepositoryServiceProvider            (interface → impl bindings)
```

Every cross-layer dependency is injected via an **interface** (bound in
`RepositoryServiceProvider`), so the Redis / DB / JWT implementations can be
swapped without touching callers.

---

## Configuration reference

All tunable via `.env` (see `.env.example`):

| Group          | Keys                                                             |
|----------------|-----------------------------------------------------------------|
| JWT            | `JWT_SECRET`, `JWT_ALGO`, `JWT_TTL`, `JWT_ISSUER`, `JWT_LEEWAY`  |
| Idempotency    | `IDEMPOTENCY_PREFIX`, `IDEMPOTENCY_TTL`                          |
| Notifications  | `NOTIFICATIONS_QUEUE`, `NOTIFICATIONS_TRIES`, `NOTIFICATIONS_BACKOFF`, `NOTIFICATIONS_TIMEOUT` |
| Rate limits    | `RATELIMIT_TOKEN`, `RATELIMIT_WEBHOOK`                           |
| Infra          | `DB_*` (MySQL), `REDIS_*`, `QUEUE_CONNECTION=redis`, `MAIL_MAILER=log` |

Config files: `config/jwt.php`, `config/idempotency.php`,
`config/notifications.php`, `config/ratelimit.php`.

---

## Troubleshooting

- **`migrate` can't reach the DB** — wait for `docker compose ps` to show `mysql`
  as `healthy`, then retry. Confirm `.env` has `DB_HOST=mysql`.
- **401 on the webhook** — the token expired (`JWT_TTL`, default 1h) or
  `JWT_SECRET` changed after minting. Get a fresh token.
- **Email didn't "send"** — with `MAIL_MAILER=log` it's written to
  `storage/logs/laravel.log`, not an inbox. Check the worker actually ran:
  `docker compose logs worker`.
- **Storage not writable (500 on log write)** —
  `docker compose exec app sh -lc 'chown -R www-data:www-data storage bootstrap/cache'`.
- **Changed config but no effect** — `docker compose exec app php artisan config:clear`.
