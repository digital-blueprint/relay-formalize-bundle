<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormalizeServiceTest extends WebTestCase
{
    private $api;

    protected function setUp(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $managerRegistry->expects($this->any())
            ->method('getManager')
            ->willReturnOnConsecutiveCalls($entityManager);
        $this->api = new FormalizeService($managerRegistry);
    }

    public function test()
    {
        $this->assertTrue(true);
        $this->assertNotNull($this->api);
    }
}
