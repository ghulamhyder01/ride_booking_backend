# Ride Booking Backend — API Documentation

## 1. Overview

Base URL for local development:

```text
http://127.0.0.1:8000/api
```

All examples use JSON. Include these headers where applicable:

```http
Accept: application/json
Content-Type: application/json
```

Protected endpoints require:

```http
Authorization: Bearer <access_token>
```

The API uses Laravel Sanctum personal access tokens. Login requires an explicit `role` (`rider` or `driver`); the issued token receives the matching ability. Rider and driver tokens are not interchangeable.

## 2. Authentication

### POST `/auth/login`

Public endpoint. Authenticates a user for the requested role and returns a bearer token.

**Request**

```json
{
  "email": "rider@example.com",
  "password": "Rider@12345",
  "role": "rider"
}
```

`role` must be `rider` or `driver`.

**Success — 200**

```json
{
  "message": "Login successful.",
  "data": {
    "token": "<sanctum-token>",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Demo Rider",
      "email": "rider@example.com",
      "role": "rider",
      "profile": {
        "id": 1,
        "user_id": 1,
        "phone": "03000000001"
      }
    }
  }
}
```

The profile object is the corresponding Rider or Driver model. Its fields depend on the model serialization.

**Errors**
- `401` — invalid email/password/role combination: `{"message":"Invalid credentials."}`
- `403` — account has no corresponding rider/driver profile: `{"message":"User profile is not configured."}`
- `422` — request validation failed.

### GET `/auth/me`

Authenticated endpoint. Returns the current authenticated user and role profile.

**Success — 200**

```json
{
  "data": {
    "id": 1,
    "name": "Demo Rider",
    "email": "rider@example.com",
    "role": "rider",
    "profile": {}
  }
}
```

`profile` is the corresponding serialized Rider or Driver model.

### POST `/auth/logout`

Authenticated endpoint. Deletes the current access token.

**Success — 200**

```json
{
  "message": "Logged out successfully."
}
```

## 3. Rider endpoints

All rider endpoints require a rider bearer token.

### POST `/rides` — Create a ride

Creates a ride, stores its fare estimate and status history, and returns an estimated pickup time.

**Required header**

```http
Idempotency-Key: <unique-client-generated-key>
```

The key must be present and no longer than 100 characters. Reuse the same key when retrying the same logical request. Reusing a key with a different payload returns HTTP 409. A replay currently returns HTTP 201 and the original ride.

**Request**

```json
{
  "pickup_address": "Liberty Market, Lahore",
  "destination_address": "Lahore Railway Station",
  "pickup_latitude": 31.5100,
  "pickup_longitude": 74.3440,
  "destination_latitude": 31.5497,
  "destination_longitude": 74.3436,
  "category": "economy"
}
```

**Validation**
- `pickup_address`, `destination_address`: required strings, maximum 255 characters.
- `pickup_latitude`, `destination_latitude`: required numeric values between -90 and 90.
- `pickup_longitude`, `destination_longitude`: required numeric values between -180 and 180.
- `category`: required; `economy` or `comfort`.
- `Idempotency-Key`: required header, maximum 100 characters.

**Success — 201**

```json
{
  "message": "Ride created successfully.",
  "data": {
    "ride": {
      "id": 1,
      "rider_id": 1,
      "pickup_address": "Liberty Market, Lahore",
      "destination_address": "Lahore Railway Station",
      "pickup_latitude": 31.51,
      "pickup_longitude": 74.344,
      "destination_latitude": 31.5497,
      "destination_longitude": 74.3436,
      "category": "economy",
      "status": "searching"
    },
    "pickup_eta_minutes": 4
  }
}
```

The ride object is the serialized Ride model returned by the service; database timestamps and any model-visible fields may also be present. The ETA value above is illustrative, not a guaranteed result.

**Errors**
- `401` — unauthenticated.
- `403` — rider profile missing or role/token not authorized.
- `409` — idempotency key reused with a different request payload.
- `422` — invalid request fields or missing/invalid idempotency key.

**Fare and ETA behavior**
- Fare is calculated using Haversine straight-line distance.
- Economy: base PKR 100 + PKR 30/km.
- Comfort: base PKR 150 + PKR 40/km.
- Pickup ETA uses the nearest available driver with matching category and coordinates, at configured average speed 25 km/h. It is not road- or traffic-aware.
- `pickup_eta_minutes` is `null` when no eligible driver with coordinates is available.

### GET `/rides` — Rider ride history

Returns the authenticated rider's rides, newest first, with `fareEstimate`. Pagination size is 10.

**Success — 200**

```json
{
  "message": "Ride history retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [],
    "first_page_url": "...",
    "from": null,
    "last_page": 1,
    "last_page_url": "...",
    "links": [],
    "next_page_url": null,
    "path": "...",
    "per_page": 10,
    "prev_page_url": null,
    "to": null,
    "total": 0
  }
}
```

The `data` value is Laravel's paginator object; its inner `data` array contains ride models with their fare estimate relation. Use `?page=2` for subsequent pages.

### GET `/rides/{ride}` — Ride details

Returns a ride only if it belongs to the authenticated rider. Eager loads `fareEstimate`, `statusHistories`, and `assignments`.

