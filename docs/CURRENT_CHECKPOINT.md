# CURRENT CHECKPOINT — ERP MELI 2.0

**F15 STAGE0 BLOCKED — DEDICATED_ERP2_MELI_APP_NOT_CONFIGURED**

- Authorized Stage 0 started from exact `6fd1a7af57a04be5d993e3542d1f164ea8d91601`, branch `impl/v3-b-sales-audit-20261010`, clean tree. No functional changes, deploy, push or merge.
- Existing SSH alias `liquidapro-hostinger` verified locally against its existing known host key; interactive authentication completed, SSH exit 0. Read-only ERP2 inventory captured at `2026-10-12T16:18:18+00:00`.
- Exact target realpath: `/home/u390570745/domains/bodegadigitalmedellin.com/public_html/erpmeli2`. Nonempty placeholder only (`default.php`); no ERP2 composer/autoload/index/route markers. This proves the filesystem path, not the HTTPS document-root binding or an installed ERP2 release.
- HTTPS HEAD returned 200, advertised PHP 8.3.35; CLI PHP 8.3.35 and all ten Composer-required extensions present. Version meets current Composer `>=8.3 <8.6`; PHP 8.5 itself is not established. HTTPS 200 is not an ERP2 smoke PASS. Callback reachability and Composer executable not established.
- No target `.env`, `config.env` or `shared/config.env`; no dedicated app credentials or APP_KEY in those locations. DB configuration/access mechanism unknown, not proof that a hosting database does not exist. No DB connection attempted. Dedicated app absent from examined ERP2 configuration, not a claim about the user's DevCenter inventory.
- Mandatory blocker: `DEDICATED_ERP2_MELI_APP_NOT_CONFIGURED`. Stop before deployment, database/company/account mutation or OAuth. Target nonempty: backup remains NOT_STARTED; mandatory before any subsequent mutation. No backup claimed.
- DevCenter prerequisite: a dedicated ERP2 Mercado Libre application for Colombia/MCO, business unit Mercado Libre, callback `https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback`, authorization-code flow with PKCE S256. Required functional access: Users READ; sales/orders/packs/buyer billing-info READ; billing/invoices reports READ. Scope `read`, and `offline_access` for the initial token pair required by the current connect service; no `write` or Mercado Pago scopes. Exact DevCenter permission labels must be verified against the current UI, not invented. No secrets in chat.
- Existing local routes verified: `/oauth/mercadolibre/connect` requires an authenticated company admin; `/oauth/mercadolibre/callback` checks session-bound state/PKCE. The connect service already performs a single users/me GET after the code exchange: count it inside the total <=8 budget on resume. Current generic connect does not enforce the Stage 0 seller allowlist before persistence; establish a safe existing operational guard or STOP before authorization. No production coding attempted.
- OAuth code exchange/refresh=0; all Mercado Libre GET and other methods=0; automatic retries=0; Financial HTTP/Work=0. No ERP1 execution/config/credentials/tokens used. No raw payload, PII or secrets emitted.
- No cron/Work/background enabled by this run. Pre-existing host cron/background state remains UNVERIFIED; do not claim a complete runtime OFF audit. No company/account created, Work growth not measured.
- Frozen release QA remains 453 tests / 3171 assertions / one skip / zero failures or errors. No new destructive QA: functional HEAD unchanged. Inventory PHP syntax and PowerShell parser PASS.
- Local sanitized evidence: `storage/exports/f15/stage0/inventory-output.txt`, `ssh-exit-code.txt`, `transcript.txt`, collector and validated interactive wrapper. Outputs remain ignored/untracked, no upload.
- CURRENT=F15 STAGE0 BLOCKED; F14=LOCAL CONTRACT READY / LIVE PENDING; NEXT=configure dedicated ERP2 app securely and resume authorized Stage 0 after remaining target/runtime prerequisites. F15 Stage 1 NOT_STARTED; F16 UNAUTHORIZED.

STOP — no calibration or later stage executed.

## Previous completed checkpoint

