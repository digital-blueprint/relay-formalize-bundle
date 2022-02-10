<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends ApiTestCase
{
//    public function testBasics()
//    {
//        $client = self::createClient();
//        $response = $client->request('POST', '/formalize/submissions', [
//            'headers' => [
//                'Content-Type' => 'application/json',
//            ],
//            'body' => json_encode(['data' => 'foo']),
//        ]);
//        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
//        $this->assertSame('foo', json_decode($response->getContent(), true)['data']);
//    }

    public function testNoAuth()
    {
        $endpoints = [
            ['POST', '/formalize/submissions', 401],
            ['GET', '/formalize/submissions', 401],
            ['GET', '/formalize/submissions/123', 404],
        ];

        foreach ($endpoints as $ep) {
            [$method, $path, $status] = $ep;
            $client = self::createClient();
            $response = $client->request($method, $path);
            $this->assertEquals($status, $response->getStatusCode(), $path);
        }

    }
}
