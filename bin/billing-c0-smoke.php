<?php

declare(strict_types=1);

$appEnv = getenv('APP_ENV');
if ($appEnv !== 'production') {
    fwrite(STDERR, "Billing C0 requires APP_ENV=production.\n");
    exit(2);
}

if (getenv('BILLING_C0_REAL_HTTP') !== '1') {
    fwrite(STDERR, "Billing C0 requires BILLING_C0_REAL_HTTP=1.\n");
    exit(2);
}

fwrite(STDERR, "Billing C0 runtime is not configured yet.\n");
exit(2);
