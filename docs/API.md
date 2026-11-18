# Ride API v1

Base URL: `https://ride.coditium.com/api/v1` (local: `http://10.0.2.2:8000/api/v1` from the Android emulator).
JSON only. Send `Accept: application/json`. Authenticated routes need `Authorization: Bearer <token>` (Laravel Sanctum).

## Conventions

### Envelope
```json
{ "success": true,  "message": "OK", "data": { } }
{ "success": true,  "message": "OK", "data": [ ], "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 40 } }
{ "success": false, "message": "Validation failed.", "errors": { "field": ["..."] } }
```
`GET /users/{id}/reviews` also returns `summary: { average_rating, total }`.

| Status | Meaning |
|---|---|
| 200 / 201 | OK / created |
| 401 | Not signed in, bad credentials, or revoked token |
| 403 | Not allowed (message says why, e.g. *"Only driver accounts can add vehicles. Switch your account type to both in your profile."*) |
| 404 | Not found |
| 409 | Wrong state (already booked, already cancelled ...) |
| 422 | Validation or business-rule error |
| 429 | Throttled |

Lists accept `?page=` and `?per_page=` (max 50).

### Dates and money (one format everywhere)
- **Responses:** ISO-8601 in **UTC with a trailing `Z`**, e.g. `"2026-11-15T02:00:00.000000Z"` (= 07:00 Asia/Karachi).
  Exception: pure dates (`date_of_birth`, `expiry_date`) are `"YYYY-MM-DD"` (they are not shifted by time zone).
  *Change in this release:* `date_of_birth` used to be sent as a shifted ISO datetime; it is now the plain date.
- **Requests:** `"YYYY-MM-DD HH:MM:SS"` in **Asia/Karachi** (ISO-8601 strings are accepted too). `date` filters are `YYYY-MM-DD`, `departure_after` is `HH:MM`.
- Amounts are PKR. Decimal columns are JSON strings (`"4500.00"`); aggregate fields in this release are real numbers.

### Relation keys
Eloquent relations are **snake_case**: `ride_offer`, `from_city`, `to_city`, `ride.driver`, `ride_offer.request`.

---

## 1. Account

### POST /register · POST /login
```json
{ "login": "03001234567", "password": "Password123", "device_name": "flutter" }
```
`data`: `{ "token": "...", "token_type": "Bearer", "user": { ... } }`. Register body: `name, phone (03XXXXXXXXX), email?, password, password_confirmation, user_type (driver|passenger|both), gender?, date_of_birth?, city_id?, cnic?`. Admin cannot be registered.

### User object (`GET /user`, `PUT /profile`, login/register)
```json
{ "id": 7, "name": "Sara", "email": "sara@ride.com", "phone": "03000000003", "user_type": "passenger",
  "gender": "female", "date_of_birth": "1995-08-20", "profile_image": "https://ride.coditium.com/storage/profile-photos/x.png",
  "city_id": 3, "address": "Johar Town", "is_verified": true, "status": "active",
  "emergency_contact_name": "Ahmed", "emergency_contact_phone": "03211234567" }
```
`profile_image` is always a full URL (or `null`). CNIC is never returned.

### PUT /profile — **changed**
Any subset of: `name, email, phone, gender, date_of_birth, city_id, address, cnic, profile_image, emergency_contact_name, emergency_contact_phone, user_type`.

`user_type` may only be **`"both"`** (passenger → both, driver → both; sending it when already `both` is a no-op).
Anything else is `422` (`"Account type can only be upgraded to \"both\"."`), and admin accounts get `422` (`"Administrator accounts cannot change account type."`).
```json
PUT /profile  { "user_type": "both" }
200 { "success": true, "message": "Profile updated.", "data": { "...": "...", "user_type": "both" } }
```

### POST /profile/photo — **new** (multipart)
Field `photo` (or `image`): jpg/png/webp, max 4 MB. The previous photo is deleted.
```json
200 { "success": true, "message": "Profile photo updated.",
      "data": { "profile_image": "https://ride.coditium.com/storage/profile-photos/abc.png", "user": { ... } } }
```

