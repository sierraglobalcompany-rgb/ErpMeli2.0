# F14 — Local READ contract closure

**PHASE_RESULT: PASS_LOCAL**

CURRENT=F14 LOCAL CONTRACT READY
LIVE_READ_CERTIFICATION=PENDING_F15_STAGE0
NEXT=F15 STAGE0 — fresh ERP2 OAuth for ONE seller + direct live calibration
F16=UNAUTHORIZED

## Preflight and scope

2026-10-12; branch `impl/v3-b-sales-audit-20261010`; HEAD before `39385dd638f0adcca76bcf0814ee9ec2bfca69a4`; clean tree. The Markdown anchor `38c6cb12e2779fcd356efc029668ce15ed567905` precedes the known F14 metadata STOP checkpoint by one compatible documentation-only commit (two docs, no functional drift). The direct request resumes the current local checkpoint; no reset/stash/rebase/pull/merge.

This run is local contract implementation and deterministic regression only. No SSH, ERP1 token/refresh/cron primitive, real Mercado Libre API request, OAuth/refresh, F15 or F16 execution. Previous live-token blockers do not block this corrected local exit. F13 mapping/importer remains CLOSED and untouched. Ordinary tests use fake transport and disposable local MariaDB; existing synthetic OAuth regression fixtures are not real OAuth operations.

## Two laws and implementation budget

Correct > Simple > Stable > Maintainable > Efficient > Scalable.

DELETE → SIMPLIFY → REUSE → MERGE → EXTEND → ADD:

- Merge order/pack resource path validation in the existing MeliClient.
- Extend existing operation registry with two explicit READ/GET paths only.
- Extend the existing immutable MeliClientResponse with optional X-Content-Missing metadata and pure local normalization; one shared strict ID helper.
- Extend existing Sales handler admission inside its existing Work transaction; no new reader, pack service, fiscal subsystem or owner.
- Existing response DTO lacked seller/pack/fiscal readiness semantics; existing public normalizers could not satisfy them. Extending that DTO avoids new production classes, storage or services.
- Update successful synthetic order fixtures to carry their intended seller. No production fallback for absent seller was introduced.

| Budget | Actual |
|---|---|
| New production classes/tables | 0 / 0 |
| New queues/workers/schedulers/cron | 0 / 0 / 0 / 0 |
| New OAuth services/refresh/retry frameworks/polling | 0 / 0 / 0 / 0 |
| New dependencies/generic service hierarchy | 0 / 0 |
| Changed production owners | Existing client/response, Sales handler and registry only |
| Billing/Financial/OAuth production changes | 0 |

## ORDER_READ

Existing `orders.get` remains GET `/orders/{order_id}`, lossless numeric decoding enabled. `MeliClientResponse::orderReadState()` exposes admission/completeness, MATCH/MISMATCH/UNKNOWN ownership, nullable billing-info reference and minimal billing/shipping availability. It does not rewrite source data.

- 200, correct resource, matching seller and no X-Content-Missing: admissible after existing required Sales normalization.
- Every 206 or advertised partial header: not admitted. Missing seller means UNKNOWN ownership; missing buyer means billing UNAVAILABLE; missing shipping means shipping UNAVAILABLE. No synthesized fields.
- Seller mismatch or wrong resource: blocked. Float/invalid seller identifiers are untrusted.
- Existing current account external_user_id is read under the same claim transaction with company/status scope and FOR UPDATE. Authority is checked before any order/item mutation. Failure rolls back and uses the existing order-contract failure path, with no new retry mechanism.
- Billing-info reference is distinct from buyer ID and fiscal identity. A reference alone leaves fiscal readiness UNKNOWN.
- Existing LosslessJsonDecoder and decimal Sales normalizer preserve exact lexemes, including `90071992547409.1234`. Missing/null/actual-zero sale_fee distinctions remain tested; no float money conversion was introduced.

## PACK_READ

New registry key `packs.get`, READ/GET `/packs/{pack_id}`, strict path binding, lossless numbers. `MeliClientResponse::packMembership()` returns only pack_id and matching-seller visible order_ids.

The caller supplies every independently resolved member response. No automatic calls, graph persistence or local historical grouping. Reject malformed/duplicate/missing/unresolved refs, partial pack/member or unknown seller. Retain matching sellers and exclude proven different sellers. No assertion of global membership completeness.

Matching/mixed seller, invalid/missing/duplicate refs, unknown seller, partial members, mismatched pack and empty pack are fixture-covered. Existing InvoicingBridge PACK_MEMBERSHIP_UNVERIFIED gate remains unchanged until actual live membership evidence is available; this local normalization does not invent durable proof.

## BILLING_INFO_READ

New registry key `orders.billing_info`, READ/GET `/orders/billing-info/{site_id}/{billing_info_id}`; strict site/id binding and lossless decoding. The order DTO yields the nullable buyer.billing_info.id needed by the documented flow.

