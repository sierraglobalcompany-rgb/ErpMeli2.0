# Hostinger runtime evidence — ERP Meli 2.0

**Cutoff:** 2026-10-08

This document separates public Hostinger capabilities from account-specific facts that still require hPanel/runtime evidence.

## Confirmed from current official Hostinger documentation

### PHP 8.5

Hostinger currently allows PHP versions up to PHP 8.5 for supported web-hosting sites.

Sources:

- https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/
- https://www.hostinger.com/support/4047803-how-to-change-the-php-version-for-subfolders-or-subdomains-in-hostinger/

**Project consequence:** PHP 8.5 is a valid target, but it still must be enabled and verified for the actual ERP2 website and CLI/runtime used by cron.

### Cron capacity

Hostinger currently documents:

- Single hosting: maximum 2 cron jobs;
- Premium and above: unlimited cron jobs.

Source:

- https://www.hostinger.com/support/1583765-how-many-cron-jobs-can-you-set-up-in-hostinger/

**Project consequence:** ERP2 needs only two cron entries by default (`work` and `cleanup`), so even the documented Single limit is structurally sufficient. The actual account still needs its minimum supported interval confirmed.

### Hosting quotas vary by plan/version

Hostinger publishes CPU/RAM/disk/inode/database limits, but also documents multiple plan-limit versions based on purchase date. Therefore a public Business/Premium table is **not account-specific proof** of the ERP2 quotas.

Sources:

- https://www.hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/
- https://www.hostinger.com/support/10717644-new-web-and-cloud-hosting-limits-at-hostinger/
- https://www.hostinger.com/support/1583210-what-is-the-inode-limit-and-what-happens-when-it-is-reached/

**Project consequence:** do not freeze DB/storage/debug limits from a marketing/support table. Read the actual account values in hPanel.

## Account-specific facts still required

Run:

```bash
php bin/runtime-preflight.php
```

on the real ERP2 Hostinger runtime and record its non-secret output.

Then confirm in hPanel:

```text
HOST_DISK_QUOTA
HOST_DB_QUOTA
HOST_INODE_QUOTA
HOST_CRON_CAPACITY
HOST_MIN_CRON_INTERVAL
WEB_PHP_VERSION
CLI_PHP_VERSION
HTTPS_STATUS
```

## Gate rule

Public documentation proves platform capability, not the account-specific configuration.

Therefore:

```text
official Hostinger docs + CI green != HOSTINGER_PREFLIGHT_PASS
```

F0 closes only after the actual runtime and hPanel values are recorded.

## KISS consequence

The architecture continues to assume only:

```text
2 cron jobs maximum needed by ERP2 V1
1 MariaDB database
PHP 8.5
bounded filesystem storage
```

If the actual account cannot meet those minimal requirements, change the environment or deployment configuration before adding application complexity. Do not create application-side workarounds for hosting-plan constraints.
