<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestEntityManager as AuthorizationTestEntityManager;
use Dbp\Relay\AuthorizationBundle\TestUtils\TestResourceActionGrantServiceFactory;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\TestUtils\TestEntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractTestCase extends WebTestCase
{
    protected const CURRENT_USER_IDENTIFIER = TestAuthorizationService::TEST_USER_IDENTIFIER;

    protected TestEntityManager $testEntityManager;
    protected AuthorizationService $authorizationService;
    protected FormalizeService $formalizeService;
    protected AuthorizationTestEntityManager $authorizationTestEntityManager;
    protected ResourceActionGrantService $resourceActionGrantService;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();

        $this->authorizationTestEntityManager = TestResourceActionGrantServiceFactory::createTestEntityManager($kernel);
        $this->resourceActionGrantService = TestResourceActionGrantServiceFactory::createTestResourceActionGrantService(
            $this->authorizationTestEntityManager->getEntityManager(), self::CURRENT_USER_IDENTIFIER);
        $this->authorizationService = new AuthorizationService($this->resourceActionGrantService);
        TestAuthorizationService::setUp($this->authorizationService, self::CURRENT_USER_IDENTIFIER);

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
        $this->resourceActionGrantService = TestResourceActionGrantServiceFactory::createTestResourceActionGrantService(
            $this->authorizationTestEntityManager->getEntityManager(), $userIdentifier);
        $this->authorizationService->setResourceActionGrantService($this->resourceActionGrantService);
        TestAuthorizationService::setUp($this->authorizationService, $userIdentifier);
    }
}
