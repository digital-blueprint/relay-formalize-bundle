<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class ApiTest extends ApiTestCase
{
    //    use UserAuthTrait;

    //    public function testBasics()
    //    {
    //        $client = self::createClient();
    //        $response = $client->request('POST', '/formalize/submissions', [
    //            'headers' => [
    //                'Content-Type' => 'application/ld+json',
    //            ],
    //            'body' => json_encode(['dataFeedElement' => 'foo']),
    //        ]);
    //        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    //        $this->assertSame('foo', json_decode($response->getContent(), true)['dataFeedElement']);
    //    }

    public function testNoAuth()
    {
        $endpoints = [
            ['GET', '/formalize/submissions', 401],
            ['GET', '/formalize/submissions/123', 401],
        ];

        foreach ($endpoints as $ep) {
            [$method, $path, $status] = $ep;
            $client = self::createClient();
            $response = $client->request($method, $path);
            $this->assertEquals($status, $response->getStatusCode(), 'GET '.$path);
        }
    }
}
