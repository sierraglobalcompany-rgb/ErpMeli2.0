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
    'AUDIT_PARTIAL_206','BILLING_TWO_PAGES','BILLING_PARTIAL_HEADER','FIRST_CALL_429',
    'PACK_MATCHING_SELLER','PACK_MIXED_SELLER','PACK_PARTIAL_OR_MISSING',
    'BILLING_INFO_COMPLETE_MCO','BILLING_INFO_MISSING','BILLING_INFO_NULL',
    'BILLING_INFO_PARTIAL_OR_UNAVAILABLE','BILLING_INFO_UNAVAILABLE','OAUTH_CONNECT','OAUTH_REFRESH'];
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
$validLocalCallback = static function (string $uri): bool {
    $redirect = parse_url($uri);
    $host = is_array($redirect) ? ($redirect['host'] ?? null) : null;
    return is_array($redirect)
        && ($redirect['scheme'] ?? null) === 'http'
        && in_array($host, ['localhost','127.0.0.1'], true)
        && ($redirect['path'] ?? null) === '/oauth/mercadolibre/callback'
        && !isset($redirect['user'], $redirect['pass'], $redirect['query'], $redirect['fragment']);
};

if ($path === '/authorization') {
    if ($scenario !== 'OAUTH_CONNECT') {
        http_response_code(404);
        echo 'MLSIM_AUTH_NOT_AVAILABLE';
        exit;
    }

    $authorizationPath = $root . '/authorization.json';
    $challengePath = $root . '/pkce-challenge.txt';

    if ($method === 'GET') {
        $expectedKeys = ['client_id','code_challenge','code_challenge_method','redirect_uri','response_type','state'];
        $actualKeys = array_keys($_GET);
        sort($actualKeys);
        sort($expectedKeys);
        $clientId = is_string($_GET['client_id'] ?? null) ? trim($_GET['client_id']) : '';
        $redirectUri = is_string($_GET['redirect_uri'] ?? null) ? trim($_GET['redirect_uri']) : '';
        $state = is_string($_GET['state'] ?? null) ? trim($_GET['state']) : '';
        $challenge = is_string($_GET['code_challenge'] ?? null) ? trim($_GET['code_challenge']) : '';
        $valid = $actualKeys === $expectedKeys
            && ($_GET['response_type'] ?? null) === 'code'
            && $clientId !== '' && strlen($clientId) <= 191
            && $validLocalCallback($redirectUri)
            && preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $state) === 1
            && preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge) === 1
            && ($_GET['code_challenge_method'] ?? null) === 'S256';
        if (!$valid) {
            http_response_code(400);
            echo 'MLSIM_AUTH_INVALID';
            exit;
        }

        $approvalToken = bin2hex(random_bytes(16));
        $metadata = [
            'client_id'=>$clientId,
            'redirect_uri'=>$redirectUri,
            'state'=>$state,
            'code_challenge'=>$challenge,
            'approval_token_hash'=>hash('sha256', $approvalToken),
        ];
        $encoded = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($authorizationPath, $encoded, LOCK_EX) === false
            || file_put_contents($challengePath, $challenge, LOCK_EX) === false) {
            throw new RuntimeException('MLSIM_AUTH_WRITE_FAILED');
        }
        @chmod($authorizationPath, 0600);
        @chmod($challengePath, 0600);

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Mercado Libre simulado</title></head><body>'
            . '<main><h1>Cuenta simulada Mercado Libre</h1><p>Seller: 100</p><p>Site: MCO</p>'
            . '<form method="post" action="/authorization">'
            . '<input type="hidden" name="approval_token" value="' . htmlspecialchars($approvalToken, ENT_QUOTES, 'UTF-8') . '">'
            . '<button type="submit">Autorizar cuenta simulada</button></form></main></body></html>';
        exit;
    }

    if ($method === 'POST') {
        $token = is_string($_POST['approval_token'] ?? null) ? $_POST['approval_token'] : '';
        $raw = is_file($authorizationPath) && !is_link($authorizationPath) ? file_get_contents($authorizationPath) : false;
        $metadata = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        $valid = is_array($metadata)
            && count($_POST) === 1
            && preg_match('/^[a-f0-9]{32}$/D', $token) === 1
            && is_string($metadata['approval_token_hash'] ?? null)
            && hash_equals($metadata['approval_token_hash'], hash('sha256', $token))
            && is_string($metadata['redirect_uri'] ?? null)
            && is_string($metadata['state'] ?? null);
        if (!$valid) {
            http_response_code(400);
            echo 'MLSIM_AUTH_INVALID_APPROVAL';
            exit;
        }

        unlink($authorizationPath);
        $separator = str_contains($metadata['redirect_uri'], '?') ? '&' : '?';
        header('Location: ' . $metadata['redirect_uri'] . $separator . http_build_query([
            'code'=>'synthetic-one-use-code',
            'state'=>$metadata['state'],
        ], '', '&', PHP_QUERY_RFC3986));
        http_response_code(302);
        exit;
    }

    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$operation = 'UNKNOWN';
