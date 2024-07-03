<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Dbp\Relay\AuthorizationBundle\TestUtils\AuthorizationTestTrait;

class ApiTest extends ApiTestCase
{
    use AuthorizationTestTrait;

    private Client $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->setUpTestEntityManager($this->client);
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
            $response = $this->client->request($method, $path);
            $this->assertEquals($status, $response->getStatusCode(), 'GET '.$path);
        }
    }
}