**KISS-S1 CLOSED — Billing same-Work pagination**

| Field | Value |
|---|---|
| Date | 2026-10-12 |
| Branch | `impl/v3-b-sales-audit-20261010` |
| HEAD before | `4d1330bdcfacf100292d5e632841f4eeb2324d3a` — exact anchor, clean tree |
| Functional SHA | `509b7343824d46a97e37cb348e1870447eac291f` |
| CURRENT | KISS-S1 CLOSED — Billing same-Work pagination |
| F14 | LOCAL CONTRACT READY |
| LIVE_READ_CERTIFICATION | PENDING_F15_STAGE0 |
| NEXT | F15 STAGE0 — one seller fresh ERP2 OAuth + direct live calibration |
| F15 | NOT_STARTED |
| F16 | UNAUTHORIZED |

## KISS-S1 receipt

- BEFORE: page 1 produced two Work rows (completed parent + child). AFTER: one original Work remains, pending with cleared claim; next claim reuses its ID and original dedupe. New child continuation removed.
- One logical page per claim; no Billing inline loop. Existing bounded 401 refresh/resource retry exception is unchanged. 429 one physical request and zero inline retry; 206 no partial facts/cursor mutation.
- Existing `completeCurrentClaim()` gained one optional continuation timestamp. Its current claim row lock and transaction now contain page facts + Billing cursor + JSON_SET of the existing Work `from_id` dispatch hint + existing `deferCurrentClaim()` transition. Stale tokens cannot invoke the callback. The cursor remains Billing-owned; no new state field or queue topology.
- Successful nonterminal claim increments attempts then returns that increment: net 0; earlier genuine failure budget is preserved. Normal `available_at` = caller execution `now` +1 second, UTC-normalized; existing eligibility handles pacing. No scheduler. Terminal completes the same Work with existing completion semantics.
- Forced defer-update SQL failure rolls back facts, period/cursor, payload and claim. Test-only trigger is removed; no production fault injection/migration.
- Production scope: BillingPeriodSyncHandler and WorkRepository only. Initial producers, generic runner, Sales, OAuth and Financial unchanged. New classes/tables/migrations/queues/workers/schedulers/dependencies/HTTP operations/retry frameworks =0.
- Fresh QA PASS: Billing 36 tests/479 assertions; Work 31/246; Financial 5/126; full 453/3,171/one existing symlink skip/zero failures/errors; PHPStan and Composer PASS; PHP syntax 163 files; diff check PASS. RED 4 expected failures; early GREEN fixture omissions/timezone assertions corrected and logs retained.
- Financial HTTP/Work/Queue=0. Real API GET/POST, Alegra/DIAN and remote writes=0. Local fake transports and ready disposable MariaDB only; container stopped after QA. No SSH/ERP1 credential use, F15 execution, push, merge or deploy.
- [Current pagination authority](ERP2_AUTHORITY.md) and [compact architecture receipt](../storage/exports/kiss-s1/architecture-receipt.json). Existing F14 report/evidence remains the completed local-contract baseline; current pagination authority is KISS-S1.

## Upstream contracts remain closed

- [F13 report](F13_MIGRATION_REHEARSAL.md): explicit mapping complete, C3 Catalog ownership preserved, 100 products rehearsed locally. No production migration or target OAuth executed.
- [F14 local report](F14_READ_CERTIFICATION.md): deterministic READ contracts ready; live calibration remains pending fresh ERP2 OAuth in a separately authorized F15 Stage 0 task.
- F12 linked cancellation/invoice/credit-note contracts and Financial stay frozen. Remote fiscal writes remain OFF; F16 requires separate explicit authorization.

STOP — KISS-S1 only; F15/F16 not executed.

[Verified KISS-S1 local evidence ZIP](../storage/exports/kiss-s1/KISS_S1_BILLING_SAME_WORK_20261012.zip), SHA-256 `b71c71c28d376c4a6045436682aec78b086079d2be0099897040a4cc5aa62c9f`; 13 entries verified against manifest/ZIP CRC. Local/untracked; no upload.