$keys = [];
if (is_string($path) && preg_match('#^/orders/[0-9]{1,32}$#D', $path)) {
    $operation = 'orders.get';
} elseif ($path === '/orders/search') {
    $operation = 'orders.search';
    $keys = ['seller','order_date_created_from','order_date_created_to','sort','offset','limit'];
} elseif (is_string($path) && preg_match('#^/packs/[1-9][0-9]{0,31}$#D', $path)) {
    $operation = 'packs.get';
} elseif (is_string($path) && preg_match('#^/orders/billing-info/[A-Z]{3}/[1-9][0-9]{0,31}$#D', $path)) {
    $operation = 'orders.billing_info';
} elseif ($path === '/oauth/token') {
    $operation = 'oauth.token';
} elseif ($path === '/users/me') {
    $operation = 'users.me';
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
    $stepIndex = $seq - 1;
    if ($scenario === 'OAUTH_CONNECT' && $steps !== []) {
        $stepIndex %= count($steps);
    }
    $step = $steps[$stepIndex] ?? null;
    $expectedQuery = is_array($step) ? ($step['query'] ?? []) : [];
    ksort($expectedQuery);
    $formValid = true;
    if ($operation === 'oauth.token') {
        $form = $_POST;
        $expectedForm = is_array($step) ? ($step['form'] ?? []) : [];
        if (($form['grant_type'] ?? null) === 'authorization_code') {
            $verifier = $form['code_verifier'] ?? null;
            $redirectUri = is_string($form['redirect_uri'] ?? null) ? trim($form['redirect_uri']) : '';
            $challengePath = $root . '/pkce-challenge.txt';
            $challenge = is_file($challengePath) && !is_link($challengePath) ? file_get_contents($challengePath) : false;
            $formValid = is_string($verifier) && preg_match('/^[A-Za-z0-9_-]{43}$/D', $verifier) === 1
                && is_string($challenge) && hash_equals($challenge, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='))
                && $validLocalCallback($redirectUri);
            unset($form['code_verifier'], $form['redirect_uri'], $expectedForm['redirect_uri']);
        }
        ksort($form); ksort($expectedForm);
        $formValid = $formValid && $form === $expectedForm
            && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/x-www-form-urlencoded');
    }
    $matched = is_array($step) && $method === ($step['method'] ?? 'GET') && $operation !== 'UNKNOWN'
        && $step['operation'] === $operation && $step['path'] === $path
        && !$unexpectedQuery && $query === $expectedQuery && $formValid;
    $status = $matched ? $step['status'] : 500;
    $record = ['seq'=>$seq,'method'=>$method,'operation'=>$operation,
        'path'=>$operation === 'UNKNOWN' ? 'UNKNOWN' : $path,'query'=>$query,
        'authorization_present'=>isset($_SERVER['HTTP_AUTHORIZATION']),
        'scenario'=>$scenario,'step'=>$stepIndex + 1,'status'=>$status,'matched'=>$matched];
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
