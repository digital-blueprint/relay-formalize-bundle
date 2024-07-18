<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTest;
use Dbp\Relay\CoreBundle\TestUtils\TestClient;

class ApiTest extends ApiTestCase
{
    private ?TestClient $testClient = null;

    protected function setUp(): void
    {
        $this->testClient = new TestClient(self::createClient());
        $this->testClient->setUpUser();
        AuthorizationTest::setUp($this->testClient->getContainer());
        // the following allows multiple requests in one test:
        $this->testClient->getClient()->disableReboot();
    }

    protected function tearDown(): void
    {
        AuthorizationTest::tearDown($this->testClient->getContainer());
    }

    public function testNoAuth()
    {
        $endpoints = [
            ['GET', '/formalize/submissions', 401],
            ['GET', '/formalize/submissions/123', 401],
            ['GET', '/formalize/forms', 401],
            ['GET', '/formalize/forms/123', 401],
        ];

        foreach ($endpoints as $ep) {
            [$method, $path, $status] = $ep;
            $response = $this->testClient->request($method, $path, [], null);
            $this->assertEquals($status, $response->getStatusCode(), 'GET '.$path);
        }
    }
}
