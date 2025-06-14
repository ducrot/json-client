<?php

namespace TS\Web\JsonClient\Middleware;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use TS\Web\JsonClient\Exception\ServerMessageException;
use TS\Web\JsonClient\Exception\UnexpectedResponseException;

class ServerMessageMiddlewareTest extends TestCase
{
    private $middleware;
    private $nextHandler;

    protected function setUp(): void
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ServerMessageMiddleware($this->nextHandler);
    }

    public function testSuccessfulResponsePassesThrough()
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [RequestOptions::HTTP_ERRORS => true];

        $promise = $this->middleware->__invoke($request, $options);
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testJsonErrorResponseThrowsServerMessageException()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    Utils::streamFor('{"message":"Invalid input","details":"Field X is required","request_id":"123"}')
                );
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ServerMessageMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [RequestOptions::HTTP_ERRORS => true];

        $promise = $this->middleware->__invoke($request, $options);

        $this->expectException(ServerMessageException::class);
        $this->expectExceptionMessage('Invalid input');

        $promise->wait();
    }

    public function testNonJsonErrorResponsePassesThrough()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(
                    400,
                    ['Content-Type' => 'text/plain'],
                    Utils::streamFor('Invalid input')
                );
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ServerMessageMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [RequestOptions::HTTP_ERRORS => true];

        $promise = $this->middleware->__invoke($request, $options);
        $response = $promise->wait();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invalid input', (string)$response->getBody());
    }

    public function testInvalidJsonErrorResponseThrowsUnexpectedResponseException()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    Utils::streamFor('{invalid json}')
                );
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ServerMessageMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [RequestOptions::HTTP_ERRORS => true];

        $promise = $this->middleware->__invoke($request, $options);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Failed to decode json response: json_decode error: Syntax error');

        $promise->wait();
    }

    public function testServerMessageExceptionContainsAllFields()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    Utils::streamFor('{"message":"Invalid input","details":"Field X is required","request_id":"123"}')
                );
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ServerMessageMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [RequestOptions::HTTP_ERRORS => true];

        $promise = $this->middleware->__invoke($request, $options);

        try {
            $promise->wait();
            $this->fail('Expected ServerMessageException was not thrown');
        } catch (ServerMessageException $e) {
            $this->assertEquals('Invalid input', $e->getMessage());
            $this->assertEquals('Field X is required', $e->getDetails());
            $this->assertEquals('123', $e->getRequestId());
        }
    }
}
