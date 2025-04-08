<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager as AuthorizationTestEntityManager;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\BlobBundle\Api\FileApi;
use Dbp\Relay\BlobBundle\TestUtils\BlobTestUtils;
use Dbp\Relay\BlobBundle\TestUtils\TestEntityManager as BlobTestEntityManager;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\EventSubscriber\GetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractTestCase extends WebTestCase
{
    public const TEST_FORM_SCHEMA = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["givenName", "familyName"]
        }';
    public const TEST_FORM_SCHEMA_WITH_TEST_FILE = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["givenName", "familyName"],
            "files": {
                "testFile": {
                    "minNumber": 1,
                    "maxNumber": 1,
                    "allowedMimeTypes": ["text/plain"]
                },
                "optionalFiles": {
                    "minNumber": 0,
                    "maxNumber": 2,
                    "allowedMimeTypes": ["text/plain", "application/pdf"]
                }
            }
        }';

    public const TEXT_FILE_NAME = 'test.txt';
    public const TEXT_FILE_2_NAME = 'test-updated.txt';
    public const PDF_FILE_NAME = 'test.pdf';

    public const TEXT_FILE_PATH = __DIR__.'/Data/'.self::TEXT_FILE_NAME;
    public const TEXT_FILE_2_PATH = __DIR__.'/Data/'.self::TEXT_FILE_2_NAME;
    public const PDF_FILE_PATH = __DIR__.'/Data/'.self::PDF_FILE_NAME;

    protected const TEST_FORM_NAME = 'Test Form';

    protected const CURRENT_USER_IDENTIFIER = TestAuthorizationService::TEST_USER_IDENTIFIER;
    protected const ANOTHER_USER_IDENTIFIER = self::CURRENT_USER_IDENTIFIER.'_2';

    protected ?TestEntityManager $testEntityManager = null;
    protected ?AuthorizationService $authorizationService = null;
    protected ?FormalizeService $formalizeService = null;
    protected ?TestSubmissionEventSubscriber $testSubmissionEventSubscriber = null;
    protected ?AuthorizationTestEntityManager $authorizationTestEntityManager = null;
    protected ?ResourceActionGrantService $resourceActionGrantService = null;
    protected ?SubmittedFileService $submittedFileService = null;
    protected ?BlobTestEntityManager $blobTestEntityManager = null;
    protected ?FileApi $fileApi = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->authorizationTestEntityManager = TestResourceActionGrantServiceFactory::createTestEntityManager(
            $kernel->getContainer());
        $this->resourceActionGrantService = TestResourceActionGrantServiceFactory::createTestResourceActionGrantService(
            $this->authorizationTestEntityManager->getEntityManager(), self::CURRENT_USER_IDENTIFIER, [],
            new GetAvailableResourceClassActionsEventSubscriber());

        $this->authorizationService = new AuthorizationService($this->resourceActionGrantService);
        TestAuthorizationService::setUp($this->authorizationService, self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager = new TestEntityManager($kernel->getContainer());
        $this->testSubmissionEventSubscriber = new TestSubmissionEventSubscriber();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($this->testSubmissionEventSubscriber);

        $this->blobTestEntityManager = new BlobTestEntityManager($kernel->getContainer());
        $this->fileApi = BlobTestUtils::createTestFileApi(
            $this->blobTestEntityManager->getEntityManager(),
            TestUtils::getBlobTestConfig());

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $this->submittedFileService = new SubmittedFileService(
            $this->testEntityManager->getEntityManager(), $this->fileApi, $requestStack);
        $this->submittedFileService->setConfig(TestUtils::getTestConfig());

        $this->formalizeService = new FormalizeService(
            $this->testEntityManager->getEntityManager(), $eventDispatcher, $this->authorizationService,
            $this->submittedFileService);
        $this->formalizeService->setLogger(new ConsoleLogger(new BufferedOutput()));
    }

    protected function selectWhere(array $results, callable $where): array
    {
        return array_values(array_filter($results, $where));
    }

    protected function containsResource(array $resources, mixed $resource): bool
    {
        foreach ($resources as $res) {
            if ($resource->getIdentifier() === $res->getIdentifier()) {
                return true;
            }
        }

        return false;
    }

    protected function assertResourcesAreAPermutationOf(array $resourcesA, array $resourcesB): void
    {
        $this->assertTrue(count($resourcesA) === count($resourcesB)
            && count($resourcesA) === count(array_uintersect($resourcesA, $resourcesB,
                function ($resourceA, $resourceB) {
                    return strcmp($resourceA->getIdentifier(), $resourceB->getIdentifier());
                })), 'resource arrays are no permutation of each other');
    }

    protected function login(string $userIdentifier): void
    {
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier);
        TestResourceActionGrantServiceFactory::login($this->resourceActionGrantService, $userIdentifier);
    }

    protected function loginServiceAccount(): void
    {
        // WORKAROUND: TestAuthorizationService::setUp() currently does not support service accounts,
        // however, setting up an unauthenticated user also leads to a null user identifier,
        // which is currently sufficient for our tests. In the next release of the core bundle,
        // the next line can be replaced by:
        // TestAuthorizationService::setUp($this->authorizationService, isServiceAccount: true);
        TestAuthorizationService::setUp($this->authorizationService, TestAuthorizationService::UNAUTHENTICATED_USER_IDENTIFIER);
        TestResourceActionGrantServiceFactory::login($this->resourceActionGrantService, TestAuthorizationService::UNAUTHENTICATED_USER_IDENTIFIER);
    }

    protected function assertIsPermutationOf(array $array1, array $array2): void
    {
        $this->assertTrue($this->isPermutationOf($array1, $array2), 'arrays are no permutations of each other');
    }

    protected function isPermutationOf(array $array1, array $array2): bool
    {
        return count($array1) === count($array2)
            && count($array1) === count(array_intersect($array1, $array2));
    }
}
