<?php

declare(strict_types=1);

namespace App\Modules\Sales\ReceiveOrderWebhook;

use App\Core\Logging\DebugRecorder;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class OrderWebhookReceiver
{
    private readonly DebugRecorder $debugRecorder;

    public function __construct(
        private readonly PDO $pdo,
        private readonly WorkRepository $work,
        private readonly string $applicationId,
        ?DebugRecorder $debugRecorder = null,
    ) {
        if ($debugRecorder instanceof DebugRecorder) {
            $this->debugRecorder = $debugRecorder;
            return;
        }

        $settings = (new SystemSettingsRepository($pdo))->get();
        $this->debugRecorder = new DebugRecorder(
            dirname(__DIR__, 4) . '/storage/debug',
            $settings->debugEnabled,
            $settings->debugMaxMb * 1024 * 1024,
        );
    }

    /** @param array<string,mixed> $payload */
    public function receive(array $payload): bool
    {
        $eventId = $this->scalarString($payload['_id'] ?? null);
        $topic = $this->scalarString($payload['topic'] ?? null);
        $resource = $this->scalarString($payload['resource'] ?? null);
        $sellerId = $this->scalarString($payload['user_id'] ?? null);
        $applicationId = $this->scalarString($payload['application_id'] ?? null);

        if ($eventId === '' || strlen($eventId) > 80 ||
            $topic !== 'orders_v2' ||
            $applicationId !== $this->applicationId ||
            $sellerId === '' || strlen($sellerId) > 32 ||
            preg_match('#^/orders/([0-9]{1,32})$#D', $resource, $matches) !== 1) {
            return false;
        }

        $orderId = $matches[1];
        $account = $this->connectedAccount($sellerId);
        if ($account === null) {
            return false;
        }

        $attempts = $this->nullableUnsignedSmallInt($payload['attempts'] ?? null);
        $sentAt = $this->nullableUtcTimestamp($payload['sent'] ?? null);
        $receivedAt = $this->nullableUtcTimestamp($payload['received'] ?? null);

        $this->pdo->beginTransaction();

        try {
            $event = $this->pdo->prepare(
                'INSERT INTO webhook_events '
                . '(event_id, topic, resource, external_user_id, application_id, attempts, sent_at, received_at) '
                . 'VALUES (:event_id, :topic, :resource, :external_user_id, :application_id, :attempts, :sent_at, :received_at) '
                . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $event->execute([
                'event_id' => $eventId,
                'topic' => $topic,
                'resource' => $resource,
                'external_user_id' => $sellerId,
                'application_id' => $applicationId,
                'attempts' => $attempts,
                'sent_at' => $sentAt,
                'received_at' => $receivedAt,
            ]);

            $scope = 'company:' . $account['company_id'] . ':account:' . $account['id'];
            $workId = $this->work->enqueue(
                $account['company_id'],
                $account['id'],
                $scope,
                'order.sync',
                $orderId,
                'order.sync:' . $orderId,
                ['order_id' => $orderId],
            );

            $this->pdo->commit();
            $this->recordDebug('webhook.accepted', [
                'correlation_id' => 'work:' . $workId,
                'event_id' => $eventId,
                'topic' => $topic,
                'work_id' => $workId,
                'resource_id' => $orderId,
                'company_id' => $account['company_id'],
                'account_id' => $account['id'],
            ]);
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array{id:int,company_id:int}|null */
    private function connectedAccount(string $sellerId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, company_id FROM meli_accounts "
            . "WHERE external_user_id = :external_user_id AND status = 'connected' "
            . 'ORDER BY id LIMIT 2'
        );
        $statement->execute(['external_user_id' => $sellerId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        // A webhook user must resolve to one unambiguous local account.
        if (count($rows) !== 1) {
            return null;
        }

        return [
            'id' => (int) $rows[0]['id'],
            'company_id' => (int) $rows[0]['company_id'],
        ];
    }

    /** @param array<string,mixed> $fields */
    private function recordDebug(string $event, array $fields): void
    {
        try {
            $this->debugRecorder->record($event, $fields);
        } catch (Throwable) {
            // Debug observability must never change webhook acceptance.
        }
    }

    private function scalarString(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function nullableUnsignedSmallInt(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        $number = (int) $value;
        return $number >= 0 && $number <= 65535 ? $number : null;
    }

    private function nullableUtcTimestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u');
        } catch (\Exception) {
            return null;
        }
    }
}