`MeliClientResponse::billingInfoReadiness()` returns only an enum, not tax/name/address values:

| State | Local meaning |
|---|---|
| COMPLETE | Authoritative 200 MCO envelope, matching expected seller/buyer/site, documented structural identity/name/fiscal-address fields present |
| MISSING | Authoritative matching envelope has no buyer billing profile |
| UNAVAILABLE | 404 resource unavailable |
| UNKNOWN | Partial/header, identity/site mismatch, malformed/nullable required fields, unrecognized document type or unsupported site |

MCO structural policy follows the [official billing documentation](https://developers.mercadolibre.com.co/es_co/facturacion): buyer.cust_id is account association; identification is separate; fiscal address belongs to buyer.billing_info. Current supported identity types are CC/CE/NIT; required address presence covers street, number, city, state and CO country. COMPLETE is structural availability, not legal/tax validity, invoice eligibility or DIAN approval. Other sites remain UNKNOWN.

Tests prove missing tax number is not recovered from buyer ID, shipping address is never substituted, null stays unknown, and seller/buyer/site mismatches fail closed. No fiscal profile schema/persistence or fetching service was added. Existing invoicing export remains UNKNOWN without verified actual fiscal evidence; no readiness gate was falsely promoted.

## BILLING_PERIOD_DETAILS_READ

Existing `billing.period.details`, handler and normalizer unchanged. Existing tests prove 200 exact facts, from_id cursor, 206 no fact/cursor corruption and 429 one physical fake request with no inline retry. The new regression proves a nonzero already-established cursor, exact `90071992547409.1234`/COP and a separately queued existing Work continuation without inline page traversal. Existing Work/cooldown policy was not redesigned.

The first new broader focal run failed because its fixture used from_id=99 without seeding a matching durable period; existing lockPeriod correctly rejected it. The fixture was corrected by seeding cursor 99. No production Billing change; the failed log is retained and is not hidden as GREEN.

Billing CREDIT_NOTE reconciliation remains distinct from an Invoicing customer credit-note artifact. No shipment or fiscal-document-list consumer was added: both remain NOT_NEEDED.

## Financial freeze

Production Financial code unchanged. Five regression tests / 126 assertions PASS. Its read model remains PDO-only; projection does not change Work counts or write-command counts. Financial HTTP=0, Work=0, Queue=0. Fake Billing setup in regression fixtures is not Financial network activity.

## RED → GREEN and fresh QA

RED for actual missing operations/normalizers and seller/partial admission: 37 cases, 23 missing-method/operation errors and 13 expected assertion/exception failures. Minimal GREEN: 37 tests / 102 assertions.

| Check | Fresh result |
|---|---|
| F14 focal/regression | PASS — 117 tests / 839 assertions |
| Financial regression | PASS — 5 tests / 126 assertions |
| Full PHPUnit | PASS — 450 tests / 3,108 assertions / one existing skip / zero failures/errors |
| PHPStan | PASS — zero errors |
| Composer | PASS — validate --strict |
| PHP syntax | PASS — 163 files |
| Diff check | PASS |
| Architecture/HTTP guard receipts | PASS — no forbidden module/schema changes; real API/OAuth/refresh counters zero |

The existing Windows symlink-dependent DebugMaintenance case is skipped. Focal passed before Financial and full suite on ready local disposable `127.0.0.1:3307/erp_meli2_test`. Container stopped after QA. Previous transient Work root cause remains unproven; no such failure occurred in this run.

## Evidence, privacy and pending calibration

One [current registry](../storage/exports/f14/operation-registry.json), [current counters](../storage/exports/f14/real-http-counters.json) and [local QA receipt](../storage/exports/f14/local-contract/qa-summary.json). Current evidence is synthetic/local only; historical credential metadata was not reused and is excluded from this current package.

Mercado Libre API real GET=0, real POST=0, OAuth refresh=0, remote writes=0; Alegra/DIAN=0. Official documentation was consulted through documentation/search tooling only, not authenticated API traffic. No real token, provider payload or fiscal/address PII collected. Registry verified_at is a local/documentation contract date, not live calibration.

Functional commit: `fc281ee151ccf9f6f274a99785e4c6dc2d19bc14`. No push, merge or deploy. F15 Stage 0 must later obtain fresh ERP2 authorization for one seller and directly calibrate this contract. F15 was not executed; F16 remains UNAUTHORIZED.

STOP.

Current [F14 local evidence ZIP](../storage/exports/f14/F14_READ_CERTIFICATION_20261012.zip), SHA-256 `87739378c72472ca9832169f7fc0da88a231ac63f44c073d83552d2a8ccf6996`; 20 entries verified against SHA256 manifest/ZIP CRC. Local/untracked; no upload. Includes current RED/GREEN, corrected Billing fixture, fresh QA, registry/counters and functional patch; excludes historical source-credential evidence.
