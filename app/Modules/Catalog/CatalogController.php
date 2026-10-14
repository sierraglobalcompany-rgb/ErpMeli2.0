<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use App\Modules\Inventory\InventoryRepository;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class CatalogController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $error = '',
        string $internalSku = '',
        string $name = '',
    ): ResponseInterface
    {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->company();
        } catch (DomainException) {
            return $response->withStatus(403);
        }
        try {
            $context->companyForRoles('admin', 'operator');
            $canOperate = true;
        } catch (DomainException) {
            $canOperate = false;
        }
        $repository = new ProductRepository($this->pdo);
        $query = $request->getQueryParams();
        $input = $query['product_id'] ?? '';
        $productIdInput = is_string($input) || is_int($input) ? (string) $input : '';
        $selectedProduct = null;
        $quantity = null;
        if ($input !== '') {
            $id = $this->positiveId($input);
            if ($id === null) {
                $error = 'ID de producto inválido.';
                $response = $response->withStatus(422);
            } else {
                $selectedProduct = $repository->findForCompany($company['id'], $id);
                if ($selectedProduct === null) {
                    $error = 'Producto no encontrado en esta empresa.';
                    $response = $response->withStatus(404);
                } else {
                    $quantity = (new InventoryRepository($this->pdo))->getForProduct($company['id'], $id);
                }
            }
        }
        $products = $repository->listRecentForCompany($company['id']);
        $companies = $context->memberships();
        $csrfToken = (new Csrf())->token();
        ob_start();
        require __DIR__ . '/views/catalog.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $companyId = (new CompanyContext($this->pdo))->companyForRoles('admin', 'operator')['id'];
        } catch (DomainException) {
            return $response->withStatus(403);
        }
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $internalSku = is_string($body['internal_sku'] ?? null) ? $body['internal_sku'] : '';
        $name = is_string($body['name'] ?? null) ? $body['name'] : '';
        $request = $request->withQueryParams([]);
        if (!$this->validCsrf($body)) {
            return $this->index($request, $response->withStatus(419), 'Sesión del formulario inválida. Vuelva a enviar el formulario.', $internalSku, $name);
        }
        try {
            $id = (new ProductRepository($this->pdo))->create($companyId, $internalSku, $name);
        } catch (InvalidArgumentException) {
            return $this->index($request, $response->withStatus(422), 'SKU y nombre son obligatorios.', $internalSku, $name);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return $this->index($request, $response->withStatus(409), 'Ya existe un producto con este SKU en esta empresa.', $internalSku, $name);
            }
            if ($exception->getCode() === '22001') {
                return $this->index($request, $response->withStatus(422), 'SKU máximo 120 caracteres; nombre máximo 255.', $internalSku, $name);
            }
            throw $exception;
        }
        return $response->withHeader('Location', '/catalog?product_id=' . $id)->withStatus(303);
    }

    public function setInventory(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $productIdInput,
    ): ResponseInterface {
        try {
            $companyId = (new CompanyContext($this->pdo))->companyForRoles('admin', 'operator')['id'];
        } catch (DomainException) {
            return $response->withStatus(403);
        }
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $request = $request->withQueryParams([]);
        if (!$this->validCsrf($body)) {
            return $this->index($request, $response->withStatus(419), 'Sesión del formulario inválida. Vuelva a enviar el formulario.');
        }
        $id = $this->positiveId($productIdInput);
        if ($id === null) {
            return $this->index($request, $response->withStatus(404), 'Producto no encontrado en esta empresa.');
        }
        $request = $request->withQueryParams(['product_id' => (string) $id]);
        $input = $body['quantity'] ?? null;
        $quantity = false;
        if ((is_string($input) || is_int($input)) && preg_match('/^[0-9]+$/D', (string) $input) === 1) {
            $digits = ltrim((string) $input, '0');
            $quantity = filter_var($digits === '' ? '0' : $digits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        }
        if (!is_int($quantity)) {
            return $this->index($request, $response->withStatus(422), 'Cantidad debe ser un entero >= 0 dentro del rango admitido.');
        }
        try {
            (new InventoryRepository($this->pdo))->setForProduct($companyId, $id, $quantity);
        } catch (InvalidArgumentException) {
            return $this->index($request, $response->withStatus(404), 'Producto no encontrado en esta empresa.');
        }
        return $response->withHeader('Location', '/catalog?product_id=' . $id)->withStatus(303);
    }

    /** @param array<mixed> $body */
    private function validCsrf(array $body): bool
    {
        try {
            (new Csrf())->assertValid(is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '');
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function positiveId(mixed $input): ?int
    {
        if ((!is_string($input) && !is_int($input)) || preg_match('/^[1-9][0-9]*$/D', (string) $input) !== 1) {
            return null;
        }
        $id = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($id) ? $id : null;
    }
}
