<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\BlobBundle\TestUtils\BlobTestUtils;
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\FormalizeBundle\DependencyInjection\Configuration;

class TestUtils
{
    public const FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID = 'formalize-submitted-files-test-bucket-id';

    public const FORM_NAME_EVERYBODY_MAY_USE = 'public form';

    public static function getTestConfig(): array
    {
        $config = [
            Configuration::DATABASE_URL => 'sqlite:///:memory:',
            'authorization' => [
                'resource_permissions' => [
                    Configuration::MAY_CREATE_FORM => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_UPDATE_FORM => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_DELETE_FORM => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_READ_FORM => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_CREATE_FORM_SUBMISSIONS => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_READ_FORM_SUBMISSIONS => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_DELETE_FORM_SUBMISSIONS => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                    Configuration::MAY_UPDATE_FORM_SUBMISSIONS => 'resource.getName() === "'.self::FORM_NAME_EVERYBODY_MAY_USE.'"',
                ],
            ],
        ];

        return array_merge($config,
            BlobApi::getCustomModeConfig(self::FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID));
    }

    public static function getBlobTestConfig(): array
    {
        $blobTestConfig = BlobTestUtils::getTestConfig();
        $blobTestConfig['buckets'][0]['bucket_id'] = self::FORMALIZE_SUBMITTED_FILES_TEST_BUCKET_ID;
        $blobTestConfig['buckets'][0]['additional_types'] = [
            'test_type' => [],
        ];

        return $blobTestConfig;
    }

    public static function selectWhere(array $results, callable $where): array
    {
        return array_values(array_filter($results, $where));
    }
}
