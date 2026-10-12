# F14 — External READ certification

**PHASE_RESULT: BLOCKED**

**BLOCKER: NO_CURRENTLY_VALID_EXISTING_CERTIFICATION_ACCESS_TOKEN**

## KISS continuation

2026-10-12; branch `impl/v3-b-sales-audit-20261010`; exact HEAD before `38c6cb12e2779fcd356efc029668ce15ed567905`; clean tree, no functional drift. User explicitly authorized existing valid same-seller ERP1 access in source memory for <=8 GET, no refresh/retry. Final ERP2 OAuth is not required for F14 and belongs to F15; the previous target-runtime prerequisite is superseded. F13 not reopened.

## Real source credential metadata

Existing SSH alias/host key/native password prompt reused; exit 0. ERP1 root, VERSION/app.version 2.41.0, DB `u390570745_erpmeli`, latest migration `303_outer_cron_http_receipt.sql` reverified. READ ONLY transaction with tx_read_only=1. Exactly three metadata candidates; no encrypted/plain token value selected or decrypted. No bootstrap, refresh, worker or HTTP.

Source clock: `2026-10-12 14:43:58 UTC` (09:43:58 America/Bogota). Raw stored expiry values:

| Account | Company | Seller | Site | Token/account ownership | expires_at | Valid |
|---|---|---|---|---|---|---|
| A1 / 1 | C1 / 1 | 585784977 | MCO | 1 = 1 | 2026-10-07 02:49:09 | NO |
| A2 / 2 | C4 / 4 | 1797511852 | MCO | 2 = 2 | 2026-10-07 02:51:10 | NO |
| A3 / 3 | C5 / 5 | 175243724 | MCO | 3 = 3 | 2026-10-07 02:50:14 | NO |

All account statuses are conectado; encrypted-access presence is true. Neither means the token is current. Every expiry is five calendar days earlier than the captured source clock. Current refresh source hash differs from local staging, so current expiry storage timezone semantics are not claimed verified; even a conservative +/-24h timezone interpretation leaves all three expired. The collector's historical UTC-semantics note is not authority for current source code.

Valid candidates=0; selected account/token=NONE; token values read/decrypted/exported/persisted=NO. No live token probe. Markdown section 26 explicitly requires BLOCKED and STOP. No credential workaround or offline application patch was built after that gate.

## Registry, counters and architecture

Existing [operation registry](../storage/exports/f14/operation-registry.json): ORDER_READ, PACK_READ, BILLING_INFO_READ, BILLING_PERIOD_DETAILS_READ required; SHIPMENT_READ and FISCAL_DOCUMENT_LIST_READ NOT_NEEDED. All required live contracts remain uncertified. Order seller/206, pack and billing-info deterministic gaps remain pending, not marked PASS.

GET=POST=PUT=PATCH=DELETE=non_GET=OAuth_POST=automatic_retries=writes=0. Budget=8; calls_used=0. No status/error observations because no Mercado Libre API request occurred. ERP1/ERP2 production DML/DDL/application writes=0; remote files created=0. No token, Authorization/cookie, buyer fiscal/address values, raw provider payload or PDF/XML logged.

New classes/tables/queues/workers/OAuth services/refresh logic/retry framework=0. No application/config/test changes. Correctness and the explicit STOP gate take precedence over building a workaround.

## QA and evidence

Current standalone PHP syntax and PowerShell parser PASS; metadata/ownership/expiry and zero-counter checks PASS; docs diff check PASS. No full QA rerun after the explicit no-valid-token STOP gate. Prior F14 logs remain baseline only: focal 79 tests/744 assertions; full 412 tests/2,998 assertions/one skip/zero failures/errors; PHPStan, Composer and syntax 162 files PASS. They are not relabeled as a fresh continuation run.

## Final

CURRENT=F14 BLOCKED — NO_CURRENTLY_VALID_EXISTING_CERTIFICATION_ACCESS_TOKEN.
NEXT=F14 — resume with an authorized already-existing currently valid same-seller token; no refresh authorized in this phase.
F13=CLOSED; F15=NOT_STARTED; F16=UNAUTHORIZED. No push, merge or deploy.

STOP.

[Existing F14 evidence ZIP updated](../storage/exports/f14/F14_READ_CERTIFICATION_20261012.zip), SHA-256 `cdbd7605d89eece2231ee5931940d1a75a495d54fa1e0ad32a05e7a62ca65a00`; 17 entries verified against hashes/CRC. Local/untracked; no upload.
