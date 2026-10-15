<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $context = new CompanyContext($this->pdo);
        try {
            $company = $context->company();
            $companies = $context->memberships();
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $range = $this->dateRange($request->getQueryParams());
        if ($range === null) {
            $response->getBody()->write('Rango de fechas inválido. Usa fechas YYYY-MM-DD y un máximo de 366 días.');
            return $response->withStatus(422)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        [$from, $to] = $range;
        $toExclusive = $to->modify('+1 day');
        $snapshot = (new DashboardReadModel($this->pdo))->overview((int) $company['id'], $from, $toExclusive);
        $fromValue = $from->format('Y-m-d');
        $toValue = $to->format('Y-m-d');
        $csrfToken = (new Csrf())->token();

        ob_start();
        require __DIR__ . '/views/index.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array<string,mixed> $query
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}|null
     */
    private function dateRange(array $query): ?array
    {
        $timezone = new DateTimeZone('UTC');
        $fromRaw = $query['from'] ?? null;
        $toRaw = $query['to'] ?? null;

        if ($fromRaw === null && $toRaw === null) {
            $today = new DateTimeImmutable('today', $timezone);
            return [$today->modify('first day of this month'), $today];
        }
        if (!is_string($fromRaw) || !is_string($toRaw)) {
            return null;
        }

        $from = $this->parseDate($fromRaw, $timezone);
        $to = $this->parseDate($toRaw, $timezone);
        if ($from === null || $to === null || $from > $to) {
            return null;
        }

        $days = $from->diff($to)->days;
        if ($days === false || $days > 365) {
            return null;
        }

        return [$from, $to];
    }

    private function parseDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }
        return $date;
    }
}
