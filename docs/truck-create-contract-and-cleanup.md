# Truck Create API Contract and Cleanup Guidance

## API contract change

- Endpoint: `POST /api/trucks`
- New required fields at request boundary:
  - `name` (non-empty string)
  - `phone` (non-empty string)
  - `capacity_gallons` (positive integer)
- Requests missing required fields now return `400` with:
  - `{"success":false,"message":"name, phone, and capacity_gallons are required to create a truck"}`
- Existing callers that previously posted `{}` must be updated to send full setup payload.

## Why this change exists

The truck dashboard previously auto-called `POST /api/trucks` with an empty payload on first visit, which created placeholder rows with `NULL` profile fields. Creation now happens only on setup submit with real profile data.

## Invite flow compatibility

- Invite redemption flow is intentionally preserved.
- Placeholder creation semantics used by invite redemption are not blocked at service-layer create methods.
- Required field checks are enforced at `TruckController::create` request boundary only.

## Safe cleanup guardrails for historical empty rows

If cleaning up existing empty/incomplete truck rows, use conservative filters:

- **Age threshold:** only rows older than 14-30 days.
- **Incomplete profile:** all required profile fields still missing (`name`, `phone`, `capacity_gallons`).
- **Never activated:** `is_active = 0`.
- **No active workflow linkage:** avoid deleting rows with recent/active invite redemption relationship.

Review candidate rows before delete in production environments.
