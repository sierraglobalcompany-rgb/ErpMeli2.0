# V3-A Exact Boundary + Work Safety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the demonstrated remote-number, timestamp, write-guard, retry/defer and writes-toggle gaps before any Sales historical or Billing handler work.

**Architecture:** Keep the existing `MeliClient`, `WorkRepository`, settings module and Sales order sync. Add only a minimal number-preservation boundary where exact decimals are persisted, a small scalar normalizer when a second concrete use exists, and two Work transitions (`retry` bounded, `defer` non-penalizing). Do not create new engines, queues, schedulers, state tables or generic policy registries.

**Tech Stack:** PHP 8.3–8.5, PHPUnit, PHPStan, PDO/MariaDB, existing Slim application.

**Spec:** `docs/ERP2_AUTHORITY.md`

## Global Constraints

- Correct > Simple > Stable > Maintainable > Efficient > Scalable.
- Apply `DELETE → SIMPLIFY → REUSE → MERGE → EXTEND → ADD` before every change.
- No float for exact money.
- Remote timestamps used for business truth require explicit `Z` or offset.
- One existing `MeliClient`; one existing `work_items` engine.
- No new queue/retry framework.
- `REAL_MELI_HTTP=0` in normal QA.
- Remote writes stay OFF; no merge/deploy.
- Every behavior change follows RED → expected failure → minimal GREEN → focused QA → full QA → checkpoint.

## Review Focus

1. JSON NUMBER `90071992547409.1234` must remain the exact lexeme; strings containing digits must not be rewritten.
2. A remote timestamp without `Z`/offset must fail closed instead of inheriting PHP/server timezone.
3. Unknown/missing operation classification must be blocked even if HTTP method is GET/POST.
4. Active 429 cooldown must defer the Work without consuming its retry budget or causing physical HTTP.
5. Repeated real transient failures must terminate after the fixed automatic attempt cap; crash recovery must not reopen exhausted Work forever.

---

### Task 1: RED — prove JSON NUMBER precision loss

**Files:**
- Modify: `tests/Integration/SyncOrderDecimalPrecisionTest.php`
- Create: `tests/Integration/MeliClientLosslessNumbersTest.php`

**Interfaces:**
- Consumes: current `MeliClient::request()` and `SyncOrderHandler::syncCurrentClaim()`.
- Produces: failing tests proving unquoted JSON decimals lose precision before persistence.

- [ ] **Step 1: Replace the precision fake with literal JSON NUMBER tokens**

Use a literal response body containing at least:

```json
{"total_amount":90071992547409.1234,"order_items":[{"quantity":12345.6789,"unit_price":90071992547409.1234}]}
```

Keep IDs/timestamps/core fields required by current handler.

- [ ] **Step 2: Add a focused MeliClient boundary test**

Assert that a lossless-enabled operation eventually exposes exact numeric lexemes as strings while numeric-looking content inside JSON strings remains unchanged.

- [ ] **Step 3: Run only the two precision tests**

Run:

```bash
vendor/bin/phpunit tests/Integration/SyncOrderDecimalPrecisionTest.php tests/Integration/MeliClientLosslessNumbersTest.php
```

Expected: RED for the unquoted decimal case; no unrelated failures.

- [ ] **Step 4: Commit RED only**

```bash
git add tests/Integration/SyncOrderDecimalPrecisionTest.php tests/Integration/MeliClientLosslessNumbersTest.php
git commit -m "test(v3-a): expose remote decimal precision loss"
```

---

### Task 2: GREEN — minimal lossless number boundary

**Files:**
- Create: `app/Integrations/MercadoLibre/Json/LosslessJsonDecoder.php`
- Modify: `app/Integrations/MercadoLibre/Client/MeliClient.php`
- Modify: `config/meli_operations.php`
- Test: `tests/Integration/MeliClientLosslessNumbersTest.php`
- Test: `tests/Integration/SyncOrderDecimalPrecisionTest.php`

**Interfaces:**
- Produces: `LosslessJsonDecoder::decodeObject(string $json): array`.
- Registry metadata: optional `preserve_numbers=true` only on `orders.get` and `billing.period.details`.

- [ ] **Step 1: Implement a lexical NUMBER-preservation wrapper**

Validate JSON first, scan outside strings/escapes, quote only valid JSON NUMBER tokens, then delegate structure decoding to native `json_decode`. Do not build a full JSON parser/AST and do not regex-rewrite arbitrary text.

