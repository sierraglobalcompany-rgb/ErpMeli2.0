<?php

declare(strict_types=1);

namespace App\Modules\Api;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Modules\Catalog\ProductRepository;
use App\Modules\Inventory\InventoryRepository;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ErpApiController
{
    private readonly ProductRepository $products;

    private readonly InventoryRepository $inventory;

    public function __construct(private readonly PDO $pdo)
    {
        $this->products = new ProductRepository($pdo);
        $this->inventory = new InventoryRepository($pdo);
    }

    public function createProduct(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $companyId = $this->authorizedCompanyId();
        if ($companyId === null) {
            return $this->error($response, 403, 'forbidden');
        }
        if (!$this->hasValidCsrf($request)) {
            return $this->error($response, 419, 'invalid_csrf');
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->error($response, 400, 'invalid_body');
        }

        $internalSku = $body['internal_sku'] ?? null;
        $name = $body['name'] ?? null;
        if (!is_string($internalSku) || !is_string($name)) {
            return $this->error($response, 422, 'invalid_product');
        }

        try {
            $productId = $this->products->create($companyId, $internalSku, $name);
        } catch (InvalidArgumentException) {
            return $this->error($response, 422, 'invalid_product');
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                return $this->error($response, 409, 'duplicate_sku');
            }
            if ($exception->getCode() === '22001') {
                return $this->error($response, 422, 'invalid_product');
            }

            throw $exception;
        }

        return $this->json($response, 201, [
            'id' => $productId,
            'internal_sku' => $internalSku,
            'name' => $name,
        ])->withHeader('Location', '/api/products/' . $productId);
    }

    public function readProduct(
        ResponseInterface $response,
        string $productIdInput,
    ): ResponseInterface {
        $companyId = $this->authorizedCompanyId();
        if ($companyId === null) {
            return $this->error($response, 403, 'forbidden');
        }

        $productId = $this->positiveId($productIdInput);
        if ($productId === null) {
            return $this->error($response, 404, 'not_found');
        }

        $product = $this->products->findForCompany($companyId, $productId);
        if ($product === null) {
            return $this->error($response, 404, 'not_found');
        }

        return $this->json($response, 200, [
            'id' => $product['id'],
            'internal_sku' => $product['internal_sku'],
            'name' => $product['name'],
        ]);
    }

    public function readInventory(
        ResponseInterface $response,
        string $productIdInput,
    ): ResponseInterface {
        $companyId = $this->authorizedCompanyId();
        if ($companyId === null) {
            return $this->error($response, 403, 'forbidden');
        }

        $productId = $this->positiveId($productIdInput);
        if ($productId === null) {
            return $this->error($response, 404, 'not_found');
        }

        if ($this->products->findForCompany($companyId, $productId) === null) {
            return $this->error($response, 404, 'not_found');
        }

        return $this->json($response, 200, [
            'product_id' => $productId,
            'quantity' => $this->inventory->getForProduct($companyId, $productId),
        ]);
    }

    public function setInventory(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $productIdInput,
    ): ResponseInterface {
        $companyId = $this->authorizedCompanyId();
        if ($companyId === null) {
            return $this->error($response, 403, 'forbidden');
        }
        if (!$this->hasValidCsrf($request)) {
            return $this->error($response, 419, 'invalid_csrf');
        }

        $productId = $this->positiveId($productIdInput);
        if ($productId === null) {
            return $this->error($response, 404, 'not_found');
        }

        $body = $request->getParsedBody();
        $quantity = is_array($body) ? ($body['quantity'] ?? null) : null;
        if (!is_int($quantity) || $quantity < 0) {
            return $this->error($response, 422, 'invalid_quantity');
        }

        try {
            $this->inventory->setForProduct($companyId, $productId, $quantity);
        } catch (InvalidArgumentException) {
            return $this->error($response, 404, 'not_found');
        }

        return $this->json($response, 200, [
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    private function authorizedCompanyId(): ?int
    {
        try {
            return (new CompanyContext($this->pdo))->companyId();
        } catch (DomainException) {
            return null;
        }
    }

    private function hasValidCsrf(ServerRequestInterface $request): bool
    {
        try {
            (new Csrf())->assertValid($request->getHeaderLine('X-CSRF-Token'));
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function positiveId(string $input): ?int
    {
        $value = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($value) ? $value : null;
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    /** @param array<string,int|string|null> $payload */
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
