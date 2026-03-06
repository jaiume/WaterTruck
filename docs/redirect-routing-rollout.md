# Redirect Routing Rollout Checklist

Use this checklist after deploying the `/` and `/truck` routing hardening changes.

## Regression Scenarios

1. Set `localStorage.last_view = 'truck'` with an incomplete truck profile, load `/`, confirm user stays on `/`.
2. Set `localStorage.last_view = 'operator'` with no operator record, load `/`, confirm user stays on `/`.
3. Set `localStorage.last_view = 'customer'`, load `/`, confirm no role redirect occurs.
4. Use a complete truck profile with `localStorage.last_view = 'truck'`, load `/`, confirm redirect to `/truck`.
5. On `/truck` setup, click Cancel, confirm return to `/` and no redirect loop.
6. Simulate `/api/me` failure or timeout and load `/` with role views set, confirm graceful stay on `/`.
7. Simulate high-latency `/api/me` and confirm routing guard timeout degrades to stay on `/`.
8. Open `/` and `/truck` in two tabs and switch between them, confirm no `/` <-> `/truck` bounce.
9. Use browser back/forward navigation after role changes, confirm no loop state reappears.
10. Block `view-eligibility.js` once and load `/`, confirm fail-safe behavior (stay on `/`).

## Telemetry Checks

- Review access logs for repeated rapid alternation for the same client/session:
  - `/` <-> `/truck`
  - `/` <-> `/operator`
- Ensure no recurring pattern appears within a short window after deployment.

## Rollback Trigger

Rollback immediately to the previous stable release if post-release telemetry shows repeated `/` <-> role-page alternation for the same client/session in a short window.

