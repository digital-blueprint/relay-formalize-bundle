<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\FormalizeBundle\Service\FormsService;
use Dbp\Relay\FormalizeBundle\Service\MyCustomService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormsServiceTest extends WebTestCase
{
    private $api;

    protected function setUp(): void
    {
        $service = new MyCustomService('test-42');
        $this->api = new FormsService($service);
    }

    public function test()
    {
        $this->assertTrue(true);
        $this->assertNotNull($this->api);
    }
}
