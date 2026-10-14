<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class CompanyManagementController
{
    public function __construct(private readonly PDO $pdo) {}

    public function handle(ServerRequestInterface $request, ResponseInterface $response, string $action = '', int $id = 0): ResponseInterface
    {
        try { (new CompanyContext($this->pdo))->assertSystemAdmin(); }
        catch (DomainException) { return $response->withStatus(403); }
        $response = $response->withHeader('Cache-Control','no-store')->withHeader('Content-Type','text/html; charset=utf-8');
        if ($request->getMethod() === 'GET') {
            $companies = $this->pdo->query('SELECT id,name,slug,archived_at FROM companies ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($companies as &$company) {
                $company['deletable'] = $company['archived_at'] !== null && $this->isPristine((int)$company['id']);
            }
            unset($company);
            $csrfToken = (new Csrf())->token();
            ob_start(); require __DIR__.'/views/companies.php';
            $response->getBody()->write((string)ob_get_clean());
            return $response;
        }
        $body = $request->getParsedBody(); $body = is_array($body) ? $body : [];
        $token = $body['csrf_token'] ?? null;
        try { (new Csrf())->assertValid(is_string($token) ? $token : ''); }
        catch (\RuntimeException) { return $response->withStatus(419); }
        $this->pdo->beginTransaction();
        try {
            $this->change($action,$id,$body);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            if ($exception instanceof InvalidArgumentException) {
                $response->getBody()->write('Revisa nombre e identificador: nombre de hasta 160 caracteres e identificador de hasta 120 con letras minúsculas, números y guiones.');
                return $response->withStatus(422);
            }
            if ($exception instanceof OutOfBoundsException) { return $response->withStatus(404); }
            if ($exception instanceof DomainException || ($exception instanceof PDOException && $exception->getCode() === '23000')) {
                $response->getBody()->write('No se pudo completar: identificador duplicado, actividad operativa pendiente o empresa con uso previo. No se alteraron sus datos.');
                return $response->withStatus(409);
            }
            throw $exception;
        }
        if (in_array($action,['archive','delete'],true) && ($_SESSION['company_id'] ?? null) === $id) {
            unset($_SESSION['company_id'],$_SESSION['role']);
        }
        return $response->withHeader('Location','/system/companies')->withStatus(303);
    }

    /** @param array<mixed> $body */
    private function change(string $action, int $id, array $body): void
    {
        if ($action === '') {
            [$name,$slug] = $this->fields($body);
            $statement = $this->pdo->prepare('INSERT INTO companies(name,slug) VALUES(?,?)');
            $statement->execute([$name,$slug]);
            $statement = $this->pdo->prepare("INSERT INTO company_users(company_id,user_id,role) VALUES(?,?,'admin')");
            $statement->execute([(int)$this->pdo->lastInsertId(),$_SESSION['user_id']]);
            return;
        }
        $statement = $this->pdo->prepare('SELECT archived_at FROM companies WHERE id=? FOR UPDATE');
        $statement->execute([$id]); $company = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($company)) { throw new OutOfBoundsException(); }
        if ($action === 'update') {
            [$name,$slug] = $this->fields($body);
            $statement = $this->pdo->prepare('UPDATE companies SET name=?,slug=? WHERE id=?');
            $statement->execute([$name,$slug,$id]);
        } elseif ($action === 'archive') {
            $statement = $this->pdo->prepare("SELECT EXISTS(SELECT 1 FROM work_items WHERE company_id=? AND status IN ('pending','running'))"
                . " OR EXISTS(SELECT 1 FROM meli_accounts WHERE company_id=? AND status<>'disabled')"
                . " OR EXISTS(SELECT 1 FROM invoicing_service_keys WHERE company_id=? AND status='active')");
            $statement->execute([$id,$id,$id]);
            if ((int)$statement->fetchColumn()!==0) { throw new DomainException(); }
            $statement = $this->pdo->prepare('UPDATE companies SET archived_at=UTC_TIMESTAMP(3) WHERE id=?');
            $statement->execute([$id]);
        } elseif ($action === 'reactivate') {
            $statement = $this->pdo->prepare('UPDATE companies SET archived_at=NULL WHERE id=?');
            $statement->execute([$id]);
        } elseif ($action === 'delete') {
            if ($company['archived_at']===null || !$this->isPristine($id)) { throw new DomainException(); }
            $statement = $this->pdo->prepare('DELETE FROM company_users WHERE company_id=? AND user_id=?');
            $statement->execute([$id,$_SESSION['user_id']]);
            $statement = $this->pdo->prepare('DELETE FROM companies WHERE id=?');
            $statement->execute([$id]);
        } else { throw new InvalidArgumentException(); }
    }

    private function isPristine(int $id): bool
    {
        // Cooldowns use app:<family>, never Company scopes; account usage is covered by the account root.
        $statement = $this->pdo->prepare('SELECT EXISTS(SELECT 1 FROM meli_accounts WHERE company_id=?)'
            . ' OR EXISTS(SELECT 1 FROM products WHERE company_id=?)'
            . ' OR EXISTS(SELECT 1 FROM work_items WHERE company_id=?)'
            . ' OR EXISTS(SELECT 1 FROM invoicing_service_keys WHERE company_id=?)'
            . ' OR EXISTS(SELECT 1 FROM api_usage_daily WHERE scope_key=? OR scope_key LIKE ?)'
            . ' OR (SELECT COUNT(*) FROM company_users WHERE company_id=?)<>1'
            . " OR NOT EXISTS(SELECT 1 FROM company_users WHERE company_id=? AND user_id=? AND role='admin')");
        $statement->execute([$id,$id,$id,$id,'company:'.$id,'company:'.$id.':account:%',$id,$id,$_SESSION['user_id']]);
        return (int)$statement->fetchColumn()===0;
    }

    /** @param array<mixed> $body
     *  @return array{string,string}
     */
    private function fields(array $body): array
    {
        $name = $body['name'] ?? null; $slug = $body['slug'] ?? null;
        if (!is_string($name) || !is_string($slug)) { throw new InvalidArgumentException(); }
        $name = trim($name); $slug = trim($slug);
        if ($name === '' || mb_strlen($name)>160 || strlen($slug)>120 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)!==1) {
            throw new InvalidArgumentException();
        }
        return [$name,$slug];
    }
}
