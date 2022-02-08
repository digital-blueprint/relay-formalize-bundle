<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Tests\Service;

use Dbp\Relay\FormsBundle\Service\FormsService;
use Dbp\Relay\FormsBundle\Service\MyCustomService;
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
