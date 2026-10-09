# F6A Billing Period-First Ingestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build crash-safe, period-first Mercado Libre Billing ingestion that caches normalized BILL/CREDIT_NOTE details locally and resumes by `last_id`, without historical order-by-order Billing loops.

**Architecture:** Reuse the existing single `MeliClient`, OAuth services, `work_items`, `WorkRepository`, and WorkRunner. A Billing module processes one remote period-details page per `billing.period.sync` claim; authoritative details, cursor progression, continuation work, and claim completion commit atomically. Financial formulas remain a separate F6B concern until verified legacy fixtures exist.

**Tech Stack:** PHP 8.5, Slim 4, PDO, MariaDB 11.4, PHPUnit 12, PHPStan, existing Mercado Libre client/work engine.

**Spec:** `docs/f6-billing-period-first-contract.md`

## Global Constraints

- Billing is fiscal/financial reconciliation, not the operational sales source.
- Primary ingestion endpoint is `GET /billing/integration/periods/key/{period_key}/group/ML/details`.
- `document_type` is exactly `BILL` or `CREDIT_NOTE`.
- Requests use `limit=1000`, sequential `from_id`, `sort_by=ID`, `order_by=ASC`.
- One `billing.period.sync` execution processes at most one physical page.
- HTTP 206 is incomplete and must not be treated as a final successful page.
- Per-order Billing is repair-only and must not become the history loop.
- Never persist raw Billing response JSON or unrelated PII.
- Monetary values remain exact decimal strings/DECIMAL columns; never derive via float.
- Reuse one `MeliClient`, one OAuth path, and one durable work engine.
- No BillingEngine, new queue, EventBus, CommandBus, CQRS, or parallel Billing fetch framework.
- RED → verify correct failure → minimal GREEN → full QA for every task.
- No merge/deploy without explicit authorization.

## Review Focus

- Cursor boundary: replaying a page after a crash must not duplicate details or skip the next page.
- 206 boundary: partial response must not advance an authoritative cursor or finish the stream.
- Remote contract boundary: malformed/mismatched detail IDs or decimal amounts must fail safely without partial local truth.
- Tenant boundary: company/account/period/document identities must not collide across sellers.
- Historical strategy boundary: F6A must have no loop that calls per-order Billing for all orders.

---

### Task 1: Freeze Billing operation contract and schema

**Files:**
- Create: `database/migrations/005_billing.sql`
- Create: `tests/Integration/BillingSchemaTest.php`
- Create: `tests/Integration/MeliBillingPeriodDetailsContractTest.php`
- Modify: `config/meli_operations.php`
- Modify: `app/Integrations/MercadoLibre/Client/MeliClient.php`
- Modify: `docs/meli-contracts-2026.md`

**Interfaces:**
- Consumes: existing migration runner, `MeliClient::request(...)`, operation registry shape.
- Produces: `billing_periods`, `billing_details`, operation key `billing.period.details`, validated `{period_key}` path support.

- [ ] **Step 1: Write RED schema tests**

Add tests proving:

- both Billing tables exist;
- `billing_periods` has scoped unique identity `(company_id, account_id, period_key, document_type)`;
- cursor/state/partial/last-sync fields exist;
- `billing_details.detail_amount` is DECIMAL;
- detail identity is unique by `(billing_period_id, external_detail_id)`;
- no raw payload/JSON/PII-style columns exist.

- [ ] **Step 2: Write RED operation-contract tests**

Prove `billing.period.details` dispatches exactly one GET to:

```text
/billing/integration/periods/key/2026-10-01/group/ML/details?document_type=BILL&limit=1000&from_id=0&sort_by=ID&order_by=ASC
```

Also prove malformed period keys are rejected before transport and unexpected path params are rejected.

- [ ] **Step 3: Run full QA and verify RED**

Expected: failures only because migration/operation/path support do not exist yet; pre-existing tests remain green.

- [ ] **Step 4: Implement minimal schema and operation support**

Create `005_billing.sql` with only the two spec tables. Add `billing.period.details` as a READ operation, and extend `MeliClient::resolvePath()` only for `{period_key}` with exact `YYYY-MM-01` calendar validation.

- [ ] **Step 5: Update contract documentation**

Move Billing period-details from deferred to verified contract, with official source and `verified_at=2026-10-09`. Do not register per-order Billing in F6A.

- [ ] **Step 6: Run full QA and verify GREEN**

Expected: PHPStan 0 and all PHPUnit tests green with `REAL_MELI_HTTP=0`.

- [ ] **Step 7: Commit/checkpoint Task 1**

Record exact QA run and branch HEAD before Task 2.

---

### Task 2: Normalize and fetch one Billing page

**Files:**
- Create: `app/Modules/Billing/SyncPeriod/BillingPeriodSyncHandler.php`
- Create: `tests/Integration/BillingPeriodSyncHandlerTest.php`

**Interfaces:**
- Consumes: `billing.period.details`, `WorkRepository`, `OAuthRefreshService`, `MeliClientResponse`.
- Produces: validated one-page normalized detail records without persistence outside the current work-claim transaction.

- [ ] **Step 1: RED valid page test**

Use fake transport to prove one claim requests exactly one page with the claim period/document/cursor and accepts a valid remote detail contract.

- [ ] **Step 2: RED normalization/adversarial tests**

