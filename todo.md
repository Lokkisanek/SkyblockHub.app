# TODO

## Sentry
- Open Sentry and verify the project is receiving events from both frontend and backend.
- Send one test frontend error and one test backend exception after deploy.
- Create alert rules for new errors, error spikes, and release regressions.
- Set alert ownership and threshold rules for growth, backend, frontend, and ops.
- Check that events are tagged with the correct release via `APP_RELEASE`.
- Confirm the environment in Sentry matches production before you rely on alerts.

## Later
- Revisit the audit note in `opinion.md` after the alerting rules are in place.
