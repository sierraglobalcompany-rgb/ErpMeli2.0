<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Migrator;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class MeliAccountIdentityMigrationTest extends TestCase
{
    public function testLegacyDuplicateSellersBlockMigrationWithoutMergeDeleteOrFalseAppliedMarker(): void
    {
        $pdo = TestDatabase::reset();

        // Recreate the pre-012 identity rule inside this disposable test database.
        $pdo->exec(
            'ALTER TABLE meli_accounts '
            . 'DROP INDEX uq_meli_accounts_external_user, '
            . 'ADD UNIQUE KEY uq_meli_accounts_company_user (company_id, external_user_id)'
        );
        $removeMarker = $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?');
        $removeMarker->execute(['012_meli_account_identity.sql']);

        $pdo->exec("INSERT INTO companies(name,slug) VALUES('Legacy A','legacy-a'),('Legacy B','legacy-b')");
        $companyIds = $pdo->query('SELECT id FROM companies ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(2, $companyIds);

        $insert = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES(?, '42424242', 'MCO', ?, 'disabled')"
        );
        $insert->execute([(int) $companyIds[0], 'Legacy Seller A']);
        $insert->execute([(int) $companyIds[1], 'Legacy Seller B']);

        $before = $pdo->query(
            "SELECT id,company_id,external_user_id,nickname,status FROM meli_accounts "
            . "WHERE external_user_id='42424242' ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $before);

        $failure = null;
        try {
            (new Migrator())->migrate($pdo, dirname(__DIR__, 2) . '/database/migrations');
        } catch (PDOException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(PDOException::class, $failure);
        self::assertSame('23000', $failure->getCode());
        self::assertSame(
            $before,
            $pdo->query(
                "SELECT id,company_id,external_user_id,nickname,status FROM meli_accounts "
                . "WHERE external_user_id='42424242' ORDER BY id"
            )->fetchAll(PDO::FETCH_ASSOC),
            'The migration must not choose, merge, move, or delete either legacy seller row.',
        );

        $marker = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = ?');
        $marker->execute(['012_meli_account_identity.sql']);
        self::assertSame(0, (int) $marker->fetchColumn());
    }
}
