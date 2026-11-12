# Ride API v1

Base URL: `{APP_URL}/api/v1` · JSON only · send `Accept: application/json`.
Authenticated routes need `Authorization: Bearer <token>` (Laravel Sanctum).

## Response format
```json
{ "success": true,  "message": "OK", "data": { } }
{ "success": true,  "message": "OK", "data": [ ], "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 40 } }
{ "success": false, "message": "Validation failed.", "errors": { "field": ["..."] } }
```
Status codes: 200 OK, 201 created, 401 unauthenticated / bad credentials, 403 not allowed, 404 not found,
409 conflict (wrong state), 422 validation or business-rule error, 429 too many requests.
Paginated lists accept `?page=` and `?per_page=` (max 50). Dates: `YYYY-MM-DD HH:MM:SS` (Asia/Karachi). Currency: PKR.

## Public
| Method | Path | Body / notes |
|---|---|---|
| GET | /health | API + DB status |
| GET | /cities | list of cities |
| POST | /register | name, phone (03XXXXXXXXX), email?, password, password_confirmation, user_type (driver\|passenger\|both), gender?, date_of_birth?, city_id?, cnic?, device_name? → token |
| POST | /login | login (email or phone), password, device_name? → token |

## Account
| Method | Path | Notes |
|---|---|---|
| POST | /logout | revokes current token |
| GET | /user | current user |
| PUT | /profile | name, email, phone, gender, date_of_birth, city_id, address, cnic, profile_image |
| PUT | /password | current_password, password, password_confirmation (other devices are signed out) |
| GET/POST | /saved-locations | location_name, city_id?, address, latitude?, longitude? |
| DELETE | /saved-locations/{id} | |

## Driver
| Method | Path | Notes |
|---|---|---|
| GET/POST | /vehicles | vehicle_type, make, model, registration_no, color, model_year, total_seats |
| GET/PUT/DELETE | /vehicles/{id} | delete deactivates when ride history exists |
| POST | /rides | vehicle_id, ride_type, from_city_id, to_city_id, from_location, to_location, lat/lng?, departure_datetime, arrival_datetime?, total_seats, price_per_seat, smoking_allowed, pets_allowed, luggage_allowed, gender_preference (any\|male\|female), notes, stops[]{stop_name, latitude, longitude} |
| GET | /my/rides?status= | driver's rides |
| GET | /rides/{id}/bookings | bookings on own ride |
| POST | /rides/{id}/cancel | reason? (cancels + refunds all bookings) |
| POST | /rides/{id}/start | |
| POST | /rides/{id}/complete | marks confirmed bookings completed |
| POST | /rides/{id}/location | latitude, longitude (only while started) |
| GET | /driver/bookings?status= | bookings on own rides/offers |
| POST | /bookings/{id}/confirm | |
| POST | /payments/{id}/confirm | driver confirms cash/transfer received |
| GET | /ride-requests | open passenger requests (filters from_city_id, to_city_id) |
| POST | /ride-requests/{id}/offers | vehicle_id, offered_price (total), offered_seats?, message? |
| GET | /my/offers | |
| POST | /ride-offers/{id}/cancel | withdraw pending offer |

## Passenger
| Method | Path | Notes |
|---|---|---|
| GET | /rides?from_city_id=&to_city_id=&date=&seats=&ride_type=&max_price= | search bookable rides |
| GET | /rides/{id} | ride detail with stops |
| GET | /rides/{id}/tracking | latest location + trail |
| POST | /bookings | ride_id, seats, passengers[]{passenger_name, passenger_phone}? |
| GET | /bookings?status= · GET /bookings/{id} | |
| POST | /bookings/{id}/cancel | reason? |
| POST | /bookings/{id}/payments | payment_method (Cash\|Easypaisa\|JazzCash\|Bank Transfer\|Card), amount, transaction_reference? → "pending" until confirmed |
| GET | /payments | |
| POST | /ride-requests | ride_type, from/to city + location, departure_datetime, required_seats, max_budget?, notes? |
| GET | /my/ride-requests · GET /ride-requests/{id} · POST /ride-requests/{id}/cancel | |
| GET | /ride-requests/{id}/offers | |
| POST | /ride-offers/{id}/accept | creates a confirmed booking |
| POST | /ride-offers/{id}/reject | |

## Both roles
| Method | Path | Notes |
|---|---|---|
| POST | /bookings/{id}/reviews | rating 1-5, comments? (completed bookings only, once each) |
| GET | /users/{id}/reviews | with average rating |
| GET/POST | /complaints | booking_id, subject, description |
| GET | /notifications?unread=1 · /notifications/unread-count | |
| POST | /notifications/{id}/read · /notifications/read-all | |

## Pending for later phases
FCM push (hook is `NotificationService::notify`), Google Maps, online payment gateways,
file upload for profile images/documents, admin approval of driver documents/vehicles.
