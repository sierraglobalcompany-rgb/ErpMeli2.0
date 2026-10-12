# CURRENT CHECKPOINT — ERP MELI 2.0

**F14 BLOCKED — real company/account and valid existing READ credential context unavailable**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| F14 preflight HEAD | `e6da42159f88ddcab8fd56f98c44de97110d8138` — exact anchor, clean tree |
| F13 functional SHA | `e226263b9e3f842e5dbce0af15a83308465aab06` |
| F13 | CLOSED LOCAL — unchanged |
| CURRENT | F14 BLOCKED — live credential/account context unavailable |
| NEXT | F14 — configured real account context, offline contract gaps, then authorized minimum GET certification |
| Live READ authorization | EXPLICIT GET ONLY — persists; no repeat approval needed for the same scope |
| F15 | NOT_STARTED |
| F16 | UNAUTHORIZED |

## F14 blocked checkpoint

- [F14 report and registry](F14_READ_CERTIFICATION.md): ORDER_READ, PACK_READ, BILLING_INFO_READ and existing Billing period details are required; Shipment/fiscal-document-list NOT_NEEDED.
- No real ERP2 DB/account/OAuth context is configured in the accessible runtime. F13's REAUTHORIZE_NEW actions do not establish connected actual target rows. No test/seed/disposable identity was used as live authority; no ERP1 credentials were reused.
- Existing OAuth consumers may refresh with POST; the current GET-only authorization forbids that path. No token was read, logged, renewed or requested in chat. No production bootstrap/worker or remote script was run.
- Live API counters total=GET=POST=PUT=PATCH=DELETE=non_GET=writes=0. No Mercado Libre payload/PII/token export; Alegra/DIAN=0. No F15/F16, push, merge or deploy.
- Fresh local regression QA PASS: 79 focal tests/744 assertions; full 412 tests/2,998 assertions/one existing skip/zero failures or errors; PHPStan, Composer, syntax 162 files and diff check PASS. These results do not certify F14 live contracts.
- Unblock with the location/association of an existing configured certification runtime and valid credential via secure configuration; never paste secrets. F14 remains uncertified, including seller/206, pack ownership and billing-info contracts.

## F13 closure

- ERP1 reverified with existing SSH/host key/password prompt: VERSION/app.version 2.41.0, DB `u390570745_erpmeli`, MariaDB 11.8.9, latest migration 303. Two bounded READ ONLY sessions exit 0: mapping/code/known artifact paths, then required Catalog ID/company/SKU/name only. No full snapshot/profile redo; no remote temporary files or writes.
- Single local [mapping-manifest.json](../storage/exports/f13/mapping-manifest.json): C1/C3/C4/C5 CREATE_BEFORE_MIGRATION; C2 SKIP_HISTORICAL with zero required dependencies; A1/A2/A3 REAUTHORIZE_NEW under exact seller/company/site contracts. No real configured ERP2 target found; QA/demo/doc identities were excluded. Target absence is resolved creation, not BLOCKED.
- Catalog ownership PRESERVE_EXPLICIT_OWNER C3; A2/C4 is intentional provenance according to current production source code/schema. Do not merge C3/C4 or C3/C5 by names. Product links remain zero; 93 suggestions untouched.
- Catalog alone is MIGRATE: 100 original non-secret products. Existing Catalog repository plus local-only CLI; no schema/staging/service. Dry-run defaults to rollback on disposable DB, execute imported 100, repeat imported 0/already_present 100. All SKU/name fields exact; tenancy/idempotency PASS; no legacy numeric target IDs copied. No production execute path.
- Historical PDFs/XML are NOT_REQUIRED_FOR_CUTOVER. Narrow known-path search observed none, not an absolute hosting filesystem inventory. Existing F11/F12 artifact acceptance semantics remain unchanged.
- Fresh QA: 14 focal tests/33 assertions; full 412 tests/2,998 assertions/one existing symlink skip/zero failures or errors; PHPStan and Composer PASS; syntax 162 files PASS; diff check PASS. Earlier Work transient root cause remains unproven.
- [Report](F13_MIGRATION_REHEARSAL.md) and [verified local evidence ZIP](../storage/exports/f13/F13_MAPPING_REHEARSAL_20261012.zip). Previous sanitized snapshot ZIP remains unchanged. Raw mapping and minimal Catalog evidence stay local/untracked; no credentials, tokens or OAuth states.
- Production ERP1/ERP2 writes=0; application writes=0; remote files created=0; remote HTTP=0. No push/merge/deploy. Local disposable DB container stopped after QA/rehearsal.

