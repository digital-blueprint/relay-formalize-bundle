<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestUtils
{
    public static function cleanupRequestCaches(ContainerInterface $container): void
    {
        AuthorizationTest::postRequestCleanup($container);

        /** @var AuthorizationService $authorizationService */
        $authorizationService = $container->get(AuthorizationService::class);
        $authorizationService->clearCaches();
    }
}
