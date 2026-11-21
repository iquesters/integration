# Track B — Tenant-bound integration and OAuth administration

All legacy integration/config/credential/knob/sync routes require scoped administration before controller work. Listing uses the same ownership predicate as item actions, including all owners of shared resources. No creator-only exception grants tenant administration. Facebook onboarding uses the existing Util flow, adding service auth to the proxy client and session/actor/org-bound state in the common guard. Arbitrary global entity/provider mapping and misbound/raw Zoho token operations remain platform-only until B2 supplies a safe typed contract. The ambiguous creator-selected WooCommerce configuration route fails closed instead of selecting an arbitrary store.

Requires reviewed Foundation/Organisation v2 security sources, PHP 8.3+, Laravel 12 and Testbench 10 for regression fixtures. The current host v1 Composer constraints need a separately reviewed dependency/lock decision before release; this PR does not change or deploy the host.

## Validation

2 actual-route security tests pass (41 assertions), including foreign resource/OAuth and nested mapping denial. Tests use self-contained SQLite fixtures plus the shared Foundation policy. No provider calls or target DDL.

## Review and release gates

This branch is for review only. [Architecture and API contract](https://github.com/iquesters/chatbot-util/blob/feature/knorai-rbac-account-management/docs/security/TRACK_B_SECURITY_FOUNDATION.md), [complete source inventory](https://github.com/iquesters/chatbot-util/blob/feature/knorai-rbac-account-management/docs/security/LARAVEL_MIGRATION_INVENTORY.md), and [test evidence](https://github.com/iquesters/chatbot-util/blob/feature/knorai-rbac-account-management/docs/security/TEST_EVIDENCE.md).

Track C (chatbot-util #137) owns additive schema/revision IDs. No new Alembic head, historical SQL edit, live migration, production role grant, merge or deployment is included. Coordinate schema verification, tenant assignments, all Util caller service credentials and the host dependency lock before release. Do not merge schema-dependent changes before the reviewed C contract is agreed. PHP remains transitional; final backend parity moves to Python.
