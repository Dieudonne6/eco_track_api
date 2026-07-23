# 📦 EcoTrack API — Supply Chain Traceability

**EcoTrack** is a REST API built with **Laravel 12**, designed to ensure traceability and data integrity throughout a supply chain. From the moment a product is created until it's delivered, every step is recorded with proof of location (GPS) and technical metadata (IoT sensors, temperature, humidity, etc.), and made accessible to the end consumer through a **QR code**.

---

## 🚀 Problems Solved

1. **Supply chain opacity** — every product can prove its real origin and journey through a public timeline accessible via QR code.
2. **Logistics fraud / broken chain of custody** — a dedicated algorithm detects inconsistent scans (skipped steps) and flags the product as `is_compromised`.
3. **Multi-device support** — the API accepts scans coming from a mobile app (workers) as well as from automated sensors (IoT), using a shared payload format.

---

## 🛠 Tech Stack

| Technology | Role |
|---|---|
| Laravel 12 (PHP 8.2+) | Backend framework |
| PostgreSQL | Database (Docker / production) |
| Laravel Sanctum | Token-based authentication (users and devices) |
| L5-Swagger (OpenAPI) | Interactive API documentation |
| Chillerlan PHP-QRCode | QR code generation (SVG format) |
| Docker / Docker Compose | Containerization (app, nginx, postgres) |

---

## 🏗 Backend Architecture

The project follows a layered architecture, with business logic extracted from controllers into a **dedicated service**:

- **`TrackingService`** — the core business logic of the application. It validates status transitions, prevents an already-delivered product from being re-scanned, and flags a product as `is_compromised` if a step was skipped.
- **UUID-based products** — every product has a public UUID (separate from its internal id), used to generate a QR code without exposing the database structure.
- **Flexible JSON checkpoints** — the `checkpoints` table stores free-form metadata (temperature, humidity, sensor battery level…) in a JSON column, alongside GPS coordinates.
- **User roles** — every user belongs to a company and has a role (`admin` or `worker`), which determines their permissions (only an admin can create a product).

### Status Workflow (strict)

```
created → processed → in_transit → delivered
```

A product can only move forward one step at a time (or stay at its current status); a product that's already `delivered` can no longer be scanned. Any attempt to skip a step is rejected with a `422` response.

---

## 🔗 Main Endpoints

**Public**

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Registration (see the two modes below) |
| POST | `/api/login` | Login, returns a Sanctum token |
| GET | `/api/companies` | List of registered companies |
| GET | `/api/products/{uuid}/history` | Public timeline of a product (end-consumer access) |
| GET | `/api/products/{uuid}/qrcode` | Generates the product's QR code image (SVG) |
| GET | `/api/worker/steps` | Lists valid statuses in the workflow |

**Protected (Sanctum)**

| Method | Endpoint | Required Role | Description |
|---|---|---|---|
| GET | `/api/allproducts` | Authenticated | Paginated list of products |
| POST | `/api/products` | **Admin** | Create a product (generates the UUID and the first `created` checkpoint) |
| POST | `/api/products/{uuid}/scan` | Authenticated | Record an official scan (mobile or IoT) |
| POST | `/api/worker/submit-scan` | Authenticated | Simulate a field scan (useful for demos) |

### Registration: Two Modes

`POST /api/register` accepts two payload formats depending on the role:

**Admin** (creates a new company):
```json
{
  "name": "Franck Admin",
  "email": "admin@mail.com",
  "password": "password123",
  "role": "admin",
  "company_name": "Smart Mobility",
  "company_type": "logistics"
}
```

**Worker** (joins an existing company via its `company_id`):
```json
{
  "name": "Jean Worker",
  "email": "worker@mail.com",
  "password": "password123",
  "role": "worker",
  "company_id": 1
}
```

`company_type` accepts: `producer`, `logistics`, `retailer`.

### Scan Example

```json
POST /api/products/{uuid}/scan
{
  "status": "processed",
  "latitude": 6.3673,
  "longitude": 2.4252,
  "location_name": "Cotonou Warehouse",
  "metadata": { "temperature": 4.5, "humidity": 20 }
}
```

---

## 🔐 Authentication

The API uses **Bearer Token** authentication via Laravel Sanctum.

```
Authorization: Bearer YOUR_TOKEN
```

Tokens work both for human users (mobile app) and for authenticated IoT devices.

---

## 🧱 Data Model

| Table | Description |
|---|---|
| `companies` | Companies (producer, logistics, retailer), typed via `type` |
| `users` | Users linked to a company (`company_id`), with a `role` (`admin` / `worker`) |
| `products` | Products, identified by a public UUID + internal SKU, with `status` and an `is_compromised` flag |
| `checkpoints` | Scan history: status, GPS location, place name, JSON metadata, author (user or sensor) |

---

## 🚦 Demo Walkthrough (via Swagger)

1. **Register** via `/api/register` as an admin, and grab the token.
2. **Authorize** in Swagger with that token (*Authorize* button).
3. **Create a product** via `POST /api/products`. Grab the returned `uuid`.
4. **View the QR code** via `GET /api/products/{uuid}/qrcode`.
5. **Simulate a logistics scan** via `POST /api/worker/submit-scan` using the UUID.
6. **Check the public timeline** via `GET /api/products/{uuid}/history`.

---

## 🚀 Installation

### With Docker (recommended)

```bash
# 1. Clone the project
git clone https://github.com/your-account/eco-track-api.git
cd eco-track-api

# 2. Set up the environment
cp .env.example .env

# 3. Start the containers
docker compose up -d
```

This starts:
- `app` — the Laravel application (PHP)
- `webserver` — Nginx, exposed on http://localhost:8003
- `postgres` — PostgreSQL, exposed on port 5434

```bash
# 4. Install dependencies and prepare the database
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# 5. Generate the Swagger documentation
docker compose exec app php artisan l5-swagger:generate
```

The documentation is then available at: `http://localhost:8003/api/documentation`

### Without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB_* in .env (PostgreSQL or SQLite)
php artisan migrate
php artisan l5-swagger:generate
php artisan serve
```


---

## 📈 What This Project Demonstrates

- Designing a REST API in Laravel with OpenAPI documentation
- Multi-actor token authentication (users and IoT devices)
- Separating business logic via a **Service Pattern** (`TrackingService`)
- Enforcing a strict business workflow with anomaly detection
- Flexible data modeling (public UUIDs, JSON columns for sensor data)
- Dynamic QR code generation
- Containerized environment with Docker

---

## 👨‍💻 Author

**Franck Dieu-donné AYENAN**
Backend Developer — Laravel