# Sentry Ops Runbook

This document is the operational layer for Sentry in SkyblockHub.play. The codebase already sends frontend and backend exceptions to Sentry; this file defines who owns each alert, what to do first, and when to escalate.

## Routing Matrix

Frontend runtime issues:
- Owner: `frontend`
- Examples: Vue render errors, browser exceptions, unhandled promise rejections
- Response: acknowledge within 15 minutes and verify whether the issue is tied to the latest frontend release

Backend runtime issues:
- Owner: `backend`
- Examples: PHP exceptions, 5xx responses, job failures
- Response: acknowledge within 15 minutes and check whether the issue is caused by a deploy, queue backlog, or upstream dependency

Performance regressions:
- Owner: `ops`
- Examples: slow transactions, slow endpoints, frontend performance regressions
- Response: acknowledge within 15 minutes and decide whether the problem is isolated or systemic

Release regressions:
- Owner: `ops`
- Examples: new error spikes after deploy, regressions that appear only on the current release
- Response: acknowledge immediately, compare against the previous release, and decide whether to roll back or hotfix

## Triage Rules

1. Confirm environment and release first.
2. Ignore non-production noise until the alert is tagged with the correct production environment.
3. Use the routing matrix above to assign a single owner.
4. Treat login, checkout, billing, and dashboard-rendering incidents as blockers.
5. If the issue reproduces on the latest release, treat it as a regression until proven otherwise.
6. If the issue is noisy but not user-facing, keep the owner assigned and adjust the alert rule rather than silencing the signal permanently.

The operational thresholds in [config/ops.php](../config/ops.php) are intentionally short and explicit:
- acknowledge within 15 minutes
- mitigate within 4 hours for blockers
- escalate within 24 hours if the issue is unresolved

## Runbook

### First 15 minutes

1. Open the Sentry issue.
2. Check environment, release, and owner tags.
3. Confirm whether the issue matches a recent deploy or known incident.
4. Capture the stack trace, browser context, or request payload.

### First 4 hours

1. Classify the incident as frontend, backend, performance, or release regression.
2. Write down the owner and the next action.
3. If checkout, login, or dashboard rendering is blocked, choose mitigation first.
4. Escalate to ops when the issue crosses surfaces or when ownership is unclear.

### Next day

1. Confirm the alert is silent after the fix.
2. Add a short postmortem note for recurring incidents.
3. Tighten routing or thresholds if the issue was noisy or misclassified.

## What This Means In Practice

The Sentry integration itself is still the same transport layer:
- frontend exceptions are captured in `resources/js/app.js`
- backend exceptions are captured in `bootstrap/app.php`
- releases and environments are tagged through the CI environment variables and Sentry config

This runbook makes the operational side deterministic. Sentry now has a clear owner, a response target, and a follow-up path instead of being just a place where errors accumulate.