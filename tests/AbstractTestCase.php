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
use Dbp\Relay\FormalizeBundle\TestUtils\TestEntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        parent::setUp();

        $kernel = self::bootKernel();

        $this->authorizationTestEntityManager = TestResourceActionGrantServiceFactory::createTestEntityManager($kernel);
        $this->createAndSetupAuthorizationServiceForUser(self::CURRENT_USER_IDENTIFIER);

        $this->testEntityManager = new TestEntityManager($kernel);
        $this->formalizeService = new FormalizeService(
            $this->testEntityManager->getEntityManager(), new EventDispatcher(), $this->authorizationService);
    }

    protected function selectWhere(array $results, callable $where): array
    {
        return array_filter($results, $where);
    }

    protected function login(string $userIdentifier): void
    {
        $this->createAndSetupAuthorizationServiceForUser($userIdentifier);
    }

    private function createAndSetupAuthorizationServiceForUser(string $userIdentifier): void
    {
        $this->resourceActionGrantService = TestResourceActionGrantServiceFactory::createTestResourceActionGrantService(
            $this->authorizationTestEntityManager->getEntityManager(), $userIdentifier, [],
            new GetAvailableResourceClassActionsEventSubscriber());
        if ($this->authorizationService === null) {
            $this->authorizationService = new AuthorizationService($this->resourceActionGrantService);
        } else {
            $this->authorizationService->setResourceActionGrantService($this->resourceActionGrantService);
        }
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier);
    }
}
