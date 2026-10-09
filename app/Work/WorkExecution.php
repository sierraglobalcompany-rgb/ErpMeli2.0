<?php

declare(strict_types=1);

namespace App\Work;

use App\Modules\Settings\SystemSettingsRepository;

final class WorkExecution
{
    public function __construct(
        private readonly SystemSettingsRepository $settings,
        private readonly WorkRunner $runner,
        private readonly WorkRepository $repository,
    ) {
    }

    /**
     * @param callable(array{
     *   id:int,company_id:?int,account_id:?int,type:string,resource_key:?string,payload:array<string,mixed>,
     *   status:string,attempts:int,claim_token:string,claimed_at:string
     * }): void $processor
     */
    public function runAutomatic(
        callable $processor,
        int $maxItems,
        int $maxSeconds,
    ): int {
        if (!$this->settings->get()->automationEnabled) {
            return 0;
        }

        return $this->runner->run(
            $this->repository,
            $processor,
            $maxItems,
            $maxSeconds,
        );
    }

    /**
     * @param callable(array{
     *   id:int,company_id:?int,account_id:?int,type:string,resource_key:?string,payload:array<string,mixed>,
     *   status:string,attempts:int,claim_token:string,claimed_at:string
     * }): void $processor
     */
    public function runManualOne(callable $processor): int
    {
        return $this->runner->run(
            $this->repository,
            $processor,
            maxItems: 1,
            maxSeconds: 45,
        );
    }
}
