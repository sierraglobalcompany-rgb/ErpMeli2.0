<?php

declare(strict_types=1);

// Test-only synthetic HTTP responses. No ERP bootstrap, database, proxy or outbound transport.
if (getenv('APP_ENV') !== 'test') {
    http_response_code(403);
    echo 'MLSIM_TEST_ONLY';
    exit(2);
}

$root = getenv('MLSIM_ROOT');
$scenario = getenv('MLSIM_SCENARIO');
$allowed = ['ORDER_HAPPY_EXACT','ORDER_PARTIAL_HEADER','INVALID_JSON','AUDIT_REPAIR_1',
    'AUDIT_PARTIAL_206','BILLING_TWO_PAGES','BILLING_PARTIAL_HEADER','FIRST_CALL_429'];
if (!is_string($root) || realpath($root) === false || is_link($root)
    || str_replace('\\', '/', $root) !== str_replace('\\', '/', realpath($root))
    || realpath(dirname($root)) !== realpath(sys_get_temp_dir())
    || preg_match('/^erp-meli2-mlsim-[a-f0-9]{16}$/D', basename($root)) !== 1
    || !in_array($scenario, $allowed, true) || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403);
    echo 'MLSIM_INVALID_RUN';
    exit(2);
}

$tracePath = $root . '/requests.jsonl';
if (!is_file($tracePath) || is_link($tracePath) || realpath(dirname($tracePath)) !== realpath($root)) {
    http_response_code(403);
    echo 'MLSIM_INVALID_TRACE';
    exit(2);
}

$bundle = json_decode((string) file_get_contents(__DIR__ . '/scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
$steps = $bundle[$scenario] ?? [];
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$operation = 'UNKNOWN';
$keys = [];
if (is_string($path) && preg_match('#^/orders/[0-9]{1,32}$#D', $path)) {
    $operation = 'orders.get';
} elseif ($path === '/orders/search') {
    $operation = 'orders.search';
    $keys = ['seller','order_date_created_from','order_date_created_to','sort','offset','limit'];
} elseif (is_string($path) && preg_match('#^/billing/integration/periods/key/[0-9]{4}-[0-9]{2}-01/group/ML/details$#D', $path)) {
    $operation = 'billing.period.details';
    $keys = ['document_type','limit','from_id','sort_by','order_by'];
}

// PHP normalizes dots in query keys. Match the same representation on both sides.
$query = [];
foreach ($keys as $key) {
    if (isset($_GET[$key]) && is_string($_GET[$key])) { $query[$key] = $_GET[$key]; }
}
ksort($query);
$unexpectedQuery = count($query) !== count($_GET);
$handle = fopen($tracePath, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) { throw new RuntimeException('MLSIM_TRACE_FAILED'); }
try {
    $seq = 1;
    while (($line = fgets($handle)) !== false) {
        if (trim($line) !== '') { $seq++; }
    }
    $step = $steps[$seq - 1] ?? null;
    $expectedQuery = is_array($step) ? ($step['query'] ?? []) : [];
    ksort($expectedQuery);
    $matched = is_array($step) && $method === 'GET' && $operation !== 'UNKNOWN'
        && $step['operation'] === $operation && $step['path'] === $path
        && !$unexpectedQuery && $query === $expectedQuery;
    $status = $matched ? $step['status'] : 500;
    $record = ['seq'=>$seq,'method'=>$method,'operation'=>$operation,
        'path'=>$operation === 'UNKNOWN' ? 'UNKNOWN' : $path,'query'=>$query,
        'authorization_present'=>isset($_SERVER['HTTP_AUTHORIZATION']),
        'scenario'=>$scenario,'step'=>$seq,'status'=>$status,'matched'=>$matched];
    $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    if (fwrite($handle, $line) !== strlen($line) || !fflush($handle)) {
        throw new RuntimeException('MLSIM_TRACE_WRITE_FAILED');
    }
} finally {
    flock($handle, LOCK_UN);
    fclose($handle);
}

http_response_code($status);
header('Content-Type: application/json');
if ($matched) {
    foreach ($step['headers'] as $name => $value) { header($name . ': ' . $value); }
    echo $step['body']; // Literal provider body: never round-trip money through float.
} else {
    echo '{"error":"mlsim_unexpected_request"}';
}