- [ ] **Step 2: Wire the decoder only for operations marked `preserve_numbers=true`**

Do not pay the extra scan on `orders.search`, OAuth or users calls.

- [ ] **Step 3: Run focused tests**

Expected: both precision tests GREEN, including exponent/negative/zero cases and strings-with-digits preservation.

- [ ] **Step 4: Run MeliClient contract/rate tests**

```bash
vendor/bin/phpunit tests/Integration/MeliClientBoundaryTest.php tests/Integration/MeliOrderGetContractTest.php tests/Integration/MeliBillingPeriodDetailsContractTest.php tests/Integration/MeliRateLimitTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git commit -am "fix(v3-a): preserve exact remote numeric lexemes"
```

---

### Task 3: Strict remote timestamps + exact scalar normalization

**Files:**
- Create only if two current call sites justify it: `app/Integrations/MercadoLibre/Client/MeliValueNormalizer.php`
- Modify: `app/Modules/Sales/SyncOrder/SyncOrderHandler.php`
- Test: `tests/Integration/SyncOrderHandlerPersistenceTest.php`
- Test: `tests/Integration/SyncOrderDecimalPrecisionTest.php`

**Interfaces:**
- Required timestamp accepts only ISO8601 carrying `Z` or explicit `±HH:MM`; result is UTC DB text.
- Exact decimal accepts string/int contract values; float is rejected.

- [ ] **Step 1: RED for missing timezone and float money**

Add tests that remote `date_created` without zone and a float value reaching exact normalization fail closed with no partial Sales write.

- [ ] **Step 2: Implement the minimum normalization change**

If exact decimal + timestamp parsing now have at least two concrete call sites, extract `MeliValueNormalizer`; otherwise keep the smallest local helper and defer the class.

- [ ] **Step 3: Focused GREEN**

Run persistence + precision tests.

- [ ] **Step 4: Commit**

```bash
git commit -am "fix(v3-a): require exact remote sales values"
```

---

### Task 4: Fail-closed operation classification

**Files:**
- Modify: `app/Integrations/MercadoLibre/Client/MeliClient.php`
- Modify: `tests/Integration/MeliClientBoundaryTest.php`

**Interfaces:**
- Allowed classifications: `READ`, `AUTH`, `WRITE`.
- Unknown/missing: throw before transport.
- `WRITE`: require `meli_writes_enabled`.

- [ ] **Step 1: RED unknown classification**

Prove an operation with a typo/unknown classification never reaches the transport.

- [ ] **Step 2: Minimal GREEN**

Validate classification centrally in `MeliClient`; do not infer safety from HTTP verb and do not add an `effect` taxonomy.

- [ ] **Step 3: Run boundary/OAuth tests**

Ensure OAuth POST classified `AUTH` remains usable.

- [ ] **Step 4: Commit**

```bash
git commit -am "fix(v3-a): fail closed on operation classification"
```

---

### Task 5: Work retry cap + non-penalizing defer

**Files:**
- Modify: `app/Work/WorkRepository.php`
- Modify: `tests/Integration/WorkRetryRecoveryTest.php`
- Modify: `tests/Integration/SyncOrderHandlerRemoteFailureTest.php`
- Modify: `tests/Integration/OrderSyncWorkProcessorTest.php`

**Interfaces:**
- Keep current `retryCurrentClaim(...)`, but it must fail terminally when the fixed automatic attempt limit is reached.
- Add `deferCurrentClaim(int $id, string $claimToken, DateTimeImmutable $availableAt, string $code, string $safeMessage): bool` that requeues and reverses the claim increment without adding schema.
- Fixed cap starts at `5` unless an existing test/contract demonstrates a smaller safe value.

- [ ] **Step 1: RED bounded retry**

Prove repeated real retry calls terminate and set safe failure fields instead of returning to pending forever.

- [ ] **Step 2: RED defer**

Prove a claimed row with `attempts=1` deferred for cooldown returns to pending with `attempts=0` and future `available_at`.

- [ ] **Step 3: RED crash recovery at cap**

Prove exhausted running Work becomes failed instead of pending again.

- [ ] **Step 4: Minimal GREEN in WorkRepository**

No RetryPolicy class, no new columns, no retry table.

- [ ] **Step 5: Focused Work QA**

