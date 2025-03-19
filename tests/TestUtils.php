<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestUtils
{
    public const FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID = 'formalize-submitted-files-test-bucket-id';

    public static function cleanupRequestCaches(ContainerInterface $container): void
    {
        AuthorizationTest::postRequestCleanup($container);

        /** @var AuthorizationService $authorizationService */
        $authorizationService = $container->get(AuthorizationService::class);
        $authorizationService->clearCaches();
    }
}
