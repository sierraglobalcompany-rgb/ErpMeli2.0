# F14 — External READ certification

**PHASE_RESULT: BLOCKED — real ERP2 account and valid existing READ credential context unavailable.**

## Authorization and preflight

- Human authorization is explicit: real Mercado Libre GET/READ, minimum sample only. This is not an authorization failure.
- Branch `impl/v3-b-sales-audit-20261010`; exact HEAD `e6da42159f88ddcab8fd56f98c44de97110d8138`; clean tree and no functional drift before this pass.
- F13 remains CLOSED LOCAL. Mapping/importer decisions and original evidence were not reopened.
- POST/PUT/PATCH/DELETE, OAuth refresh, remote writes, Alegra, DIAN, F15 and F16 are prohibited.

## Exact blocker and evidence

`F14_REAL_ACCOUNT_AND_VALID_EXISTING_READ_CREDENTIAL_CONTEXT_UNAVAILABLE`

The accessible ERP2 process has no configured DB environment or application/OAuth configuration. `.env` is absent and `Environment::all()` reads explicit process environment only. The preflight receipt records boolean presence, never values. No real ERP2 connection/account/token could therefore be inspected through that runtime. This is not a claim that credentials cannot exist elsewhere; no credentials were discovered outside the known runtime.

F13 mapping has A1/A2/A3 as REAUTHORIZE_NEW and four companies as CREATE_BEFORE_MIGRATION, not existing connected ERP2 rows. Intended seller IDs (585784977, 1797511852, 175243724) do not prove current actual ERP2 company/account/token association. Disposable QA/rehearsal accounts are TEST_FIXTURE; documentation is DOC_ONLY. Neither was substituted as live tenant authority. The redacted F13 snapshot has no tokens. ERP1 credentials were not reused for ERP2 certification.

Existing Sales and Billing consumers invoke OAuthRefreshService. `getValidAccessToken()` can invoke `oauth.token` POST on expiry; 401 handling can refresh again. That route cannot be used as a GET-only certifier. No service/bootstrap/worker was executed against production and no refresh was attempted. A valid existing token and a proven live account context are required before enabling a bounded GET-only transport; if renewal becomes necessary, the current authorization does not permit it.

To unblock: identify an existing configured certification runtime/source association with a real company/account, expected exact seller/site identity and a currently valid credential supplied through its secure configuration. Do not send credentials/tokens in chat, evidence or command arguments. Authorization to perform the F14 GET sample already persists; no repeat approval is required for that same scope.

## One operation registry

Machine-readable authority: [operation-registry.json](../storage/exports/f14/operation-registry.json). No operation registry was changed in application config.

| Operation | Required | Consumer / current status | GET endpoint family |
|---|---|---|---|
| ORDER_READ | YES | Sales SyncOrderHandler + Invoicing order source | `/orders/{order_id}` |
| PACK_READ | YES | InvoicingBridge PACK_MEMBERSHIP_UNVERIFIED gate; required by F14 | `/packs/{pack_id}` |
| BILLING_INFO_READ | YES | InvoicingBridge billing_data_status UNKNOWN; required by F14 | `/orders/billing-info/{site_id}/{billing_info_id}` |
| BILLING_PERIOD_DETAILS_READ | YES | BillingPeriodSyncHandler and BillingC0Probe | `/billing/integration/periods/key/{period_key}/group/ML/details` |
| SHIPMENT_READ | NO — NOT_NEEDED | No direct shipment consumer in current operation registry/code | `/shipments/{shipment_id}` |
| FISCAL_DOCUMENT_LIST_READ | NO — NOT_NEEDED | No independent current non-F16 consumer | `NOT_REGISTERED_NOT_NEEDED` |

Each registry entry includes input identity, company/account authority, normalized output, 206/error handling, evidence redaction and implementation/certification status. Required operations remain uncertified; NOT_NEEDED operations receive no calls.

## Concrete current contract gaps — not silently certified

