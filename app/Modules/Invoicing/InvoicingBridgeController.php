<?php

declare(strict_types=1);

namespace App\Modules\Invoicing;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class InvoicingBridgeController
{
    private const DEFAULT_PAGE_SIZE = 50;

    private const MAX_PAGE_SIZE = 100;

    private const MAX_OFFSET = 100_000;

    private readonly InvoicingBridge $bridge;

    public function __construct(PDO $pdo, string $storageDirectory)
    {
        $this->bridge = new InvoicingBridge($pdo, $storageDirectory);
    }

    public function listSources(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $credential = $this->authorize($request, $response, 'invoicing.read');
        if ($credential instanceof ResponseInterface) {
            return $credential;
        }

        $query = $request->getQueryParams();
        $dateFrom = $this->dateFilter($query['date_from'] ?? null);
        $dateTo = $this->dateFilter($query['date_to'] ?? null);
        $accountId = $this->positiveInteger($query['account_id'] ?? null);
        $status = $query['status'] ?? null;
        $readiness = $query['readiness'] ?? null;
        $limit = $this->positiveInteger($query['limit'] ?? self::DEFAULT_PAGE_SIZE);
        $offset = $this->nonNegativeInteger($query['offset'] ?? 0);

        if (
            $dateFrom === false
            || $dateTo === false
            || ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo)
            || (($query['account_id'] ?? null) !== null && $accountId === null)
            || (($query['status'] ?? null) !== null && (!is_string($status) || trim($status) === '' || mb_strlen($status) > 40))
            || (($query['readiness'] ?? null) !== null && (!is_string($readiness) || !in_array($readiness, [
                'ORDER_READY',
                'PACK_MEMBERSHIP_UNVERIFIED',
                'BILLING_DATA_MISSING',
                'CANCELLED',
                'SOURCE_DATA_MISSING',
            ], true)))
            || $limit === null
            || $limit > self::MAX_PAGE_SIZE
            || $offset === null
            || $offset > self::MAX_OFFSET
        ) {
            return $this->error($response, 422, 'invalid_filter');
        }
        if ($accountId !== null && !$this->bridge->accountBelongsToCompany($credential['company_id'], $accountId)) {
            return $this->error($response, 404, 'account_not_found');
        }

        return $this->json($response, 200, $this->bridge->listSources($credential['company_id'], [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_id' => $accountId,
            'status' => is_string($status) ? trim($status) : null,
            'readiness' => is_string($readiness) ? $readiness : null,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    public function acceptArtifact(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $externalOrderId,
    ): ResponseInterface {
        $credential = $this->authorize($request, $response, 'invoicing.write');
        if ($credential instanceof ResponseInterface) {
            return $credential;
        }

        $fields = $request->getParsedBody();
        $fields = is_array($fields) ? $fields : [];
        $files = [];
        foreach ($request->getUploadedFiles() as $name => $file) {
            if ($file instanceof UploadedFileInterface) {
                $files[(string) $name] = $file;
            }
        }

        $result = $this->bridge->acceptArtifact(
            $credential['company_id'],
            $externalOrderId,
            $fields,
            $files,
        );

        if ($result['status'] === 'created' || $result['status'] === 'existing') {
            return $this->json($response, $result['status'] === 'created' ? 201 : 200, [
                'artifact_id' => $result['artifact_id'],
                'artifact_status' => 'ACCEPTED',
                'pdf_sha256' => $result['pdf_sha256'],
                'xml_sha256' => $result['xml_sha256'],
            ]);
        }

        return match ($result['status']) {
            'source_not_found' => $this->error($response, 404, 'source_not_found'),
            'source_ambiguous' => $this->error($response, 409, 'source_ambiguous'),
            'pack_membership_unverified' => $this->error($response, 409, 'pack_membership_unverified'),
            'source_total_mismatch' => $this->error($response, 409, 'source_total_mismatch'),
            'artifact_conflict' => $this->error($response, 409, 'artifact_conflict'),
            'source_not_invoiceable' => $this->error($response, 409, 'source_not_invoiceable'),
            default => $this->error($response, 422, 'invalid_artifact'),
        };
    }

    /** @return array{company_id:int,scopes:list<string>}|ResponseInterface */
    private function authorize(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $requiredScope,
    ): array|ResponseInterface {
        $credential = $this->bridge->resolveBearer($request->getHeaderLine('Authorization'));
        if ($credential === null) {
            return $this->error($response, 401, 'invalid_api_key');
        }
        if (!in_array($requiredScope, $credential['scopes'], true)) {
            return $this->error($response, 403, 'insufficient_scope');
        }

        return $credential;
    }

    private function dateFilter(mixed $value): string|false|null
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $match) !== 1) {
            return false;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1]) ? $value : false;
    }

    private function positiveInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($validated) ? $validated : null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return is_int($validated) ? $validated : null;
    }

    /** @param array<string,mixed> $payload */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    private function error(ResponseInterface $response, int $status, string $code): ResponseInterface
    {
        return $this->json($response, $status, ['error' => $code]);
    }
}