**Success — 200**

```json
{
  "message": "Ride details retrieved successfully.",
  "data": {
    "id": 1,
    "status": "searching",
    "fare_estimate": {},
    "status_histories": [],
    "assignments": []
  }
}
```

The `data` value is the serialized Ride model with the listed relationships. The example is abbreviated.

**Errors**
- `404` — ride does not exist or does not belong to the authenticated rider.
- `401` — unauthenticated.
- `403` — rider role/token is not authorized or rider profile is missing.

### POST `/rides/{ride}/cancel` — Cancel a ride

Cancels an owned ride if its status is `searching` or `accepted`.

**Success — 200**

```json
{
  "message": "Ride cancelled successfully.",
  "data": {
    "id": 1,
    "status": "cancelled"
  }
}
```

The `data` value is the serialized cancelled Ride model.

**Business rules**
- A rider can cancel only their own ride.
- `searching` and `accepted` rides can be cancelled.
- `in_progress`, `completed`, and already `cancelled` rides cannot be cancelled.
- If a driver had accepted the ride, the active assignment is ended and the driver is released.

**Errors**
- `404` — ride not found for this rider.
- `409` — ride is in a state that cannot be cancelled.
- `401` / `403` — authentication or role/token authorization failure.

## 4. Driver endpoints

All driver endpoints require a driver bearer token.

### GET `/driver/rides/available` — Available rides

Returns rides with `searching` status and a category matching the authenticated driver's `vehicle_category`. Results are oldest first, with 10 records per page.

**Success — 200**

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 0
  }
}
```

Each ride includes its `fareEstimate` relation. Use `?page=2` for pagination.

### POST `/driver/rides/{ride}/accept` — Accept a ride

Attempts to accept a searching ride. The service enforces ride/driver availability and category matching. Concurrent acceptance is protected by transaction/locking and database constraints; only one active assignment can win.

**Success — 200**

```json
{
  "message": "Ride accepted successfully.",
  "data": {
    "id": 1,
    "status": "accepted"
  }
}
```

**Business constraints**
- Ride must be `searching`.
- Driver must be `available`.
- Driver vehicle category must match ride category.
- Driver cannot already have another active ride.

### POST `/driver/rides/{ride}/start` — Start a ride

Starts a ride accepted by the authenticated driver.

**Success — 200**

```json
{
  "message": "Ride started successfully.",
  "data": {
    "id": 1,
    "status": "in_progress"
  }
}
```

Only the driver assigned to the accepted ride can start it.

### POST `/driver/rides/{ride}/complete` — Complete a ride

Completes an in-progress ride assigned to the authenticated driver.

**Success — 200**

```json
{
  "message": "Ride completed successfully.",
  "data": {
    "id": 1,
    "status": "completed"
  }
}
```

Completion ends the active assignment and releases the driver.

**Driver action errors**
- `401` — unauthenticated.
- `403` — role/token authorization failure.
- `404` — ride not found.
- `409` — state transition, assignment, category, or driver availability conflict.

## 5. Ride statuses and transitions

| Current status | Action | Next status | Actor |
|---|---|---|---|
| `searching` | Accept | `accepted` | Matching available driver |
| `searching` | Cancel | `cancelled` | Owning rider |
| `accepted` | Start | `in_progress` | Assigned driver |
| `accepted` | Cancel | `cancelled` | Owning rider |
| `in_progress` | Complete | `completed` | Assigned driver |

No other transitions are supported by the described API.

## 6. Standard HTTP status codes

| Status | Meaning in this API |
|---|---|
| `200` | Successful read or state-changing action |
| `201` | Ride creation (including an idempotent replay in the current implementation) |
| `401` | Missing/invalid authentication or invalid login credentials |
| `403` | Authenticated user lacks required role/token ability or required profile |
| `404` | Resource missing or not visible to the rider |
| `409` | Business-state conflict or idempotency-key payload conflict |
| `422` | Validation error or invalid/missing idempotency key |

Laravel validation responses normally include a `message` and an `errors` object keyed by field.

## 7. Postman setup

Import `postman_collection.json`. Create an environment with:

| Variable | Example |
|---|---|
| `base_url` | `http://127.0.0.1:8000/api` |
| `rider_token` | Set automatically by Rider Login request |
| `driver_token` | Set automatically by Driver Login request |
| `ride_id` | Set automatically by Create Ride request |
| `idempotency_key` | A unique value for a new ride request |

Recommended flow:
1. Rider Login.
2. Create Ride.
3. Driver Login with a matching vehicle category.
4. Driver — Available Rides.
5. Accept Ride, Start Ride, Complete Ride.
6. Rider — Ride History / Ride Details.

To test rider cancellation instead, create another ride and call Cancel Ride while it is `searching` or `accepted`.

## 8. Scope and limitations

- Distance is straight-line Haversine distance, not road distance.
- ETA is a simplified nearest-driver estimate and does not use live traffic or a routing provider.
- The available-rides endpoint returns matching-category searching rides; it does not itself reserve a ride.
- No payment processing, maps provider, push notifications, or real-time driver tracking is documented in this API.
