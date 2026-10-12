<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Work\WorkRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkStaleClaimTest extends TestCase
{
    public function testOldClaimCannotPersistAfterWorkIsReclaimed(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec(
            'CREATE TABLE work_effects ('
            . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, '
            . 'marker VARCHAR(80) NOT NULL'
            . ') ENGINE=InnoDB'
        );
        $repository = new WorkRepository($pdo);

        $pdo->exec(
            "INSERT INTO work_items "
            . "(company_id, account_id, scope_key, type, resource_key, dedupe_key, status, available_at, created_at, updated_at) "
            . "VALUES (1, 10, 'company:1:account:10', 'order.sync', 'order:stale', 'stale-key', 'pending', "
            . "UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))"
        );
        $workId = (int) $pdo->lastInsertId();

        $claimA = $repository->claimNext();
        self::assertNotNull($claimA);

        // Simulate the legitimate runner recovering an orphaned running row and reclaiming it.
        $pdo->exec(
            'UPDATE work_items SET status = \'pending\', claim_token = NULL, claimed_at = NULL '
            . 'WHERE id = ' . $workId
        );
        $claimB = $repository->claimNext();
        self::assertNotNull($claimB);
        self::assertSame($workId, $claimB['id']);
        self::assertSame(2, $claimB['attempts']);
        self::assertNotSame($claimA['claim_token'], $claimB['claim_token']);

        $oldCommitted = $repository->completeCurrentClaim(
            $workId,
            $claimA['claim_token'],
            static function (PDO $connection): void {
                $connection->exec("INSERT INTO work_effects (marker) VALUES ('old-worker')");
            }
        );

        self::assertFalse($oldCommitted);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM work_effects')->fetchColumn());

        $current = $pdo->query('SELECT status, claim_token FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($current);
        self::assertSame('running', $current['status']);
        self::assertSame($claimB['claim_token'], $current['claim_token']);

        $newCommitted = $repository->completeCurrentClaim(
            $workId,
            $claimB['claim_token'],
            static function (PDO $connection): void {
                $connection->exec("INSERT INTO work_effects (marker) VALUES ('current-worker')");
            }
        );

        self::assertTrue($newCommitted);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM work_effects')->fetchColumn());

        $done = $pdo->query('SELECT status, claim_token, claimed_at, finished_at FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($done);
        self::assertSame('done', $done['status']);
        self::assertNull($done['claim_token']);
        self::assertNull($done['claimed_at']);
        self::assertNotNull($done['finished_at']);
    }
    public function testAtomicSuccessfulDeferRejectsStaleClaimAndReusesCurrentWork(): void
    {
        $pdo=TestDatabase::reset();$work=new WorkRepository($pdo);
        $id=$work->enqueue(null,null,'test:defer','test','resource','identity',[],new \DateTimeImmutable('2000-01-01Z'));
        $claim=$work->claimNext();self::assertIsArray($claim);
        $ran=false;
        self::assertFalse($work->completeCurrentClaim($id,str_repeat('a',32),function(PDO $p)use(&$ran):void{$ran=true;},new \DateTimeImmutable('2030-01-01Z')));
        self::assertFalse($ran);
        self::assertTrue($work->completeCurrentClaim($id,$claim['claim_token'],function(PDO $p):void{},new \DateTimeImmutable('2030-01-01Z')));
        $row=$pdo->query('SELECT * FROM work_items')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('pending',$row['status']);self::assertSame(0,(int)$row['attempts']);self::assertNull($row['claim_token']);self::assertNull($row['last_error_code']);
        self::assertSame('2030-01-01 00:00:00.000000',$row['available_at']);self::assertNull($work->claimNext());
    }

}
