<?php

namespace TS\Web\JsonClient\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use TS\Web\JsonClient\HttpLogging\HttpLoggerInterface;

class HttpLoggingMiddlewareTest extends TestCase
{
    private $middleware;
    private $nextHandler;
    private $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(HttpLoggerInterface::class);
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor('{"id": 1}'));
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new HttpLoggingMiddleware($this->nextHandler, $this->logger);
    }

    public function testSuccessfulRequestLogging()
    {
        $request = new Request('GET', 'http://example.com');
        $options = ['logger' => $this->logger];

        $this->logger->expects($this->once())
            ->method('logStart')
            ->with($request, $options)
            ->willReturn($request);

        $this->logger->expects($this->once())
            ->method('logSuccess')
            ->with(
                $this->anything(),
                $this->anything(),
                $options,
                $this->anything()
            );

        $handler = $this->middleware->__invoke($this->nextHandler);
        $promise = $handler($request, $options);
        $response = $promise->wait();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testFailedRequestLogging()
    {
        $request = new Request('GET', 'http://example.com');
        $options = ['logger' => $this->logger];
        $exception = new RequestException('Error', $request);

        $this->nextHandler = function ($request, $options) use ($exception) {
            return Create::rejectionFor($exception);
        };
        $this->middleware = new HttpLoggingMiddleware($this->nextHandler, $this->logger);

        $this->logger->expects($this->once())
            ->method('logStart')
            ->with($request, $options)
            ->willReturn($request);

        $this->logger->expects($this->once())
            ->method('logFailure')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $options,
                $this->anything()
            );

        $handler = $this->middleware->__invoke($this->nextHandler);
        $promise = $handler($request, $options);

        $this->expectException(RequestException::class);
        $promise->wait();
    }

    public function testUsesNullLoggerWhenNoLoggerProvided()
    {
        $request = new Request('GET', 'http://example.com');
        $options = [];

        $handler = $this->middleware->__invoke($this->nextHandler);
        $promise = $handler($request, $options);
        $response = $promise->wait();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTransferTimeIsCalculated()
    {
        $request = new Request('GET', 'http://example.com');
        $options = ['logger' => $this->logger];

        $this->logger->expects($this->once())
            ->method('logStart')
            ->with($request, $options)
            ->willReturn($request);

        $this->logger->expects($this->once())
            ->method('logSuccess')
            ->with(
                $this->anything(),
                $this->anything(),
                $options,
                $this->callback(function ($transferTime) {
                    return is_float($transferTime) && $transferTime > 0;
                })
            );

        $handler = $this->middleware->__invoke($this->nextHandler);
        $promise = $handler($request, $options);
        $promise->wait();
    }

    public function testRequestIsModifiedByLogger()
    {
        $originalRequest = new Request('GET', 'http://example.com');
        $modifiedRequest = new Request('POST', 'http://example.com');
        $options = ['logger' => $this->logger];

        $this->logger->expects($this->once())
            ->method('logStart')
            ->with($originalRequest, $options)
            ->willReturn($modifiedRequest);

        $this->nextHandler = function ($request, $options) use ($modifiedRequest) {
            $this->assertEquals('POST', $request->getMethod());
            $promise = new Promise(function () use (&$promise) {
                $response = new Response(200);
                $promise->resolve($response);
            });
            return $promise;
        };
        $this->middleware = new HttpLoggingMiddleware($this->nextHandler, $this->logger);

        $handler = $this->middleware->__invoke($this->nextHandler);
        $promise = $handler($originalRequest, $options);
        $promise->wait();
    }
}
