<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class H7CCompanyDestructiveActionUxTest extends TestCase
{
    public function testCompanyDestructiveActionsRequireDeliberateHumanConfirmation(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Empresa H7C', 'empresa-h7c', 'h7c-company@example.test', 'test-h7c-company-password');
        self::assertTrue($auth->login('h7c-company@example.test', 'test-h7c-company-password'));

        $activeResponse = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/system/companies')
        );
        self::assertSame(200, $activeResponse->getStatusCode());
        $activeHtml = (string) $activeResponse->getBody();

        self::assertMatchesRegularExpression(
            '#<details[^>]*data-company-destructive-action="archive"[^>]*>.*?<summary>Archivar empresa</summary>.*?historial se conserva.*?reactivar.*?<form[^>]*action="/system/companies/1/archive"[^>]*>.*?</form>.*?</details>#si',
            $activeHtml,
            'Archivar debe exigir una segunda decisión humana y explicar que conserva historia y permite reactivar.'
        );

        $pdo->exec('UPDATE companies SET archived_at=UTC_TIMESTAMP(3) WHERE id=1');

        $archivedResponse = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/system/companies')
        );
        self::assertSame(200, $archivedResponse->getStatusCode());
        $archivedHtml = (string) $archivedResponse->getBody();

        self::assertStringContainsString('action="/system/companies/1/reactivate"', $archivedHtml);
        self::assertStringNotContainsString('data-company-destructive-action="reactivate"', $archivedHtml);

        self::assertMatchesRegularExpression(
            '#<details[^>]*data-company-destructive-action="delete"[^>]*>.*?<summary>Eliminar empresa definitivamente</summary>.*?<form[^>]*action="/system/companies/1/delete"[^>]*>.*?<input[^>]*type="checkbox"[^>]*name="confirm_permanent_delete"[^>]*required[^>]*>.*?<button[^>]*class="danger"[^>]*>Eliminar definitivamente</button>.*?</form>.*?</details>#si',
            $archivedHtml,
            'Eliminar definitivamente debe quedar detrás de una decisión deliberada y un acknowledgement obligatorio.'
        );
        self::assertStringContainsString('No se eliminará tu usuario', $archivedHtml);
    }
}
