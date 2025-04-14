<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\TestUtils;

use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\BlobBundle\TestUtils\BlobTestUtils;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\DependencyInjection\Configuration;
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

    public static function getTestConfig(): array
    {
        return
            [
                Configuration::DATABASE_URL => 'sqlite:///:memory:',
                Configuration::SUBMITTED_FILES_BUCKET_ID => TestUtils::FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID,
            ];
    }

    public static function getBlobTestConfig(): array
    {
        $blobTestConfig = BlobTestUtils::getTestConfig();
        $blobTestConfig['buckets'][0]['bucket_id'] = self::FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID;

        return $blobTestConfig;
    }

    public static function selectWhere(array $results, callable $where): array
    {
        return array_values(array_filter($results, $where));
    }
}
