<?php

declare(strict_types=1);

namespace App\Modules\Invoicing;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

final class InvoicingBridge
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    /** @var list<string> */
    private const SCOPES = ['invoicing.read', 'invoicing.write'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $storageDirectory,
    ) {
    }

    /**
     * @param list<string> $scopes
     * @return array{id:int,key:string,scopes:list<string>}
     */
    public function issueServiceKey(int $companyId, array $scopes): array
    {
        if ($companyId < 1) {
            throw new RuntimeException('Company does not exist.');
        }

        $company = $this->pdo->prepare('SELECT id FROM companies WHERE id = :company_id');
        $company->execute(['company_id' => $companyId]);
        if ($company->fetchColumn() === false) {
            throw new RuntimeException('Company does not exist.');
        }

        $normalizedScopes = [];
        foreach (self::SCOPES as $scope) {
            if (in_array($scope, $scopes, true)) {
                $normalizedScopes[] = $scope;
            }
        }
        if ($normalizedScopes === [] || count($normalizedScopes) !== count(array_unique($scopes))) {
            throw new RuntimeException('Invalid invoicing key scopes.');
        }

        $key = 'erp2_inv_' . bin2hex(random_bytes(32));
        $insert = $this->pdo->prepare(
            'INSERT INTO invoicing_service_keys (company_id, key_hash, scopes) '
            . 'VALUES (:company_id, :key_hash, :scopes)',
        );
        $insert->execute([
            'company_id' => $companyId,
            'key_hash' => hash('sha256', $key),
            'scopes' => implode(',', $normalizedScopes),
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'key' => $key,
            'scopes' => $normalizedScopes,
        ];
    }

    public function revokeServiceKey(int $keyId): bool
    {
        if ($keyId < 1) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "UPDATE invoicing_service_keys SET status = 'revoked', revoked_at = UTC_TIMESTAMP(6) "
            . "WHERE id = :id AND status = 'active'",
        );
        $statement->execute(['id' => $keyId]);

        return $statement->rowCount() === 1;
    }

    /** @return array{company_id:int,scopes:list<string>}|null */
    public function resolveBearer(string $authorization): ?array
    {
        if (preg_match('/^Bearer (erp2_inv_[a-f0-9]{64})$/D', $authorization, $match) !== 1) {
            return null;
        }

        $keyHash = hash('sha256', $match[1]);
        $statement = $this->pdo->prepare(
            "SELECT company_id, key_hash, scopes FROM invoicing_service_keys "
            . "WHERE key_hash = :key_hash AND status = 'active' LIMIT 1",
        );
        $statement->execute(['key_hash' => $keyHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !hash_equals((string) $row['key_hash'], $keyHash)) {
            return null;
        }

        $scopes = array_values(array_filter(
            explode(',', (string) $row['scopes']),
            static fn (string $scope): bool => in_array($scope, self::SCOPES, true),
        ));

        return ['company_id' => (int) $row['company_id'], 'scopes' => $scopes];
    }

    public function accountBelongsToCompany(int $companyId, int $accountId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM meli_accounts WHERE company_id = :company_id AND id = :account_id LIMIT 1',
        );
        $statement->execute(['company_id' => $companyId, 'account_id' => $accountId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array{date_from:?string,date_to:?string,account_id:?int,status:?string,readiness:?string,limit:int,offset:int} $filters
     * @return array{data:list<array<string,mixed>>,pagination:array{limit:int,offset:int,has_more:bool}}
     */
    public function listSources(int $companyId, array $filters): array
    {
        $conditions = ['o.company_id = :company_id'];
        $parameters = ['company_id' => $companyId];

        if ($filters['date_from'] !== null) {
            $conditions[] = 'o.date_created >= :date_from';
            $parameters['date_from'] = $filters['date_from'] . ' 00:00:00.000000';
        }
        if ($filters['date_to'] !== null) {
            $conditions[] = 'o.date_created < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $parameters['date_to'] = $filters['date_to'] . ' 00:00:00.000000';
        }
        if ($filters['account_id'] !== null) {
            $conditions[] = 'o.account_id = :account_id';
            $parameters['account_id'] = $filters['account_id'];
        }
        if ($filters['status'] !== null) {
            $conditions[] = 'o.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if ($filters['readiness'] !== null) {
            $conditions[] = $this->readinessPredicate($filters['readiness']);
        }

        $sql = 'SELECT o.id AS local_order_id, o.external_order_id AS order_id, o.account_id, '
            . 'a.external_user_id AS account_external_user_id, a.site_id, o.pack_id, o.status, '
            . 'o.status_detail, o.date_created, o.date_closed, o.last_updated, o.total_amount, '
            . 'o.currency_id, o.buyer_id, ia.id AS invoice_artifact_id, ca.id AS credit_note_artifact_id '
            . 'FROM orders o JOIN meli_accounts a ON a.company_id = o.company_id AND a.id = o.account_id '
            . 'LEFT JOIN invoicing_artifacts ia ON ia.company_id = o.company_id '
            . "AND ia.account_id = o.account_id AND ia.source_order_id = o.id AND ia.document_kind = 'INVOICE' "
            . 'LEFT JOIN invoicing_artifacts ca ON ca.company_id = o.company_id '
            . "AND ca.account_id = o.account_id AND ca.source_order_id = o.id AND ca.document_kind = 'CREDIT_NOTE' "
            . 'WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY o.date_created ASC, o.id ASC LIMIT :row_limit OFFSET :row_offset';
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }
        $statement->bindValue(':row_limit', $filters['limit'] + 1, PDO::PARAM_INT);
        $statement->bindValue(':row_offset', $filters['offset'], PDO::PARAM_INT);
        $statement->execute();
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($orders) > $filters['limit'];
        if ($hasMore) {
            array_pop($orders);
        }

        $itemsByOrder = $this->itemsForOrders(array_map(
            static fn (array $order): int => (int) $order['local_order_id'],
            $orders,
        ));
        $data = [];
        foreach ($orders as $order) {
            $items = $itemsByOrder[(int) $order['local_order_id']] ?? [];
            $readiness = $this->readiness($order, $items);
            $invoiceArtifactPresent = $order['invoice_artifact_id'] !== null;
            $creditNoteArtifactPresent = $order['credit_note_artifact_id'] !== null;
            $data[] = [
                'local_order_id' => (int) $order['local_order_id'],
                'order_id' => (string) $order['order_id'],
                'pack_id' => $order['pack_id'] === null ? null : (string) $order['pack_id'],
                'account_id' => (int) $order['account_id'],
                'account_external_user_id' => (string) $order['account_external_user_id'],
                'site_id' => (string) $order['site_id'],
                'status' => (string) $order['status'],
                'status_detail' => $order['status_detail'] === null ? null : (string) $order['status_detail'],
                'date_created' => $order['date_created'] === null ? null : (string) $order['date_created'],
                'date_closed' => $order['date_closed'] === null ? null : (string) $order['date_closed'],
                'last_updated' => $order['last_updated'] === null ? null : (string) $order['last_updated'],
                'total_amount' => (string) $order['total_amount'],
                'currency_id' => (string) $order['currency_id'],
                'buyer_id' => $order['buyer_id'] === null ? null : (string) $order['buyer_id'],
                'buyer_id_is_fiscal_id' => false,
                'billing_data_status' => 'UNKNOWN',
                'billing_data_reason' => 'NO_LOCAL_FISCAL_DATA_SOURCE',
                'items' => $items,
                'readiness' => $readiness,
                'invoice_eligible' => $readiness === 'ORDER_READY',
                'cancelled' => $this->isCancelled((string) $order['status']),
                'post_sale_status' => $this->postSaleStatus(
                    (string) $order['status'],
                    $invoiceArtifactPresent,
                    $creditNoteArtifactPresent,
                ),
                'original_invoice_artifact_present' => $invoiceArtifactPresent,
                'credit_note_artifact_present' => $creditNoteArtifactPresent,
                'artifact_status' => $invoiceArtifactPresent ? 'ACCEPTED' : 'MISSING',
                'artifact_id' => $invoiceArtifactPresent ? (int) $order['invoice_artifact_id'] : null,
            ];
        }

        return [
            'data' => $data,
            'pagination' => [
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
                'has_more' => $hasMore,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $fields
     * @param array<string,UploadedFileInterface> $files
     * @return array{status:string,artifact_id?:int,pdf_sha256?:string,xml_sha256?:string|null}
     */
    public function acceptArtifact(
        int $companyId,
        string $externalOrderId,
        array $fields,
        array $files,
        string $documentKind = 'INVOICE',
    ): array
    {
        if (!in_array($documentKind, ['INVOICE', 'CREDIT_NOTE'], true)) {
            return ['status' => 'invalid_artifact'];
        }
        if (array_diff(array_keys($files), ['pdf', 'xml']) !== []) {
            return ['status' => 'invalid_artifact'];
        }
        $pdf = $this->readFile($files['pdf'] ?? null, 'pdf');
        if ($pdf === null) {
            return ['status' => 'invalid_artifact'];
        }
        $xml = array_key_exists('xml', $files) ? $this->readFile($files['xml'], 'xml') : null;
        if (array_key_exists('xml', $files) && $xml === null) {
            return ['status' => 'invalid_artifact'];
        }

        $metadata = $this->normalizeArtifactMetadata($fields, $documentKind);
        if ($metadata === null) {
            return ['status' => 'invalid_artifact'];
        }
        if (preg_match('/^[0-9]{1,32}$/D', $externalOrderId) !== 1) {
            return ['status' => 'source_not_found'];
        }

        $pdfHash = hash('sha256', $pdf);
        $xmlHash = $xml === null ? null : hash('sha256', $xml);
        $writtenPaths = [];
        $this->pdo->beginTransaction();
        try {
            $orderStatement = $this->pdo->prepare(
                'SELECT id, account_id, external_order_id, pack_id, status, date_created, total_amount, currency_id '
                . 'FROM orders WHERE company_id = :company_id AND external_order_id = :external_order_id '
                . 'ORDER BY id FOR UPDATE',
            );
            $orderStatement->execute([
                'company_id' => $companyId,
                'external_order_id' => $externalOrderId,
            ]);
            $matchingOrders = $orderStatement->fetchAll(PDO::FETCH_ASSOC);
            if ($matchingOrders === []) {
                $this->pdo->commit();
                return ['status' => 'source_not_found'];
            }
            if (count($matchingOrders) !== 1) {
                $this->pdo->commit();
                return ['status' => 'source_ambiguous'];
            }
            $order = $matchingOrders[0];
            if ($order['pack_id'] !== null) {
                $this->pdo->commit();
                return ['status' => 'pack_membership_unverified'];
            }

            $isCancelled = $this->isCancelled((string) $order['status']);
            if ($documentKind === 'INVOICE' && $isCancelled) {
                $this->pdo->commit();
                return ['status' => 'source_not_invoiceable'];
            }

            $originalInvoiceArtifactId = null;
            if ($documentKind === 'INVOICE') {
                $items = $this->itemsForOrders([(int) $order['id']])[(int) $order['id']] ?? [];
                if ($this->readiness($order, $items) !== 'ORDER_READY') {
                    $this->pdo->commit();
                    return ['status' => 'source_not_invoiceable'];
                }
                if (
                    $metadata['currency'] !== (string) $order['currency_id']
                    || $metadata['normalized_total'] !== $this->normalizeExactDecimal((string) $order['total_amount'])
                ) {
                    $this->pdo->commit();
                    return ['status' => 'source_total_mismatch'];
                }
            } else {
                if (!$isCancelled) {
                    $this->pdo->commit();
                    return ['status' => 'source_not_cancelled'];
                }

                $invoiceStatement = $this->pdo->prepare(
                    "SELECT id FROM invoicing_artifacts WHERE company_id = :company_id AND account_id = :account_id "
                    . "AND source_order_id = :source_order_id AND document_kind = 'INVOICE' LIMIT 1",
                );
                $invoiceStatement->execute([
                    'company_id' => $companyId,
                    'account_id' => (int) $order['account_id'],
                    'source_order_id' => (int) $order['id'],
                ]);
                $invoiceId = $invoiceStatement->fetchColumn();
                if ($invoiceId === false) {
                    $this->pdo->commit();
                    return ['status' => 'original_invoice_missing'];
                }
                $originalInvoiceArtifactId = (int) $invoiceId;
            }

            $existingStatement = $this->pdo->prepare(
                'SELECT id, document_number, issued_at, currency, exact_total, pdf_sha256, xml_sha256, cufe, provider_document_id '
                . 'FROM invoicing_artifacts WHERE company_id = :company_id AND account_id = :account_id '
                . 'AND source_order_id = :source_order_id AND document_kind = :document_kind LIMIT 1',
            );
            $existingStatement->execute([
                'company_id' => $companyId,
                'account_id' => (int) $order['account_id'],
                'source_order_id' => (int) $order['id'],
                'document_kind' => $documentKind,
            ]);
            $existing = $existingStatement->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                $same = (string) $existing['document_number'] === $metadata['document_number']
                    && (string) $existing['issued_at'] === $metadata['issued_at']
                    && (string) $existing['currency'] === $metadata['currency']
                    && $this->normalizeExactDecimal((string) $existing['exact_total']) === $metadata['normalized_total']
                    && hash_equals((string) $existing['pdf_sha256'], $pdfHash)
                    && ($existing['xml_sha256'] === null
                        ? $xmlHash === null
                        : $xmlHash !== null && hash_equals((string) $existing['xml_sha256'], $xmlHash))
                    && $existing['cufe'] === $metadata['cufe']
                    && $existing['provider_document_id'] === $metadata['provider_document_id'];
                $this->pdo->commit();

                return $same
                    ? [
                        'status' => 'existing',
                        'artifact_id' => (int) $existing['id'],
                        'pdf_sha256' => $pdfHash,
                        'xml_sha256' => $xmlHash,
                    ]
                    : ['status' => 'artifact_conflict'];
            }

            $companyIdFromOrder = $companyId;
            $accountId = (int) $order['account_id'];
            $sourceOrderId = (int) $order['id'];
            $pdfRelative = $this->artifactRelativePath($companyIdFromOrder, $accountId, $sourceOrderId, $pdfHash, 'pdf');
            if ($this->storeFile($pdfRelative, $pdf, $pdfHash)) {
                $writtenPaths[] = $this->absolutePath($pdfRelative);
            }
            $xmlRelative = null;
            if ($xml !== null) {
                $xmlRelative = $this->artifactRelativePath($companyIdFromOrder, $accountId, $sourceOrderId, $xmlHash, 'xml');
                if ($this->storeFile($xmlRelative, $xml, $xmlHash)) {
                    $writtenPaths[] = $this->absolutePath($xmlRelative);
                }
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO invoicing_artifacts '
                . '(company_id, account_id, source_order_id, document_kind, original_invoice_artifact_id, '
                . 'document_number, issued_at, currency, exact_total, channel, '
                . 'pdf_storage_ref, pdf_sha256, xml_storage_ref, xml_sha256, cufe, provider_document_id) '
                . "VALUES (:company_id, :account_id, :source_order_id, :document_kind, :original_invoice_artifact_id, "
                . ":document_number, :issued_at, :currency, :exact_total, 'WINDOWS_BRIDGE', "
                . ':pdf_storage_ref, :pdf_sha256, :xml_storage_ref, :xml_sha256, :cufe, :provider_document_id)',
            );
            $insert->execute([
                'company_id' => $companyIdFromOrder,
                'account_id' => $accountId,
                'source_order_id' => $sourceOrderId,
                'document_kind' => $documentKind,
                'original_invoice_artifact_id' => $originalInvoiceArtifactId,
                'document_number' => $metadata['document_number'],
                'issued_at' => $metadata['issued_at'],
                'currency' => $metadata['currency'],
                'exact_total' => $metadata['normalized_total'],
                'pdf_storage_ref' => $pdfRelative,
                'pdf_sha256' => $pdfHash,
                'xml_storage_ref' => $xmlRelative,
                'xml_sha256' => $xmlHash,
                'cufe' => $metadata['cufe'],
                'provider_document_id' => $metadata['provider_document_id'],
            ]);
            $artifactId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return [
                'status' => 'created',
                'artifact_id' => $artifactId,
                'pdf_sha256' => $pdfHash,
                'xml_sha256' => $xmlHash,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($writtenPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            throw $exception;
        }
    }

    /**
     * @param array<string,mixed> $order
     * @param list<array<string,string|null>> $items
     */
    private function readiness(array $order, array $items): string
    {
        if (($order['pack_id'] ?? null) !== null) {
            return 'PACK_MEMBERSHIP_UNVERIFIED';
        }
        if ($this->isCancelled((string) ($order['status'] ?? ''))) {
            return 'CANCELLED';
        }
        if (
            !is_string($order['date_created'] ?? null)
            || trim((string) ($order['external_order_id'] ?? $order['order_id'] ?? '')) === ''
            || trim((string) ($order['status'] ?? '')) === ''
            || trim((string) ($order['currency_id'] ?? '')) === ''
            || $items === []
        ) {
            return 'SOURCE_DATA_MISSING';
        }

        return 'ORDER_READY';
    }

    private function isCancelled(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['cancelled', 'canceled'], true);
    }

    private function postSaleStatus(string $status, bool $invoicePresent, bool $creditNotePresent): string
    {
        if (!$this->isCancelled($status)) {
            return 'NONE';
        }
        if (!$invoicePresent) {
            return 'CANCELLED_BEFORE_INVOICE';
        }

        return $creditNotePresent ? 'CREDIT_NOTE_RECORDED' : 'CREDIT_NOTE_REVIEW_REQUIRED';
    }

    private function readinessPredicate(string $readiness): string
    {
        $notCancelled = "LOWER(o.status) NOT IN ('cancelled', 'canceled')";
        $hasItems = 'EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id)';
        $sourceReady = 'o.date_created IS NOT NULL AND o.external_order_id <> \'\' '
            . 'AND o.status <> \'\' AND o.currency_id <> \'\' AND ' . $hasItems;

        return match ($readiness) {
            'ORDER_READY' => 'o.pack_id IS NULL AND ' . $notCancelled . ' AND ' . $sourceReady,
            'PACK_MEMBERSHIP_UNVERIFIED' => 'o.pack_id IS NOT NULL',
            'CANCELLED' => 'o.pack_id IS NULL AND LOWER(o.status) IN (\'cancelled\', \'canceled\')',
            'SOURCE_DATA_MISSING' => 'o.pack_id IS NULL AND ' . $notCancelled . ' AND NOT (' . $sourceReady . ')',
            'BILLING_DATA_MISSING' => '1 = 0',
            default => '1 = 0',
        };
    }

    /**
     * @param list<int> $orderIds
     * @return array<int,list<array<string,string|null>>>
     */
    private function itemsForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT order_id, external_item_id, variation_id, title, quantity, unit_price, currency_id, seller_sku '
            . 'FROM order_items WHERE order_id IN (' . $placeholders . ') ORDER BY order_id, id',
        );
        foreach ($orderIds as $index => $orderId) {
            $statement->bindValue($index + 1, $orderId, PDO::PARAM_INT);
        }
        $statement->execute();

        $itemsByOrder = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $orderId = (int) $item['order_id'];
            $itemsByOrder[$orderId][] = [
                'external_item_id' => (string) $item['external_item_id'],
                'variation_id' => $item['variation_id'] === null ? null : (string) $item['variation_id'],
                'title' => (string) $item['title'],
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'currency_id' => (string) $item['currency_id'],
                'seller_sku' => $item['seller_sku'] === null ? null : (string) $item['seller_sku'],
            ];
        }

        return $itemsByOrder;
    }

    /**
     * @param array<string,mixed> $fields
     * @return array{document_number:string,issued_at:string,currency:string,normalized_total:string,cufe:?string,provider_document_id:?string}|null
     */
    private function normalizeArtifactMetadata(array $fields, string $documentKind): ?array
    {
        $numberField = $documentKind === 'CREDIT_NOTE' ? 'credit_note_number' : 'invoice_number';
        $documentNumber = $fields[$numberField] ?? null;
        $issuedAtInput = $fields['issued_at'] ?? null;
        $currency = $fields['currency'] ?? null;
        $totalInput = $fields['exact_total'] ?? null;
        if (
            !is_string($documentNumber)
            || trim($documentNumber) === ''
            || mb_strlen($documentNumber) > 80
            || !is_string($issuedAtInput)
            || !is_string($currency)
            || trim($currency) === ''
            || mb_strlen($currency) > 8
            || !is_string($totalInput)
        ) {
            return null;
        }

        $issuedAt = $this->normalizeIssuedAt($issuedAtInput);
        $normalizedTotal = $this->normalizeExactDecimal($totalInput);
        $cufe = $this->optionalText($fields['cufe'] ?? null, 120);
        $providerDocumentId = $this->optionalText($fields['provider_document_id'] ?? null, 120);
        if ($issuedAt === null || $normalizedTotal === null || $cufe === false || $providerDocumentId === false) {
            return null;
        }

        return [
            'document_number' => trim($documentNumber),
            'issued_at' => $issuedAt,
            'currency' => trim($currency),
            'normalized_total' => $normalizedTotal,
            'cufe' => $cufe,
            'provider_document_id' => $providerDocumentId,
        ];
    }

    private function normalizeIssuedAt(string $value): ?string
    {
        if (preg_match(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-][0-9]{2}:[0-9]{2})$/D',
            $value,
        ) !== 1) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function normalizeExactDecimal(string $value): ?string
    {
        if (preg_match('/^([0-9]+)(?:\.([0-9]{1,4}))?$/D', $value, $match) !== 1) {
            return null;
        }
        $integer = ltrim($match[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 14) {
            return null;
        }

        return $integer . '.' . str_pad($match[2] ?? '', 4, '0');
    }

    /** @return string|false|null */
    private function optionalText(mixed $value, int $maxLength): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $maxLength) {
            return false;
        }

        return trim($value);
    }

    private function readFile(?UploadedFileInterface $file, string $kind): ?string
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            return null;
        }
        $size = $file->getSize();
        if ($size !== null && ($size < 1 || $size > self::MAX_FILE_BYTES)) {
            return null;
        }

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contents = $stream->getContents();
        if ($contents === '' || strlen($contents) > self::MAX_FILE_BYTES) {
            return null;
        }

        if ($kind === 'pdf' && !str_starts_with($contents, '%PDF-')) {
            return null;
        }
        if (
            $kind === 'xml'
            && (str_contains($contents, "\0")
                || preg_match('/^\s*(?:<\?xml\b[^>]*>\s*)?<[^\s!?][^>]*>/s', $contents) !== 1)
        ) {
            return null;
        }

        return $contents;
    }

    private function artifactRelativePath(int $companyId, int $accountId, int $orderId, string $hash, string $extension): string
    {
        return $companyId . '/' . $accountId . '/' . $orderId . '/' . $hash . '.' . $extension;
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->storageDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private function storeFile(string $relativePath, string $contents, string $expectedHash): bool
    {
        $root = rtrim($this->storageDirectory, DIRECTORY_SEPARATOR);
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('Invoice artifact storage is unavailable.');
        }
        if (is_link($root)) {
            throw new RuntimeException('Invoice artifact storage path is unsafe.');
        }
        $realRoot = realpath($root);
        if (!is_string($realRoot)) {
            throw new RuntimeException('Invoice artifact storage is unavailable.');
        }

        $path = $this->absolutePath($relativePath);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Invoice artifact storage is unavailable.');
        }
        if (is_link($directory) || is_link($path)) {
            throw new RuntimeException('Invoice artifact storage path is unsafe.');
        }
        $realDirectory = realpath($directory);
        if (
            !is_string($realDirectory)
            || !str_starts_with($realDirectory, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('Invoice artifact storage path is unsafe.');
        }
        if (is_file($path)) {
            $existingHash = hash_file('sha256', $path);
            if (is_string($existingHash) && hash_equals($expectedHash, $existingHash)) {
                return false;
            }
            throw new RuntimeException('Invoice artifact storage reference is inconsistent.');
        }

        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        $written = file_put_contents($temporary, $contents, LOCK_EX);
        if ($written !== strlen($contents) || !rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw new RuntimeException('Invoice artifact could not be persisted.');
        }

        return true;
    }
}
