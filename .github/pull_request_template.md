# Pull Request

## Scope

Describe the root cause and the smallest intended change.

```text
MICROBLOCK=
ROOT_CAUSE=
OUT_OF_SCOPE=
```

## KISS / Noise / Patch Gate

- [ ] This is the smallest correct solution.
- [ ] No duplicate owner/source-of-truth was introduced.
- [ ] No speculative architecture was added.
- [ ] Replaced/dead code or documentation was reviewed for removal.
- [ ] No patch-on-patch chain was introduced.

## Human-First UI/UX Gate

Complete this section when the PR changes user-facing UI/UX. Otherwise mark `NOT_APPLICABLE`.

```text
HUMAN_FIRST_UI_CHANGE=YES|NO
```

- [ ] Read `docs/ERP_HUMAN_FIRST_UI_UX_MASTER_SKILL.md`.
- [ ] Read `design-system/erp-meli-2-0/MASTER.md`.
- [ ] Read page override or confirmed none exists.
- [ ] Read `docs/CURRENT_CHECKPOINT.md`.
- [ ] Truth owner verified.
- [ ] Authorization/scope verified.
- [ ] Primary human job/action defined.
- [ ] Raw machine language is not dominant.
- [ ] No unnecessary table / empty table chrome.
- [ ] `UNKNOWN != 0`, `CONFLICT != 0`, missing inventory != factual zero.
- [ ] Mobile/tablet/desktop behavior defined for the scope.
- [ ] Keyboard/focus verified when interactive.
- [ ] Accessibility reviewed.
- [ ] No primary mobile page horizontal scroll.
- [ ] Automated QA GREEN.
- [ ] Real-browser QA GREEN when user-facing behavior changed.
- [ ] Evidence/screenshots referenced when appropriate.
- [ ] Checkpoint persisted before opening next UI block.

## Truth / Safety

```text
REMOTE_WRITES=
REAL_MELI_HTTP=
MIGRATIONS=
NEW_DEPENDENCIES=
```

- [ ] No secrets/tokens are rendered or committed.
- [ ] No real MELI mutation unless explicitly authorized.
- [ ] Money remains exact; no float-based monetary truth.
- [ ] Company/account scope remains fail-closed.

## QA

```text
RED=
GREEN=
FULL_QA=
BROWSER_QA=
VIEWPORTS=
```

## Findings / Limitations

```text
KNOWN_LIMITATIONS=
DEFERRED=
```

## Checkpoint

```text
BRANCH=
HEAD=
CI_RUN=
NEXT=
RESUME_EXACTLY_FROM=
```