## F13 remaining operational prerequisites

Before eventual production use, create the four mapped companies and reauthorize the three exact seller IDs. These are resolved cutover actions, not unresolved identity gaps. F14 certification and F16 remote-write authorization are separate gates. Unsupported legacy packs/payments/claims/links remain fail-closed without added owners.

## F12 closed contract

- Cancellation is derived from the synchronized local `orders.status` (`cancelled`/`canceled`). F11 already exports it.
- `GET /api/integrations/v1/invoicing/sources` exposes `post_sale_status`, `original_invoice_artifact_present`, and `credit_note_artifact_present` from local invoice/credit-note artifacts.
- States: active → `NONE`; cancelled without invoice artifact → `CANCELLED_BEFORE_INVOICE`; cancelled with invoice and without credit note → `CREDIT_NOTE_REVIEW_REQUIRED`; both stored → `CREDIT_NOTE_RECORDED`.
- The existing pre-release `invoicing_artifacts` table stores one `INVOICE` and one `CREDIT_NOTE` per source. `/credit-note` accepts an externally generated PDF and optional XML only for a cancelled non-pack source with its stored original invoice; the server links the note to that invoice and computes hashes. A different second note conflicts.
- Billing `document_type=CREDIT_NOTE` remains a Mercado Libre reconciliation stream and is never used as a customer invoice credit note. No automatic generation, legal/tax decision, DIAN call, or remote write was added. Packs remain blocked pending F14.
- DIAN's cancellation mechanism and Mercado Libre's attached-file DELETE distinction are documented in `docs/ERP2_AUTHORITY.md` with official sources.

## F12 verification

| Check | Result |
|---|---|
| F12 focal | PASS — 17 tests / 146 assertions |
| Full PHPUnit on implementation SHA above | PASS — 405 tests / 2976 assertions / 1 skipped / 0 failures / 0 errors |
| PHPStan | PASS — 0 errors |
| Composer validation | PASS — `composer validate --strict` |
| PHP syntax | PASS — 162 files |
| `REAL_MELI_HTTP` | `0` |
| `ALEGRA_REAL_HTTP` | `0` |
| `DIAN_HTTP` | `0` |
| Remote writes | OFF |

QA ran against fresh migrations in local MariaDB 11.4 on port 3307. No Mercado Libre, Alegra, or DIAN HTTP was made. The one skipped PHPUnit case is the existing platform-dependent symlink test.

## Gates

```text
REAL_MELI_HTTP=0
ALEGRA_REAL_HTTP=0
DIAN_HTTP=0
remote writes=OFF
F14=PENDING — certify pack membership, billing-info READ, and a complete local snapshot
F16=UNAUTHORIZED — remote fiscal-document writes remain prohibited
```

F12 does not start F13. F10 and F11 remain unchanged. Alegra remains deferred. `ORDER_READY` covers local source/pack safety and artifact acceptance, not fiscal identity or legal validity.

## Guardrails

No real remote HTTP, OAuth, production DB mutation, push, merge, deploy, pack reconstruction, Alegra call, F14 execution, or F16 execution. Remote fiscal writes remain OFF.

V4 evidence ZIP SHA-256: `ffb2a2574a9ed9ca01b07f03d659849d27771823f60336bb0ac05e85e92c07ec`; 37 entries verified against SHA256 manifest and ZIP CRC; local/untracked.
