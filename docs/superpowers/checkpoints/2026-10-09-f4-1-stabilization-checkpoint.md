# CHECKPOINT — F4.1 Stabilization + pre-F5 hardening

Date: 2026-10-09
Repository: `sierraglobalcompany-rgb/ErpMeli2.0`
Branch: `fix/f4-1-stabilization-20261009`
Base: `impl/f4-sales-slice-20261009`
Draft PR: #13 — `F4.1 — Stabilization hardening before F5`
Verified functional HEAD before this checkpoint commit: `6d06b35998a7ad6bc03c9283605b9f95f76fe3ec`
Latest full QA: run #294 — SUCCESS.

## Purpose

Close concrete reliability/security gaps found after the F4 audit before opening F5. Keep KISS/YAGNI, existing Work Engine, existing MeliClient and four work states. No merge or deploy.

## Completed and verified

### 1. Fail-closed APP_ENV — DONE / GREEN

- Unknown `APP_ENV` rejected.
- Real Mercado Libre host only allowed when environment is exactly `production`.
- QA #262 SUCCESS.

### 2. Best-effort API telemetry — DONE / GREEN

- Recorder failures cannot mask valid HTTP responses or replace typed remote errors.
- RED #263; GREEN #264.

### 3. Poison-loop fail-safe — DONE / GREEN

Terminal local failure without remote HTTP for:
- unsupported work type → `unsupported_work_type`;
- malformed `order.sync` → `invalid_work_claim`;
- malformed `orders.reconcile` identity → `invalid_work_claim`.

Evidence: GREEN #269 / #277 / #279.

### 4. Bounded webhook body — DONE / GREEN

- Oversized `/webhooks/mercadolibre` is bounded before body parsing/persistence.
- Creates zero `webhook_events` and zero `work_items`.
- Valid webhook path unchanged.
- GREEN #282.

### 5. Database-scoped WorkRunner lock — DONE / GREEN

- CLI lock uses `erp_meli2.runner.<DB_NAME>`.
- Separate ERP2 databases on one MariaDB server no longer contend accidentally.
- GREEN #288.

### 6. Decimal precision — DONE / GREEN

RED proved actual precision loss in `DECIMAL(18,4)` handling:
- input `90071992547409.1234`;
- old persisted value `90071992547409.1250` because of `(float)` conversion.

Minimal GREEN:
- plain decimal strings are normalized/rounded to four decimals without float conversion;
- existing integer/float behavior remains supported;
- no BCMath, money library or decimal framework added.

Evidence:
- RED commit `0709be8855023d882ec8fea2ee351e8936e204da`, QA #290 expected failure;
- GREEN commit `cfcee48798265f2c36d709632f567b8fd4ee367f`, QA #291 SUCCESS.

### 7. OAuth admin authorization — DONE / GREEN

Audit found `/oauth/mercadolibre/connect` and callback accepted any company member although F3 plan requires an authenticated ERP admin.

Minimal GREEN:
- connect requires `company_users.role='admin'`;
- callback rechecks admin on the originally bound company before consuming state or exchanging the code;
- role loss during an in-flight OAuth flow aborts with 403;
- no new RBAC framework added.

Evidence:
- RED head `2ab5773ef732a21394c4f496eb513ea10c24dc6f`, QA #293: member got 302 and revoked-admin callback reached 500 instead of 403;
- GREEN commit `6d06b35998a7ad6bc03c9283605b9f95f76fe3ec`, QA #294 SUCCESS.

## Incidental fixture hardening

Several Sales tests had access-token fixtures expiring on 2026-10-09. They were changed to stable future dates. These were test-clock defects, not production regressions.

## Conclusion

F4.1 plus mandatory pre-F5 hardening is technically closed at functional HEAD `6d06b35998a7ad6bc03c9283605b9f95f76fe3ec` with QA #294 SUCCESS.

Do not merge/deploy automatically. PR #13 remains Draft and stacked over F4.

## Canonical next phase

F5 = Debug DVR Completo.

Master-plan scope:
- `storage/debug` + `storage/exports`;
- structured allowlist logger;
- Debug OFF minimal logs / Debug ON verbose structured events;
- one JSONL per UTC day;
- gzip closed days;
- default retention 7 days;
- configurable total storage cap;
- verbose logging stops at cap while normal warnings continue;
- UI: ON/OFF, retention, max storage, usage, history, download range, clear debug;
- export ZIP + manifest + checksums, TTL 24h;
- extend `bin/cleanup.php`;
- prove no secrets/PII, behavior parity ON/OFF, cap, retention, safe clear, traversal defense, export auth/TTL, gzip, and webhook→work→HTTP correlation.

Exit gates:
```text
DEBUG_BOUNDED=PASS
SECRET_LEAK=0
BUSINESS_BEHAVIOR_DIFF_ON_OFF=0
```

## External blockers remain open

Do not merge/deploy based only on CI green:
- Hostinger real preflight not certified;
- `main` branch protection not certified/enabled;
- dedicated Mercado Libre ERP2 application + callback/webhook authorization not certified;
- PR stack remains Draft/unmerged.

## Resume command

1. Verify QA #294 and functional HEAD above.
2. Do not reopen F4.1/pre-F5 hardening without regression evidence.
3. Create F5 branch stacked on this branch.
4. Write/execute F5 in microblocks with checkpoint after each green block.
5. Keep F6 Billing untouched until F5 exit gates pass.
