<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\HealthCheck;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class HealthCheck implements CheckInterface
{
    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly AuthorizationService $authorizationService)
    {
    }

    public function getName(): string
    {
        return 'formalize';
    }

    private function checkDbConnection(): CheckResult
    {
        $result = new CheckResult('Check if we can connect to the DB');

        try {
            $this->formalizeService->checkConnection();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }

    private function checkAuthorization(): CheckResult
    {
        $result = new CheckResult('Validate if access control policies are valid');
        $result->set(CheckResult::STATUS_SUCCESS);
        try {
            $this->authorizationService->validateConfiguration();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        return [$this->checkDbConnection()];
    }
}
