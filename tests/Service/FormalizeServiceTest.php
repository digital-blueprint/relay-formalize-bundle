<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FormalizeServiceTest extends WebTestCase
{
    private $api;

    protected function setUp(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $managerRegistry->expects($this->any())
            ->method('getManager')
            ->willReturnOnConsecutiveCalls($entityManager);
        $this->api = new FormalizeService($managerRegistry, $eventDispatcher);
    }

    public function test()
    {
        $this->assertTrue(true);
        $this->assertNotNull($this->api);
    }
}