- ORDER_READ: MeliClient preserves numeric lexemes and response headers/status, but SyncOrderHandler passes all 2xx responses to normalization without explicit 206/X-Content-Missing admission control. Normalization does not check remote `seller.id` against the actual account external seller ID. No order was admitted in this pass.
- PACK_READ: no registered `packs.get` path/adapter. Invoicing still returns PACK_MEMBERSHIP_UNVERIFIED. The required future certified interpretation is seller/account-visible membership, verified by reading each referenced order; no global completeness or historical grouping reconstruction.
- BILLING_INFO_READ: no registered site/id operation or billing-info normalization consumer. Invoicing exposes billing_data_status UNKNOWN. Missing fiscal fields remain unknown; buyer ID is not tax ID and fiscal address is not shipping address.
- BILLING_PERIOD_DETAILS_READ: real current consumer exists. Existing code already defers 206 without persistence/cursor advance and models 429; its live behavior remains uncertified. CREDIT_NOTE billing classification remains distinct from customer credit-note artifacts.
- SHIPMENT_READ: no direct current consumer; Financial shipping UNKNOWN is not evidence of a shipment HTTP consumer. NOT_NEEDED.
- FISCAL_DOCUMENT_LIST_READ: no existing independent non-F16 consumer. NOT_NEEDED.

The [official billing documentation](https://developers.mercadolibre.com.co/es_co/facturacion) specifies obtaining `buyer.billing_info.id` from the order and then GET `/orders/billing-info/{site_id}/{billing_info_id}`. The [official pack documentation](https://developers.mercadolibre.com.co/gestion-packs) documents GET `/packs/{pack_id}`. Documentation lookup is separate from authenticated API traffic and does not certify either operation.

## Offline validation and QA

No application/schema/test change was made while the live credential/tenant gate was blocked. Existing fake-transport tests were selected for regression validation; they do not establish all new F14 fixture requirements or replace live certification. RED/GREEN for a changed contract: NOT_RUN, because no functional patch was implemented. ORDER 206/missing-seller, seller mismatch, mixed-seller pack and site-specific billing-info completeness still need deterministic contract coverage when the certification context can be completed.

| Check | Fresh result |
|---|---|
| Existing focal regression | PASS — 79 tests / 744 assertions |
| Full PHPUnit | PASS — 412 tests / 2,998 assertions / one existing symlink skip / zero failures or errors |
| PHPStan | PASS — zero errors |
| Composer | PASS — validate --strict |
| PHP syntax | PASS — 162 files |
| Diff check | PASS |
| Registry/counters | PASS — four required, two NOT_NEEDED, registered methods GET, API counters zero |
| New F14 fixture matrix/live certification | NOT_COMPLETED — real credential/account context blocked; not represented as PASS |

QA used ready local disposable MariaDB at 127.0.0.1:3307/erp_meli2_test. Focal regression completed before the full suite. No new transient failures occurred and no root cause is asserted for the earlier F13 Work failures. Container stopped after QA.

## Live counters and privacy

[real-http-counters.json](../storage/exports/f14/real-http-counters.json): total=GET=POST=PUT=PATCH=DELETE=non_GET=writes=0; observed 206/401/403/404/429/5xx/timeouts=0 because no Mercado Libre API request was attempted. No live adapter instantiated; no counter is a simulated or inferred successful response. No token, cookie, Authorization header, raw provider payload, fiscal identity/address or PDF/XML was collected/exported.

No remote-method firewall implementation is claimed certified: the credential/authority gate stopped before network dispatch. Ordinary QA kept REAL_MELI_HTTP=0, ALEGRA_REAL_HTTP=0, DIAN_HTTP=0. Production ERP1/ERP2 writes=0. F15 NOT_STARTED; F16 UNAUTHORIZED.

## Final state

CURRENT=F14 BLOCKED — real account/valid existing READ credential context unavailable.
NEXT=F14 — restore the configured certification context, complete offline gaps and then the minimum authorized GET sample.
F13 remains CLOSED LOCAL; do not advance to F15.

STOP.

Local [F14 evidence ZIP](../storage/exports/f14/F14_READ_CERTIFICATION_20261012.zip), SHA-256 `c88eadd5c46a566cee8cd941fc21f709239eb3061fb628cf562b147ceaa1df1e`; 11 entries verified against SHA256 manifest and ZIP CRC. No upload.
