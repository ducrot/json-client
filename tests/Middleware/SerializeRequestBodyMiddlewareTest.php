<?php

namespace TS\Web\JsonClient\Middleware;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;
use TS\Web\JsonClient\Fixtures\Payload;

class SerializeRequestBodyMiddlewareTest extends TestCase
{
    private $serializer;
    private $middleware;
    private $nextHandler;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->nextHandler = function ($request, $options) {
            $promise = new Promise(function () use (&$promise, $request) {
                $promise->resolve($request);
            });
            return $promise;
        };
        $this->middleware = new SerializeRequestBodyMiddleware($this->nextHandler, $this->serializer);
    }

    public function testSuccessfulSerialization()
    {
        $payload = new Payload('test', 123);
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($payload, 'json')
            ->willReturn('{"message":"test","value":123}');

        $request = new Request('POST', 'http://example.com', ['Content-Type' => 'application/json']);
        $options = ['data' => $payload];

        $promise = $this->middleware->__invoke($request, $options);
        $modifiedRequest = $promise->wait();

        $this->assertEquals('application/json', $modifiedRequest->getHeaderLine('Content-Type'));
        $this->assertEquals('{"message":"test","value":123}', (string)$modifiedRequest->getBody());
    }

    public function testNoDataOptionReturnsOriginalRequest()
    {
        $request = new Request('GET', 'http://example.com');
        $options = [];

        $promise = $this->middleware->__invoke($request, $options);
        $modifiedRequest = $promise->wait();

        $this->assertSame($request, $modifiedRequest);
    }

    public function testSerializerExceptionPropagates()
    {
        $payload = new Payload('test', 123);
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->willThrowException(new \RuntimeException('Serializer error'));

        $request = new Request('POST', 'http://example.com', ['Content-Type' => 'application/json']);
        $options = ['data' => $payload];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Serializer error');

        $promise = $this->middleware->__invoke($request, $options);
        $promise->wait();
    }

    public function testNonObjectPayloadHandling()
    {
        $payload = ['message' => 'test', 'value' => 123];
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($payload, 'json')
            ->willReturn('{"message":"test","value":123}');

        $request = new Request('POST', 'http://example.com', ['Content-Type' => 'application/json']);
        $options = ['data' => $payload];

        $promise = $this->middleware->__invoke($request, $options);
        $modifiedRequest = $promise->wait();

        $this->assertEquals('application/json', $modifiedRequest->getHeaderLine('Content-Type'));
        $this->assertEquals('{"message":"test","value":123}', (string)$modifiedRequest->getBody());
    }

    public function testDataContextOptionIsPassedToSerializer()
    {
        $payload = new Payload('test', 123);
        $context = ['foo' => 'bar'];
        $this->serializer->expects($this->once())
            ->method('serialize')
            ->with($payload, 'json', $context)
            ->willReturn('{"message":"test","value":123}');

        $request = new Request('POST', 'http://example.com', ['Content-Type' => 'application/json']);
        $options = ['data' => $payload, 'data_context' => $context];

        $promise = $this->middleware->__invoke($request, $options);
        $modifiedRequest = $promise->wait();

        $this->assertEquals('application/json', $modifiedRequest->getHeaderLine('Content-Type'));
        $this->assertEquals('{"message":"test","value":123}', (string)$modifiedRequest->getBody());
    }
}
