<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiTest extends ApiTestCase
{
//    public function testBasics()
//    {
//        $client = self::createClient();
//        $response = $client->request('POST', '/forms/form_datas', [
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
            ['POST', '/forms/form_datas', 401],
            ['GET', '/forms/form_datas', 401],
            ['GET', '/forms/form_datas/123', 404],
        ];

        foreach ($endpoints as $ep) {
            [$method, $path, $status] = $ep;
            $client = self::createClient();
            $response = $client->request($method, $path);
            $this->assertEquals($status, $response->getStatusCode(), $path);
        }

    }
}
