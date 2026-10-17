---
name: erp-human-first-ui-ux
description: Mandatory Human-First UI/UX law for ERP design, implementation, review, accessibility, responsive behavior, information hierarchy, data-heavy screens and real-browser QA.
---

# ERP Human-First UI/UX — Mandatory Repository Skill

This is a thin activation adapter.

The canonical law is:

```text
docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md
```

Do not duplicate or replace that Master here.

## Mandatory activation

When a task changes any user-facing UI/UX, before changing production code:

1. Read `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`.
2. Read `AGENTS.md`.
3. Read `design-system/erp-meli-2-0/MASTER.md` if it exists.
4. Read `design-system/erp-meli-2-0/pages/<page>.md` if a page override exists.
5. Read `docs/CURRENT_CHECKPOINT.md`.
6. Verify backend truth, routes, permissions, capabilities and active scope/company.
7. Define one isolated UI microblock.
8. Do not invent data, routes, metrics, capabilities or permissions.
9. Apply Human-First, Decision-First, progressive disclosure and No-Infinite-Excel laws.
10. Preserve `UNKNOWN != 0`, `CONFLICT != 0`, and missing inventory != factual zero.
11. Run automated QA appropriate to the risk tier.
12. Run real-browser QA for user-facing changes before final GREEN.
13. Persist a checkpoint before opening the next UI block.

## Required handshake

The work log must establish:

```text
HUMAN_FIRST_LAW_LOADED=YES
CURRENT_CHECKPOINT_LOADED=YES
PROJECT_DESIGN_MASTER_LOADED=YES|NO
PAGE_OVERRIDE_LOADED=YES|NO|NOT_PRESENT
TRUTH_OWNER_VERIFIED=YES
AUTHORIZATION_MODEL_VERIFIED=YES
TARGET_VIEWPORTS_DEFINED=YES
UI_TASK_SCOPE=<one microblock>
```

If a required authority is missing:

```text
STOP
RESOLVE AUTHORITY FIRST
```

## Conflict rule

```text
TRUTH + SECURITY + ACCESSIBILITY > VISUAL PREFERENCE
```

Repository authority wins over stale chat memory, screenshots, old prompts or previous SHAs.

## Human-First gate

A UI change is not complete merely because it renders or passes unit tests.

Required when applicable:

```text
TRUTH=GREEN
AUTHORIZATION=GREEN
HUMAN_COMPREHENSION=GREEN
ACCESSIBILITY=GREEN
RESPONSIVE=GREEN
AUTOMATED_QA=GREEN
REAL_BROWSER_QA=GREEN
NO_INFINITE_EXCEL=GREEN
CHECKPOINT=PERSISTED
```
