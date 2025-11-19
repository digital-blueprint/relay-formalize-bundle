<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\BlobBundle\TestUtils\BlobTestUtils;
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\FormalizeBundle\DependencyInjection\Configuration;

class TestUtils
{
    public const FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID = 'formalize-submitted-files-test-bucket-id';

    public static function getTestConfig(): array
    {
        return array_merge([
            Configuration::DATABASE_URL => 'sqlite:///:memory:',
        ], BlobApi::getCustomModeConfig(self::FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID));
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
