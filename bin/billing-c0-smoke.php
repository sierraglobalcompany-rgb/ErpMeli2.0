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

$options = getopt('', [
    'account-id:',
    'period:',
    'document-type:',
    'max-pages:',
]);

$accountId = $options['account-id'] ?? null;
if (!is_string($accountId) || preg_match('/^[0-9]+$/D', $accountId) !== 1 || (int) $accountId < 1) {
    fwrite(STDERR, "Billing C0 requires --account-id as a positive integer.\n");
    exit(2);
}

$period = $options['period'] ?? null;
$periodMatch = is_string($period) && preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])-01$/D', $period, $periodParts) === 1;
if (!$periodMatch || !checkdate((int) $periodParts[2], 1, (int) $periodParts[1])) {
    fwrite(STDERR, "Billing C0 requires --period=YYYY-MM-01.\n");
    exit(2);
}

$documentType = $options['document-type'] ?? null;
if (!is_string($documentType) || !in_array($documentType, ['BILL', 'CREDIT_NOTE'], true)) {
    fwrite(STDERR, "Billing C0 requires --document-type=BILL|CREDIT_NOTE.\n");
    exit(2);
}

$maxPages = $options['max-pages'] ?? null;
$maxPagesValue = is_string($maxPages)
    ? filter_var($maxPages, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]])
    : false;
if ($maxPagesValue === false) {
    fwrite(STDERR, "Billing C0 requires --max-pages between 1 and 20.\n");
    exit(2);
}

fwrite(STDERR, "Billing C0 runtime is not configured yet.\n");
exit(2);
