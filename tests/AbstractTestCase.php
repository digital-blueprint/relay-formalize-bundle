<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager as AuthorizationTestEntityManager;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\EventSubscriber\GetAvailableResourceClassActionsEventSubscriber;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractTestCase extends WebTestCase
{
    protected const CURRENT_USER_IDENTIFIER = TestAuthorizationService::TEST_USER_IDENTIFIER;
    protected const ANOTHER_USER_IDENTIFIER = self::CURRENT_USER_IDENTIFIER.'_2';

    protected ?TestEntityManager $testEntityManager = null;
    protected ?AuthorizationService $authorizationService = null;
    protected ?FormalizeService $formalizeService = null;
    protected ?AuthorizationTestEntityManager $authorizationTestEntityManager = null;
    protected ?ResourceActionGrantService $resourceActionGrantService = null;

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
        $this->formalizeService = new FormalizeService(
            $this->testEntityManager->getEntityManager(), new EventDispatcher(), $this->authorizationService);
        $this->formalizeService->setLogger(new ConsoleLogger(new BufferedOutput()));
    }

    protected function selectWhere(array $results, callable $where): array
    {
        return array_filter($results, $where);
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