Prove exact string-safe `detail_amount`, required remote detail identity, BILL/CREDIT_NOTE validation, optional safe metadata, and rejection of malformed/mismatched contracts without writing rows.

- [ ] **Step 3: Verify RED**

Expected: handler/class missing or behavior absent; no unrelated failures.

- [ ] **Step 4: Implement minimal handler request + normalizer**

Use existing OAuth flow and `MeliClient`; no alternate HTTP client. Parse only fields approved by the F6 contract and keep money as validated decimal text.

- [ ] **Step 5: Verify GREEN with full QA**

Expected: all suite green, no real Mercado Libre HTTP.

- [ ] **Step 6: Commit/checkpoint Task 2**

---

### Task 3: Atomic detail persistence and cursor continuation

**Files:**
- Modify: `app/Modules/Billing/SyncPeriod/BillingPeriodSyncHandler.php`
- Modify: `tests/Integration/BillingPeriodSyncHandlerTest.php`
- Create if useful only after RED proves composition need: `app/Modules/Billing/BillingWorkProcessor.php`
- Modify composition: `bin/work.php`

**Interfaces:**
- Consumes: normalized page from Task 2, `WorkRepository::completeCurrentClaim(...)`, `WorkRepository::enqueue(...)`.
- Produces: idempotent local details, persisted cursor, exactly one next-page work item when continuation exists.

- [ ] **Step 1: RED 1000-page + last_id test**

Assert 1000 unique remote details persist exactly once and the period cursor becomes the remote `last_id`.

- [ ] **Step 2: RED duplicate/replay test**

Replay the same page and prove detail count is unchanged and no duplicate active continuation work appears.

- [ ] **Step 3: RED crash-before-commit test**

Force persistence failure after HTTP but before commit; assert detail rows/cursor/next work do not advance, then recover/replay and complete successfully.

- [ ] **Step 4: Verify RED**

- [ ] **Step 5: Implement one atomic claim completion**

Within `completeCurrentClaim`, persist normalized unseen details, advance cursor/state, and enqueue next cursor work if the contract indicates another page. Do not start a second transaction.

- [ ] **Step 6: Wire Billing work into the existing runner**

Use a minimal top-level processor/composition that preserves Sales behavior and terminal handling for unsupported/malformed work. Do not introduce a registry framework unless current composition cannot remain explicit.

- [ ] **Step 7: Full QA GREEN**

- [ ] **Step 8: Commit/checkpoint Task 3**

---

### Task 4: Partial/retry/OAuth behavior and separate document streams

**Files:**
- Modify: `app/Modules/Billing/SyncPeriod/BillingPeriodSyncHandler.php`
- Modify: Billing work processor/composition from Task 3
- Modify: `tests/Integration/BillingPeriodSyncHandlerTest.php`

**Interfaces:**
- Consumes: existing `MeliRateLimitException`, OAuth refresh, work retry/fail operations.
- Produces: safe retry behavior for 206/429/401/5xx/transport and independent BILL/CREDIT_NOTE streams.

- [ ] **Step 1: RED 206 test**

Assert 206 leaves claim pending for a later time, sets/retains partial indication, and does not advance the page cursor as final truth.

- [ ] **Step 2: RED rate-limit and temporary remote failure tests**

Assert 429 uses the exact client retry timestamp; 5xx/transport become bounded pending retry without partial persistence.

- [ ] **Step 3: RED 401 refresh test**

Assert one refresh and one retry of the same Billing page; repeated unauthorized result is terminal/safe according to existing OAuth semantics.

- [ ] **Step 4: RED BILL/CREDIT_NOTE isolation test**

Same company/account/period must hold independent cursor/detail streams for both document types.

- [ ] **Step 5: Verify RED, then implement minimal GREEN**

Do not add Billing-specific rate limiter or retry engine.

- [ ] **Step 6: Full QA GREEN and checkpoint Task 4**

---

### Task 5: F6A adversarial closure

**Files:**
- Create: `tests/Integration/F6ABillingAdversarialTest.php`
- Modify only production files when a RED gate proves a concrete gap.
- Create checkpoint: `docs/superpowers/checkpoints/2026-10-09-f6a-billing-period-ingestion-checkpoint.md`

**Interfaces:**
- Consumes: complete F6A ingestion slice.
- Produces: durable evidence for `ORDER_BY_ORDER_HISTORY=0` and `CURSOR_RECOVERY=PASS`.

- [ ] **Step 1: RED/verify late unseen detail behavior**

Explicit re-sync may insert a newly observed remote detail identity without silently mutating an already persisted financial detail row. F6B owns business adjustment classification.

- [ ] **Step 2: Prove no per-order mass loop**

Test/runtime composition must perform period-details requests only for historical ingestion; per-order Billing is absent from this ingestion path.

- [ ] **Step 3: Tenant and raw-storage adversarial tests**

Prove same remote detail IDs can coexist safely across company/account/period streams and raw response/PII fields are not stored.

- [ ] **Step 4: Fresh full QA**

Expected:

```text
ORDER_BY_ORDER_HISTORY=0
CURSOR_RECOVERY=PASS
PHPSTAN=0
PHPUNIT=PASS
REAL_MELI_HTTP=0
```

Do not assert `FINANCIAL_FIXTURES=PASS`; that is F6B.

- [ ] **Step 5: Durable checkpoint**

Record branch HEAD, PR, QA evidence, completed gates, unresolved F6B legacy-fixture source requirement, and exact next action.
