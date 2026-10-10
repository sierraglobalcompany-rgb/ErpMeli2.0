# CURRENT CHECKPOINT — ERP MELI 2.0

**Date:** 2026-10-09  
**Branch:** `impl/v3-a-exact-boundary-20261009`  
**Base product SHA:** `4d144b9744160c3b1ddfa542af74040b262dec42`  
**Authority:** `docs/ERP2_AUTHORITY.md`  
**Plan:** `docs/superpowers/plans/2026-10-09-v3-a-exact-boundary-work-safety.md`

## Current state

- V3.2/KISS direction approved by user after repeated audits.
- Implementation branch intentionally starts from product base, not from the V3 forensic audit branch, to avoid importing superseded audit/addendum noise.
- Product code has not yet changed on this branch.
- F6A Task 2 remains blocked.
- Remote writes remain OFF.

## Last known green product gate

From product base `4d144b9744160c3b1ddfa542af74040b262dec42`:

```text
PHPSTAN=0
PHPUNIT=147/147 PASS
ASSERTIONS=970
REAL_MELI_HTTP=0
```

This evidence belongs to the base checkpoint and must not be claimed as fresh V3-A QA.

## Exact next task

```text
V3-A Task 1 — RED only
```

Modify the existing decimal precision test so the fake HTTP body contains literal unquoted JSON NUMBER values such as:

```text
90071992547409.1234
```

Add the focused MeliClient lossless-numbers RED test.

Expected result: current native `json_decode` path loses precision and the new focused test fails for the demonstrated reason.

## Do not do yet

- no GREEN decoder before RED is observed;
- no Sales historical code;
- no Billing handler;
- no merge;
- no deploy;
- no real ML HTTP;
- no remote writes.