```bash
vendor/bin/phpunit tests/Integration/WorkRetryRecoveryTest.php tests/Integration/WorkRepositoryClaimTest.php tests/Integration/WorkStaleClaimTest.php
```

- [ ] **Step 6: Commit**

```bash
git commit -am "fix(v3-a): bound retry and add cooldown defer"
```

---

### Task 6: Route 429 to defer, not retry budget

**Files:**
- Modify: `app/Modules/Sales/SyncOrder/OrderSyncWorkProcessor.php`
- Modify: `app/Modules/Sales/ReconcileOrders/ReconcileOrdersHandler.php` only if still touched before its V3-B replacement
- Modify: `tests/Integration/OrderSyncWorkProcessorTest.php`
- Modify: `tests/Integration/MeliRateLimitTest.php` only for integration seam if needed

**Interfaces:**
- `MeliRateLimitException::retryAt` supplies defer time.
- Active cooldown and physical 429 both defer the same Work without burning automatic attempts.

- [ ] **Step 1: RED 429 Work attempt preservation**

Assert one Work hit by 429/cooldown stays pending at `retryAt` and retains its previous attempt budget.

- [ ] **Step 2: Minimal GREEN**

Catch the existing `MeliRateLimitException` at the Work-processing seam and call `deferCurrentClaim`.

- [ ] **Step 3: Verify no inline HTTP retry**

Existing `MeliRateLimitTest` must remain GREEN.

- [ ] **Step 4: Commit**

```bash
git commit -am "fix(v3-a): defer work during Mercado Libre cooldown"
```

---

### Task 7: Remove premature writes switch from UI

**Files:**
- Modify: `app/Modules/Settings/views/system.php`
- Modify: `app/Modules/Settings/SystemSettingsController.php`
- Modify: `app/Modules/Settings/SystemSettingsRepository.php`
- Modify: `tests/Integration/DebugSettingsControllerTest.php`
- Modify: `tests/Integration/SystemSettingsTest.php`

**Interfaces:**
- DB column `meli_writes_enabled` remains and defaults false.
- Admin settings update no longer accepts a writes value before F16.

- [ ] **Step 1: RED that settings POST cannot enable writes**

- [ ] **Step 2: Delete writes checkbox and update parameter from UI/controller/repository update method**

Do not remove the DB guard.

- [ ] **Step 3: Run Settings + MeliClient boundary tests**

- [ ] **Step 4: Commit**

```bash
git commit -am "fix(v3-a): keep Mercado Libre writes locked before F16"
```

---

### Task 8: Operational retention without archive machinery

**Files:**
- Modify: `app/Work/WorkRepository.php`
- Modify: `app/Integrations/MercadoLibre/Client/ApiUsageRecorder.php`
- Modify: `bin/cleanup.php`
- Create/modify focused tests as appropriate.

**Interfaces:**
- Purge Work terminal rows older than 30 days.
- Purge API usage aggregates older than 90 days.
- Keep DebugMaintenance as the existing file-maintenance component; do not create a MaintenanceEngine.

- [ ] **Step 1: RED retention boundaries**

Rows exactly inside retention remain; rows older than retention are removed; pending/running Work is never purged.

- [ ] **Step 2: Minimal purge methods and cleanup wiring**

- [ ] **Step 3: Focused QA**

- [ ] **Step 4: Commit**

```bash
git commit -am "chore(v3-a): bound operational retention"
```

---

### Task 9: Full V3-A gate + checkpoint

**Files:**
- Update: `docs/CURRENT_CHECKPOINT.md`
- Do not begin V3-B code.

- [ ] **Step 1: Run PHPStan**

```bash
vendor/bin/phpstan analyse
```

Expected: `0` errors.

- [ ] **Step 2: Run full PHPUnit**

```bash
REAL_MELI_HTTP=0 vendor/bin/phpunit
```

Expected: all tests PASS.

- [ ] **Step 3: Confirm gates**

```text
G1 REMOTE_TRUTH = PASS
G2 WORK_SAFETY = PASS
G3 RATE_SAFETY = PASS for implemented scope
G7 WRITE_FAIL_CLOSED = PASS
REAL_MELI_HTTP=0
```

- [ ] **Step 4: Update current checkpoint with exact SHA/test counts/remaining blocker**

Next blocker after A is V3-B Sales Audit; Billing remains blocked by C0.

- [ ] **Step 5: Commit checkpoint**

```bash
git commit -am "docs(checkpoint): close V3-A exact boundary"
```
