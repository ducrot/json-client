<?php

namespace TS\Web\JsonClient\Middleware;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TS\Web\JsonClient\Exception\UnexpectedResponseException;

class ResponseExpectationMiddlewareTest extends TestCase
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
        $this->middleware = new ResponseExpectationMiddleware($this->nextHandler);
    }

    public function testSuccessfulResponsePassesThrough()
    {
        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [];

        $promise = $this->middleware->__invoke($request, $options);
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExpectedContentTypePassesThrough()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200, ['Content-Type' => 'application/json']);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ResponseExpectationMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [ResponseExpectationMiddleware::REQUEST_OPTION_EXPECT_RESPONSE_TYPE => 'application/json'];

        $promise = $this->middleware->__invoke($request, $options);
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testUnexpectedContentTypeThrowsException()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200, ['Content-Type' => 'text/html']);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ResponseExpectationMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [ResponseExpectationMiddleware::REQUEST_OPTION_EXPECT_RESPONSE_TYPE => 'application/json'];

        $promise = $this->middleware->__invoke($request, $options);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected response content type to be application/json, got text/html instead.');

        $promise->wait();
    }

    public function testMissingContentTypeThrowsException()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ResponseExpectationMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [ResponseExpectationMiddleware::REQUEST_OPTION_EXPECT_RESPONSE_TYPE => 'application/json'];

        $promise = $this->middleware->__invoke($request, $options);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected response content type to be application/json, got  instead.');

        $promise->wait();
    }

    public function testNoExpectationOptionPassesThrough()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200, ['Content-Type' => 'text/html']);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ResponseExpectationMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [];

        $promise = $this->middleware->__invoke($request, $options);
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testMultipleContentTypesInHeader()
    {
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200, ['Content-Type' => 'application/json; charset=utf-8']);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new ResponseExpectationMiddleware($this->nextHandler);

        $request = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $options = [ResponseExpectationMiddleware::REQUEST_OPTION_EXPECT_RESPONSE_TYPE => 'application/json'];

        $promise = $this->middleware->__invoke($request, $options);
        $response = $promise->wait();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }
}