### Other
`POST /logout`, `GET /user`, `PUT /password` (`current_password, password, password_confirmation`), `GET|POST /saved-locations`, `DELETE /saved-locations/{id}`.

### Password reset by code — **new**
| Endpoint | Body | Notes |
|---|---|---|
| `POST /forgot-password` | `{ "login": "email or phone" }` | Throttle 5/min. Always answers `200` with the same text so accounts cannot be enumerated. |
| `POST /reset-password` | `{ "login", "code", "password", "password_confirmation" }` | Throttle 10/min. `422 "The code is invalid or has expired."` |

The 6-digit code is **emailed** (no SMS provider is connected). It expires after 15 minutes, allows 5 wrong guesses, can be re-requested once per 60 seconds, is single use, and a successful reset signs out every device.
A phone-only account (no email) cannot receive a code yet. `POST /verify-phone*` is **not implemented** (needs an SMS provider).

---

## 2. Bookings — **changed (additive)**
Applies to `GET /bookings`, `GET /bookings/{id}`, `GET /driver/bookings`, `POST /bookings`, `POST /ride-offers/{id}/accept`, `POST /bookings/{id}/confirm|cancel`.

```json
{
  "id": 12, "ride_id": 5, "ride_offer_id": null, "passenger_id": 3, "total_seats": 2, "price_per_seat": "4500.00",
  "total_amount": "9000.00", "payment_status": "pending", "booking_status": "confirmed", "booking_date": "2026-11-12T07:00:00.000000Z",
  "has_reviewed": true,
  "my_review": { "rating": 5, "comments": "Great" },
  "passengers": [ { "passenger_name": "Sara Khan", "passenger_phone": "03000000003" } ],
  "payments": [ ],
  "ride": {
    "id": 5, "from_location": "...", "to_location": "...", "departure_datetime": "2026-11-15T02:00:00.000000Z", "ride_status": "scheduled",
    "from_city": { "id": 1, "city_name": "Karachi" }, "to_city": { "id": 3, "city_name": "Lahore" },
    "vehicle": { "id": 1, "vehicle_type": "Car", "make": "Toyota", "model": "Corolla", "color": "White", "model_year": 2022, "registration_no": "ABC-123", "total_seats": 4 },
    "stops": [ { "stop_name": "Sukkur", "stop_order": 1 } ],
    "driver": { "id": 2, "name": "Ali", "phone": "0300...", "gender": "male", "profile_image": null, "is_verified": true,
                "rating_avg": 4.5, "reviews_count": 12, "completed_rides_count": 30, "member_since": 2025 }
  }
}
```
For a booking made from an offer, `ride` is `null` and:
```json
"ride_offer": {
  "id": 4, "offered_price": "8500.00", "offered_seats": 2,
  "request": { "id": 9, "from_location": "G-11 Islamabad", "to_location": "Johar Town Lahore",
               "departure_datetime": "2026-11-16T03:00:00.000000Z", "required_seats": 2,
               "from_city": { "id": 4, "city_name": "Islamabad" }, "to_city": { "id": 3, "city_name": "Lahore" } },
  "vehicle": { "id": 2, "make": "Honda", "...": "..." },
  "driver": { "id": 5, "name": "Bilal", "phone": "0300...", "is_verified": true, "rating_avg": 0, "reviews_count": 0, "completed_rides_count": 0, "member_since": 2026 }
}
```
- `has_reviewed` / `my_review` are for the **signed-in user** (a driver's `my_review` is the review they wrote about the passenger).
- Driver views (`/driver/bookings`, `/rides/{id}/bookings`, driver's `GET /bookings/{id}`, confirm) add `passenger`:
  `{ id, name, gender, profile_image, is_verified, phone, rating_avg, reviews_count, member_since, completed_bookings_count }`.
  **`phone` is `null` until the booking is `confirmed` (or `completed`).**

## 3. Driver rating stats — **new fields on every driver object**
`rating_avg` (0–5, 2 decimals, `0` when none), `reviews_count`, `completed_rides_count` (completed rides + completed offer bookings), `member_since` (year).
Present in: `GET /rides`, `GET /rides/{id}`, `GET /dashboard`, `GET /ride-requests/{id}/offers`, `GET /my/offers` (new `driver` key), bookings.

## 4. Push notifications (FCM) — **new**
| Endpoint | Body |
|---|---|
| `POST /device-tokens` | `{ "token": "...", "platform": "android\|ios", "device_name": "Pixel 7" }` → `201` new / `200` existing |
| `DELETE /device-tokens/{token}` | call on logout; idempotent |
| `DELETE /notifications/{id}` | own notifications only |

A token identifies one phone, so it is unique: if another account signs in on the same phone the token moves to that user.

Notification item (`GET /notifications`) now includes `booking_id, ride_id, request_id, offer_id` (nullable):
```json
{ "id": 31, "title": "New Booking", "message": "...", "notification_type": "booking", "is_read": false,
  "booking_id": 12, "ride_id": 5, "request_id": null, "offer_id": null, "created_at": "2026-11-12T07:00:00.000000Z" }
```
FCM message: `notification {title, body}` plus `data` (all strings; absent keys are omitted):
`{ "notification_id": "31", "type": "booking", "booking_id": "12", "ride_id": "5", "request_id": "9", "offer_id": "4" }`.
`type` ∈ `booking | ride | payment | offer | general`. Pushes are sent after the transaction commits; failures never break the API call; dead tokens are deleted automatically.

**Server setup:** put the Firebase service-account JSON outside git/public (e.g. `storage/app/firebase.json`) and set `FCM_CREDENTIALS=/full/path/firebase.json` in `.env`, then `php artisan config:cache`. Without it, pushes are skipped silently.

## 5. Documents — **new**
Files are private; they are only served to the owner/admin through the authenticated `file_url` (send the Bearer header, e.g. `Image.network(url, headers: ...)`).

| Endpoint | Notes |
|---|---|
| `GET /documents` | the user's documents |
| `POST /documents` | multipart: `type` (e.g. `cnic`, `driving_license`), `file` (jpg/png/webp/pdf ≤ 5 MB), `expiry_date?`. Replaces an earlier **non-approved** upload of the same type. |
| `GET /documents/{id}/file` | owner/admin |
| `GET /vehicles/{id}/documents`, `POST /vehicles/{id}/documents` | `type` ∈ `registration, insurance, driving_licence, driving_license, fitness, other` |
| `GET /vehicles/{id}/documents/{doc}/file` | owner/admin |

```json
{ "id": 4, "type": "driving_license", "document_type": "driving_license", "status": "rejected", "rejection_reason": "Photo is blurry",
  "expiry_date": "2028-12-31", "file_url": "https://ride.coditium.com/api/v1/documents/4/file", "created_at": "..." }
```
`status` ∈ `pending | approved | rejected`. Approval/rejection is done by an admin (database today; no admin API yet).

Vehicle object (`/vehicles`, `/vehicles/{id}`) now has `verification_status` (`approved` when `is_verified`; `rejected` if not verified and a document was rejected; else `pending`) and a `documents` array. Existing `status` (`active|inactive|blocked`) is unchanged.

## 6. Ride search — **changed**
`GET /rides` keeps its defaults (scheduled, seats left, future) and adds:

| Param | Meaning |
|---|---|
| `from_location`, `to_location` | partial, case-insensitive text match (`%`/`_` are literal) |
| `departure_after` | `HH:MM` — departs at or after this time of day |
| `sort` | `earliest` (default) \| `price` (cheapest first) \| `rating` (best driver first) |

Existing: `ride_type, from_city_id, to_city_id, date, seats, max_price`.

## 7. Complaints — **changed**
`POST /complaints { "booking_id"?: 12, "category"?: "payment", "subject": "...", "description": "..." }`
`booking_id` is optional (general complaint; if given you must be part of that booking). `category` ∈ `payment, driver_behavior, passenger_behavior, safety, vehicle, app_issue, other`.
`complaint_status` values: **`open`** (initial) → `in_progress` → `resolved` or `rejected` (set by admins).

## 8. Home screen — **new**
`GET /dashboard`
```json
{ "data": {
  "nearby_rides": [ { "...ride + driver with stats..." } ],
  "popular_routes": [ { "from_city_id": 1, "to_city_id": 3, "from": "Karachi", "to": "Lahore", "ride_count": 8, "min_price": 3500 } ],
  "recent_activity": [ { "...last 10 notifications..." } ],
  "active_rides": [ { "...ride...", "role": "driver" }, { "...ride...", "role": "passenger" } ]
} }
```
`nearby_rides` = upcoming rides leaving the user's `city_id` (falls back to all cities when empty).

## 9. Places — **new**
`GET /places?city_id=1&q=gul&limit=20` → `[ { id, city_id, name, latitude, longitude } ]`. Coordinates are `null` for the seeded areas (`php artisan db:seed --class=PlacesSeeder`). For production-quality autocomplete prefer **Google Places on the client**; this endpoint is a free fallback.

---

## 10. Clarifications

**`ride_requests.max_budget`** is the **total for all seats** (`required_seats`), not per seat. `ride_offers.offered_price` is also a **total**; an accepted offer creates a booking with `total_amount = offered_price` and `price_per_seat = offered_price / offered_seats`. (The app multiplying per-seat budget × seats is correct.)

**Payments**
- `payment_method` ∈ `Cash`, `Easypaisa`, `JazzCash`, `Bank Transfer`, `Card`.
- `payments.payment_status`: `pending` → `completed` (driver/admin confirms) → `refunded` (booking cancelled after payment). `failed` exists for a future gateway.
- `bookings.payment_status`: `pending` → `partial` → `paid`, or `refunded`.
- Who: passenger `POST /bookings/{id}/payments` (creates a **pending** row; amount ≤ outstanding); driver/admin `POST /payments/{id}/confirm`; cancelling a booking refunds all completed payments automatically.
- **Cash:** yes, create a payment row — it is the audit record and the only thing that moves `booking.payment_status` to `paid`. Create it when the passenger chooses Cash (at booking time or after the driver confirms), with the full amount; the driver confirms when the cash is received. The server does **not** create it automatically (that would block part-payments and other methods).

**Test accounts on the live server:** `ali@ride.com` (driver), `sara@ride.com` (passenger), `usman@ride.com` (both), `bilal@ride.com` (driver). The original seed script stored a hash that did not match `password`, so those logins never worked. `php artisan db:seed --class=TestAccountsSeeder` resets them to **`Password123`** (refuses to run when `APP_ENV=production` unless `RIDE_ALLOW_TEST_ACCOUNTS=true`).

---

## Endpoint index
Public: `GET /health`, `GET /cities`, `POST /register`, `POST /login`, `POST /forgot-password`, `POST /reset-password`.
Account: `POST /logout`, `GET /user`, `PUT /profile`, `POST /profile/photo`, `PUT /password`, `/saved-locations`, `GET /dashboard`, `GET /places`, `POST|DELETE /device-tokens`, `GET|POST /documents`.
Driver: `/vehicles` (+ `/vehicles/{id}/documents`), `POST /rides`, `GET /my/rides`, `GET /rides/{id}/bookings`, `POST /rides/{id}/cancel|start|complete|location`, `GET /driver/bookings`, `POST /bookings/{id}/confirm`, `POST /payments/{id}/confirm`, `GET /ride-requests`, `POST /ride-requests/{id}/offers`, `GET /my/offers`, `POST /ride-offers/{id}/cancel`.
Passenger: `GET /rides`, `GET /rides/{id}`, `GET /rides/{id}/tracking`, `POST|GET /bookings`, `GET /bookings/{id}`, `POST /bookings/{id}/cancel`, `POST /bookings/{id}/payments`, `GET /payments`, `POST /ride-requests`, `GET /my/ride-requests`, `GET /ride-requests/{id}`, `POST /ride-requests/{id}/cancel`, `GET /ride-requests/{id}/offers`, `POST /ride-offers/{id}/accept|reject`.
Both: `POST /bookings/{id}/reviews`, `GET /users/{id}/reviews`, `GET|POST /complaints`, `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/{id}/read`, `POST /notifications/read-all`, `DELETE /notifications/{id}`.
